<?php

namespace App\Http\Controllers\Traspasos;

use App\Events\SolicitudTraspasoActualizada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Traspasos\ConfirmarTraspasoCedisRequest;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoRevisionProducto;
use App\Services\Traspasos\ConfirmarTraspasoCedisService;
use App\Services\Traspasos\ListarSolicitudesTraspasoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TraspasoCedisController extends Controller
{
    public function index(Request $request, ListarSolicitudesTraspasoService $listarService): Response
    {
        Gate::authorize('traspasos.cedis');

        $traspasos = $listarService->ejecutar(Auth::user(), $request->all());

        return Inertia::render('Traspasos/Cedis/Index', [
            'traspasos' => $traspasos,
            'filtros' => $request->all(),
        ]);
    }

    public function confirmar(
        ConfirmarTraspasoCedisRequest $request,
        SolicitudTraspaso $traspaso,
        ConfirmarTraspasoCedisService $service,
        ListarSolicitudesTraspasoService $listarService
    ): RedirectResponse {
        Gate::authorize('traspasos.cedis');

        $user = Auth::user();
        $idVerificada = CatalogoEstadoSolicitud::idDe('Verificada');
        $yaVerificadaPorCedis = $user->can('traspasos.cedis')
            && $idVerificada !== null
            && (int) $traspaso->catalogo_estado_solicitud_id === $idVerificada;

        if (! $listarService->usuarioPuedeVer($user, $traspaso) && ! $yaVerificadaPorCedis) {
            abort(403);
        }

        $revisionesInput = collect($request->validated('revisiones') ?? [])->map(function (array $rev, int $i) use ($request) {
            $files = $request->file("revisiones.{$i}.evidencias") ?? [];

            return [
                ...$rev,
                'evidencias' => is_array($files) ? $files : ($files ? [$files] : []),
            ];
        })->all();

        $estadoAntes = (int) $traspaso->catalogo_estado_solicitud_id;

        try {
            $fresh = $service->ejecutar($traspaso, $user, $revisionesInput);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        if ($estadoAntes !== (int) $fresh->catalogo_estado_solicitud_id) {
            event(new SolicitudTraspasoActualizada(
                solicitudId: $traspaso->id,
                accion: 'actualizada',
                porUsuarioId: Auth::id(),
                vendedorId: $traspaso->vendedor_id,
                departamentoId: $traspaso->departamento_id,
            ));
        }

        return redirect()->back()->with('success', 'Recepción confirmada por CEDIS.');
    }

    public function fotoRevision(
        SolicitudTraspasoRevisionProducto $revision,
        int $indice,
        ListarSolicitudesTraspasoService $listarService
    ): StreamedResponse {
        $user = Auth::user();
        if (! $user->can('traspasos.ver_listado') && ! $user->can('traspasos.cedis')) {
            abort(403);
        }

        $traspaso = $revision->solicitud;
        if (! $traspaso || ! $listarService->usuarioPuedeVer($user, $traspaso)) {
            abort(403);
        }

        $paths = $revision->evidencia_paths ?? [];
        $path = $paths[$indice] ?? null;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        $nombre = basename($path);

        return Storage::disk('public')->response($path, $nombre, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename=\"{$nombre}\"",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
