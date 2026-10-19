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
            'limiteSegundos' => max(10, (int) config('gelia_ai.max_turn_seconds', 90)),
            'recomendacionesDisponibles' => app(\App\Services\GeliaAi\Skills\RecomendacionesPerfumeria::class)->disponiblePara($request->user()),
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
            'modo' => ['nullable', 'string', 'in:general,recomendaciones'],
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
                $validated['modo'] ?? null,
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

        // Solo se ejecuta una propuesta guardada en el servidor; accion/payload del navegador se ignoran.
        $validated = $this->validarPropuesta($request);
        $user = $request->user();
        $id = $validated['propuesta_id'];
        $convId = isset($validated['conversacion_id']) ? (int) $validated['conversacion_id'] : null;

        $reclamo = $propuestas->reclamar($user, $id);
        if (! isset($reclamo['propuesta'])) {
            if ($convId && $reclamo['estado'] !== PropuestasAccionGeliaAi::OCUPADA && $reclamo['estado'] !== PropuestasAccionGeliaAi::PROCESANDO) {
                $conversaciones->actualizarContexto($user, $convId, fn ($ctx) => $contexto->descartarPropuesta($contexto->normalizar($ctx), $id));
            }

            return $this->rechazoPropuesta($propuestas, $reclamo['estado']);
        }

        $accion = (string) $reclamo['propuesta']['accion'];
        $payload = (array) $reclamo['propuesta']['payload'];
        $error = null;

        try {
            if (! $registry->soporta($accion)) {
                throw new RuntimeException('Esta acción ya no está disponible.');
            }
            $result = $registry->ejecutar($user, $accion, $payload);
        } catch (AuthorizationException $e) {
            $error = [$e->getMessage() ?: 'No tienes permiso para esta acción.', 403];
        } catch (ValidationException $e) {
            $error = [collect($e->errors())->flatten()->first() ?: 'Los datos de la propuesta ya no son válidos.', 422];
        } catch (RuntimeException $e) {
            $error = [$e->getMessage(), preg_match('/permiso|acceso operable/iu', $e->getMessage()) ? 403 : 422];
        } catch (Throwable $e) {
            report($e);
            $error = ['No se pudo completar la acción. Revisa el módulo correspondiente antes de prepararla de nuevo.', 500];
        }

        // Fallida no vuelve a pendiente: un error o timeout nunca habilita una segunda ejecución automática.
        $propuestas->finalizar($user, $id, $error === null);

        if ($error !== null) {
            [$mensaje, $code] = $error;
            if ($convId) {
                $conversaciones->registrarEventoAccion(
                    $user,
                    $convId,
                    "**No se completó la acción.** {$mensaje}\n\nNo se reintentó automáticamente.",
                    $contexto->registrarAccionEjecutada(
                        $contexto->normalizar($conversaciones->contextoDe($user, $convId)),
                        $accion,
                        ['estado' => PropuestasAccionGeliaAi::FALLIDA, 'resumen' => $mensaje],
                    ),
                );
            }

            return response()->json(['message' => $mensaje, 'estado_propuesta' => PropuestasAccionGeliaAi::FALLIDA], $code);
        }

        $result['mensaje'] = $this->mensajeReporte($result);
        $result['estado_propuesta'] = PropuestasAccionGeliaAi::EJECUTADA;

        if ($convId) {
            $ctx = $contexto->registrarAccionEjecutada(
                $contexto->normalizar($conversaciones->contextoDe($user, $convId)),
                $accion,
                (array) ($result['reporte'] ?? []),
            );
            $conversaciones->registrarEventoAccion($user, $convId, $result['mensaje'], $ctx);
        }

        return response()->json($result);
    }

    public function cancelarAccion(
        Request $request,
        ResolverAccesoGeliaAi $acceso,
        PropuestasAccionGeliaAi $propuestas,
        GeliaAiConversacionService $conversaciones,
        ContextoConversacionGeliaAi $contexto,
    ): JsonResponse {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $validated = $this->validarPropuesta($request, false);
        $user = $request->user();
        $id = $validated['propuesta_id'];
        $convId = isset($validated['conversacion_id']) ? (int) $validated['conversacion_id'] : null;

        $res = $propuestas->cancelar($user, $id);
        if (! isset($res['propuesta'])) {
            return $this->rechazoPropuesta($propuestas, $res['estado']);
        }

        if ($convId) {
            $ctx = $contexto->descartarPropuesta($contexto->normalizar($conversaciones->contextoDe($user, $convId)), $id);
            $conversaciones->registrarEventoAccion(
                $user,
                $convId,
                'Propuesta cancelada; no se ejecutó nada: '.$res['propuesta']['resumen'],
                $ctx,
            );
        }

        return response()->json(['ok' => true, 'estado_propuesta' => PropuestasAccionGeliaAi::CANCELADA]);
    }

    /** @return array{propuesta_id: string, conversacion_id?: int|string|null} */
    private function validarPropuesta(Request $request, bool $confirmar = true): array
    {
        return $request->validate([
            'propuesta_id' => ['required', 'string', 'uuid'],
            'confirmado' => $confirmar ? ['required', 'accepted'] : ['nullable'],
            'conversacion_id' => [
                'nullable',
                'integer',
                Rule::exists('gelia_ai_conversaciones', 'id')->where(
                    fn ($q) => $q->where('user_id', $request->user()->id)
                ),
            ],
        ], [
            'propuesta_id.required' => 'Falta la propuesta a confirmar. Pide a GELIA que prepare la acción y confírmala desde su tarjeta.',
            'propuesta_id.uuid' => 'La propuesta indicada no es válida. Pide a GELIA que la prepare de nuevo.',
            'confirmado.required' => 'Confirma la acción desde su tarjeta para ejecutarla.',
            'confirmado.accepted' => 'Confirma la acción desde su tarjeta para ejecutarla.',
        ]);
    }

    private function rechazoPropuesta(PropuestasAccionGeliaAi $propuestas, string $estado): JsonResponse
    {
        return response()->json([
            'message' => $propuestas->mensajeRechazo($estado),
            'estado_propuesta' => $estado,
        ], $propuestas->codigoHttp($estado));
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
            'modo' => 'general',
            'titulo' => null,
            'temporal' => true,
        ], 201);
    }

    public function showConversacion(
        Request $request,
        int $conversacion,
        ResolverAccesoGeliaAi $acceso,
        GeliaAiConversacionService $svc,
        ContextoConversacionGeliaAi $contexto,
        PropuestasAccionGeliaAi $propuestas,
        GeliaAiArchivoService $archivos,
    ): JsonResponse {
        abort_unless($acceso->puedeUsar($request->user()), 403);

        $user = $request->user();
        $conv = $svc->obtenerDeUsuario($user, $conversacion);
        $ctx = $contexto->normalizar(is_array($conv->contexto) ? $conv->contexto : null);

        $pendiente = null;
        $idPendiente = ($ctx['tarea_pendiente']['estado'] ?? null) === 'esperando_confirmacion'
            ? (string) ($ctx['tarea_pendiente']['propuesta_id'] ?? '')
            : '';
        if ($idPendiente !== '' && ($p = $propuestas->pendiente($user, $idPendiente))) {
            $pendiente = $propuestas->publica($user, $p);
        }

        return response()->json([
            'id' => $conv->id,
            'titulo' => $conv->titulo,
            'modo' => $ctx['modo'],
            'temporal' => (bool) $conv->temporal,
            'messages' => $svc->mensajesDe($conv),
            'propuesta_pendiente' => $pendiente,
            'archivos' => array_map([$archivos, 'resumenPublico'], $archivos->metasDisponibles($user, $ctx['archivos'])),
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
