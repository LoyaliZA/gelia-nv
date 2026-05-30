<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiAplicacion;
use App\Models\ApiAplicacionCampo;
use App\Models\ApiAplicacionPermiso;
use App\Models\ApiAuditoria;
use App\Models\ApiCampoRecurso;
use App\Models\ApiRecurso;
use App\Services\ApiExterna\ApiAplicacionService;
use App\Services\ApiExterna\ApiDocumentacionPdfService;
use App\Services\ApiExterna\ApiDocumentacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ApiExternaController extends Controller
{
    public function __construct(
        protected ApiAplicacionService $aplicacionService,
        protected ApiDocumentacionService $documentacionService,
        protected ApiDocumentacionPdfService $documentacionPdfService
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->can('api_externa.gestionar') && !$request->user()->can('api_externa.ver_auditoria')) {
            abort(403);
        }

        $puedeGestionar = $request->user()->can('api_externa.gestionar');
        $auditorias = collect();
        if ($request->user()->can('api_externa.ver_auditoria') || $puedeGestionar) {
            $auditorias = ApiAuditoria::with('aplicacion')
                ->when($request->query('app_id'), fn ($q, $id) => $q->where('api_aplicacion_id', $id))
                ->when($request->query('status'), fn ($q, $status) => $q->where('status_code', $status))
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
                ->map(fn (ApiAuditoria $log) => [
                    'id' => $log->id,
                    'aplicacion' => $log->aplicacion?->nombre ?? 'Sin autenticar',
                    'metodo' => $log->metodo,
                    'ruta' => $log->ruta,
                    'ip' => $log->ip,
                    'status_code' => $log->status_code,
                    'duracion_ms' => $log->duracion_ms,
                    'error_resumen' => $log->error_resumen,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]);
        }

        return Inertia::render('Admin/ApiExterna', [
            'puedeGestionar' => $puedeGestionar,
            'aplicaciones' => $puedeGestionar ? ApiAplicacion::with(['permisos.recurso', 'campos.campo.recurso'])
                ->orderBy('nombre')
                ->get()
                ->map(fn (ApiAplicacion $app) => [
                    'id' => $app->id,
                    'nombre' => $app->nombre,
                    'descripcion' => $app->descripcion,
                    'client_id' => $app->client_id,
                    'activa' => $app->activa,
                    'ips_permitidas' => $app->ips_permitidas ?? [],
                    'limite_por_minuto' => $app->limite_por_minuto,
                    'created_at' => $app->created_at?->toIso8601String(),
                    'permisos' => $app->permisos->map(fn ($p) => [
                        'id' => $p->id,
                        'recurso_id' => $p->api_recurso_id,
                        'recurso_slug' => $p->recurso?->slug,
                        'recurso_nombre' => $p->recurso?->nombre,
                        'puede_leer' => $p->puede_leer,
                        'puede_escribir' => $p->puede_escribir,
                        'activo' => $p->activo,
                    ]),
                    'campos' => $app->campos->map(fn ($c) => [
                        'id' => $c->id,
                        'campo_id' => $c->api_campo_recurso_id,
                        'campo_slug' => $c->campo?->slug,
                        'campo_etiqueta' => $c->campo?->etiqueta,
                        'recurso_slug' => $c->campo?->recurso?->slug,
                        'habilitado' => $c->habilitado,
                    ]),
                ]) : ApiAplicacion::orderBy('nombre')->get(['id', 'nombre'])->map(fn (ApiAplicacion $app) => [
                    'id' => $app->id,
                    'nombre' => $app->nombre,
                    'permisos' => [],
                    'campos' => [],
                ]),
            'recursos' => $puedeGestionar ? ApiRecurso::with('campos')->orderBy('nombre')->get()->map(fn (ApiRecurso $r) => [
                'id' => $r->id,
                'slug' => $r->slug,
                'nombre' => $r->nombre,
                'descripcion' => $r->descripcion,
                'activo' => $r->activo,
                'lectura_habilitada' => $r->lectura_habilitada,
                'escritura_habilitada' => $r->escritura_habilitada,
                'campos' => $r->campos->map(fn ($c) => [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'etiqueta' => $c->etiqueta,
                    'es_sensible' => $c->es_sensible,
                    'habilitado_global' => $c->habilitado_global,
                    'orden' => $c->orden,
                ]),
            ]) : [],
            'auditorias' => $auditorias,
            'documentacion' => $puedeGestionar ? $this->documentacionService->construirDatos() : null,
            'filtros' => $request->only(['app_id', 'status']),
            'baseUrl' => rtrim(config('app.url'), '/') . '/api/v1',
            'credenciales_nuevas' => session('api_credenciales_nuevas'),
            'secret_regenerado' => session('api_secret_regenerado'),
        ]);
    }

    public function storeAplicacion(Request $request): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'ips_permitidas' => 'nullable|array',
            'ips_permitidas.*' => 'string|max:45',
            'limite_por_minuto' => 'nullable|integer|min:1|max:1000',
        ]);

        $resultado = $this->aplicacionService->crear($validated, $request->user()->id);

        return redirect()
            ->route('admin.api_externa.index')
            ->with('api_credenciales_nuevas', [
                'nombre' => $resultado['aplicacion']->nombre,
                'client_id' => $resultado['client_id'],
                'client_secret' => $resultado['client_secret'],
            ]);
    }

    public function updateAplicacion(Request $request, ApiAplicacion $aplicacion): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'activa' => 'required|boolean',
            'ips_permitidas' => 'nullable|array',
            'ips_permitidas.*' => 'string|max:45',
            'limite_por_minuto' => 'required|integer|min:1|max:1000',
        ]);

        $aplicacion->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activa' => $validated['activa'],
            'ips_permitidas' => empty($validated['ips_permitidas']) ? null : array_values($validated['ips_permitidas']),
            'limite_por_minuto' => $validated['limite_por_minuto'],
        ]);

        return redirect()->back()->with('success', 'Aplicación actualizada.');
    }

    public function regenerarSecret(ApiAplicacion $aplicacion): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $secret = $this->aplicacionService->regenerarSecret($aplicacion);

        return redirect()
            ->back()
            ->with('api_secret_regenerado', [
                'nombre' => $aplicacion->nombre,
                'client_secret' => $secret,
            ]);
    }

    public function revocarTokens(ApiAplicacion $aplicacion): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $this->aplicacionService->revocarTokens($aplicacion);

        return redirect()->back()->with('success', 'Tokens revocados.');
    }

    public function destroyAplicacion(ApiAplicacion $aplicacion): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $this->aplicacionService->revocarTokens($aplicacion);
        $aplicacion->delete();

        return redirect()->back()->with('success', 'Aplicación eliminada.');
    }

    public function updateRecurso(Request $request, ApiRecurso $recurso): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $validated = $request->validate([
            'activo' => 'required|boolean',
            'lectura_habilitada' => 'required|boolean',
            'escritura_habilitada' => 'required|boolean',
        ]);

        $recurso->update($validated);

        return redirect()->back()->with('success', 'Recurso actualizado.');
    }

    public function updateCampo(Request $request, ApiCampoRecurso $campo): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $validated = $request->validate([
            'habilitado_global' => 'required|boolean',
        ]);

        $campo->update($validated);

        return redirect()->back()->with('success', 'Campo actualizado.');
    }

    public function updatePermiso(Request $request, ApiAplicacionPermiso $permiso): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $validated = $request->validate([
            'puede_leer' => 'required|boolean',
            'puede_escribir' => 'required|boolean',
            'activo' => 'required|boolean',
        ]);

        $permiso->update($validated);

        return redirect()->back()->with('success', 'Permiso actualizado.');
    }

    public function updateCampoAplicacion(Request $request, ApiAplicacionCampo $campo): RedirectResponse
    {
        Gate::authorize('api_externa.gestionar');

        $validated = $request->validate([
            'habilitado' => 'required|boolean',
        ]);

        $campo->update($validated);

        return redirect()->back()->with('success', 'Campo de aplicación actualizado.');
    }

    public function descargarDocumentacion(): HttpResponse
    {
        Gate::authorize('api_externa.gestionar');

        return $this->documentacionPdfService
            ->generar()
            ->download('GELIANV-API-v1.pdf');
    }
}
