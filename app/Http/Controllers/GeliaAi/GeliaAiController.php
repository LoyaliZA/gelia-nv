<?php

namespace App\Http\Controllers\GeliaAi;

use App\Http\Controllers\Controller;
use App\Services\GeliaAi\Acciones\AccionRegistry;
use App\Services\GeliaAi\Acciones\PropuestasAccionGeliaAi;
use App\Services\GeliaAi\ContextoConversacionGeliaAi;
use App\Services\GeliaAi\DeepSeekClient;
use App\Services\GeliaAi\DeepSeekException;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\GeliaAiChatService;
use App\Services\GeliaAi\GeliaAiConversacionService;
use App\Services\GeliaAi\GeliaAiUsoService;
use App\Services\GeliaAi\InspeccionarArchivoGeliaAiService;
use App\Services\GeliaAi\ResolverAccesoGeliaAi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class GeliaAiController extends Controller
{
    public function index(
        Request $request,
        ResolverAccesoGeliaAi $acceso,
        DeepSeekClient $client,
        GeliaAiConversacionService $conversaciones,
    ): Response {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        return Inertia::render('GeliaAi/Index', [
            'configurado' => $client->estaConfigurado(),
            'conversaciones' => $conversaciones->listar($request->user()),
        ]);
    }

    public function chat(
        Request $request,
        ResolverAccesoGeliaAi $acceso,
        GeliaAiChatService $chatService,
        GeliaAiConversacionService $conversaciones,
        GeliaAiUsoService $usos,
    ): JsonResponse {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'messages' => ['nullable', 'array', 'max:12'],
            'messages.*.role' => ['required_with:messages', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required_with:messages', 'string', 'max:4000'],
            'file_ids' => ['nullable', 'array', 'max:'.GeliaAiArchivoService::MAX_FILES],
            'file_ids.*' => ['string', 'uuid'],
            'conversacion_id' => [
                'nullable',
                'integer',
                Rule::exists('gelia_ai_conversaciones', 'id')->where(
                    fn ($q) => $q->where('user_id', $request->user()->id)
                ),
            ],
        ]);

        try {
            $fileIds = $validated['file_ids'] ?? [];
            $conversacionId = isset($validated['conversacion_id']) ? (int) $validated['conversacion_id'] : null;
            // El historial se toma del servidor; `messages` del cliente se ignora (no es fuente confiable).
            $previo = $conversaciones->cargarParaChat($request->user(), $conversacionId);

            $result = $chatService->chat(
                $request->user(),
                $validated['message'],
                $previo['historial'],
                $fileIds,
                $previo['contexto'],
            );

            $saved = $conversaciones->persistirTurno(
                $request->user(),
                $conversacionId,
                $validated['message'],
                $result['reply'],
                $result['contexto'],
            );
            unset($result['contexto']);

            $result['conversacion_id'] = $saved['conversacion_id'];
            $result['titulo'] = $saved['titulo'];

            $usos->registrar(
                $request->user(),
                (int) $saved['conversacion_id'],
                $validated['message'],
                $result,
                $fileIds !== [],
            );
        } catch (DeepSeekException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no está configurado') ? 503 : 422;

            return response()->json(['message' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Error al consultar Gel-IA. Intenta de nuevo.'], 500);
        }

        return response()->json($result);
    }

    public function subirArchivos(
        Request $request,
        ResolverAccesoGeliaAi $acceso,
        GeliaAiArchivoService $archivos,
        InspeccionarArchivoGeliaAiService $inspector,
    ): JsonResponse {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $request->validate([
            'archivos' => ['required', 'array', 'min:1', 'max:'.GeliaAiArchivoService::MAX_FILES],
            'archivos.*' => [
                'file',
                // extensions evita falsos rechazos de CSV (text/plain / application/octet-stream)
                'extensions:csv,xlsx,xls',
                'max:'.(GeliaAiArchivoService::MAX_MB * 1024),
            ],
        ], [
            'archivos.max' => 'Máximo '.GeliaAiArchivoService::MAX_FILES.' archivos.',
            'archivos.*.extensions' => 'Solo se permiten archivos CSV, XLSX o XLS.',
            'archivos.*.max' => 'Cada archivo puede pesar hasta '.GeliaAiArchivoService::MAX_MB.' MB.',
        ]);

        try {
            $lista = $archivos->guardarYInspeccionar(
                $request->user(),
                $request->file('archivos', []),
                $inspector,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['files' => $lista]);
    }

    public function ejecutarAccion(
        Request $request,
        ResolverAccesoGeliaAi $acceso,
        AccionRegistry $registry,
        PropuestasAccionGeliaAi $propuestas,
        GeliaAiConversacionService $conversaciones,
        ContextoConversacionGeliaAi $contexto,
    ): JsonResponse {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $validated = $request->validate([
            'propuesta_id' => ['nullable', 'string', 'uuid'],
            'accion' => ['required_without:propuesta_id', 'string', 'max:64'],
            'payload' => ['required_without:propuesta_id', 'array'],
            'confirmado' => ['required', 'accepted'],
            'conversacion_id' => [
                'nullable',
                'integer',
                Rule::exists('gelia_ai_conversaciones', 'id')->where(
                    fn ($q) => $q->where('user_id', $request->user()->id)
                ),
            ],
        ]);

        $user = $request->user();
        if (! empty($validated['propuesta_id'])) {
            // Se ejecuta exactamente lo propuesto y mostrado; uso único para evitar dobles importaciones.
            $propuesta = $propuestas->consumir($user, $validated['propuesta_id']);
            if (! $propuesta) {
                return response()->json(['message' => 'La propuesta expiró o ya se ejecutó. Pide a GELIA que la prepare de nuevo.'], 422);
            }
            $accion = (string) $propuesta['accion'];
            $payload = (array) $propuesta['payload'];
        } else {
            $accion = (string) $validated['accion'];
            $payload = (array) $validated['payload'];
        }

        if (! $registry->soporta($accion)) {
            return response()->json(['message' => 'Acción no soportada.'], 422);
        }

        try {
            $result = $registry->ejecutar($user, $accion, $payload);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'No autorizado.'], 403);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first() ?: 'Datos inválidos.'], 422);
        } catch (RuntimeException $e) {
            $code = preg_match('/permiso|acceso operable/iu', $e->getMessage()) ? 403 : 422;

            return response()->json(['message' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo completar la acción. Revisa el módulo correspondiente antes de reintentar.'], 500);
        }

        $result['mensaje'] = $this->mensajeReporte($result);

        if (! empty($validated['conversacion_id'])) {
            $convId = (int) $validated['conversacion_id'];
            $ctx = $contexto->registrarAccionEjecutada(
                $contexto->normalizar($conversaciones->contextoDe($user, $convId)),
                $accion,
                (array) ($result['reporte'] ?? []),
            );
            $conversaciones->registrarEventoAccion($user, $convId, $result['mensaje'], $ctx);
        }

        return response()->json($result);
    }

    /** @param  array<string, mixed>  $result */
    private function mensajeReporte(array $result): string
    {
        $r = (array) ($result['reporte'] ?? []);
        $lineas = ['**'.($r['resumen'] ?? 'Acción ejecutada.').'**'];
        if (! empty($r['log_id'])) {
            $lineas[] = "- Registro de importación: `{$r['log_id']}` (el resultado por fila se revisa en Almacenes → Importaciones).";
        }
        if (isset($r['conteos'])) {
            $lineas[] = "- Filas generadas: {$r['conteos']['ok']}; con inconsistencia: {$r['conteos']['error']}.";
        }
        $detalles = array_slice((array) ($r['detalles'] ?? []), 0, 8);
        if ($detalles !== []) {
            $lineas[] = '';
            $lineas[] = 'Avisos:';
            foreach ($detalles as $d) {
                $lineas[] = '- '.($d['sku'] ?? '—').': '.($d['error'] ?? '');
            }
            if (count((array) $r['detalles']) > 8) {
                $lineas[] = '- …y '.(count((array) $r['detalles']) - 8).' más';
            }
        }

        return implode("\n", $lineas);
    }

    public function conversaciones(Request $request, ResolverAccesoGeliaAi $acceso, GeliaAiConversacionService $svc): JsonResponse
    {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        return response()->json(['data' => $svc->listar($request->user())]);
    }

    public function storeConversacion(Request $request, ResolverAccesoGeliaAi $acceso, GeliaAiConversacionService $svc): JsonResponse
    {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $conv = $svc->crearTemporal($request->user());

        return response()->json([
            'id' => $conv->id,
            'titulo' => null,
            'temporal' => true,
        ], 201);
    }

    public function showConversacion(
        Request $request,
        int $conversacion,
        ResolverAccesoGeliaAi $acceso,
        GeliaAiConversacionService $svc,
    ): JsonResponse {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $conv = $svc->obtenerDeUsuario($request->user(), $conversacion);

        return response()->json([
            'id' => $conv->id,
            'titulo' => $conv->titulo,
            'temporal' => (bool) $conv->temporal,
            'messages' => $svc->mensajesDe($conv),
        ]);
    }

    public function destroyConversacion(
        Request $request,
        int $conversacion,
        ResolverAccesoGeliaAi $acceso,
        GeliaAiConversacionService $svc,
    ): JsonResponse {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $svc->eliminar($request->user(), $conversacion);

        return response()->json(['ok' => true]);
    }
}
