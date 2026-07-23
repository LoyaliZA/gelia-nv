<?php

namespace App\Http\Controllers\Traspasos;

use App\Events\SolicitudTraspasoActualizada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Traspasos\ResponderSolicitudTraspasoRequest;
use App\Http\Requests\Traspasos\StoreSolicitudTraspasoRequest;
use App\Models\Almacen;
use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoHorarioTraspaso;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use App\Services\Traspasos\CrearSolicitudTraspasoService;
use App\Services\Traspasos\EliminarSolicitudTraspasoService;
use App\Services\Traspasos\ListarSolicitudesTraspasoService;
use App\Services\Traspasos\NotificarTraspasoService;
use App\Services\Traspasos\ResponderSolicitudTraspasoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SolicitudTraspasoController extends Controller
{
    public function index(Request $request, ListarSolicitudesTraspasoService $listarService): Response
    {
        Gate::authorize('traspasos.ver_listado');

        $traspasos = $listarService->ejecutar(Auth::user(), $request->all());

        $vendedores = User::permission('traspasos.crear')
            ->orderBy('name')
            ->get(['id', 'name']);

        $almacenes = Almacen::where('activo', true)
            ->where('visible_en_traspasos', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $horarios = CatalogoHorarioTraspaso::where('activo', true)
            ->orderBy('orden')
            ->get(['id', 'nombre', 'hora_inicio', 'hora_fin', 'dias_para_entrega', 'descripcion']);

        return Inertia::render('Traspasos/Index', [
            'traspasos' => $traspasos,
            'filtros' => $request->all(),
            'vendedores' => $vendedores,
            'almacenes' => $almacenes,
            'horarios' => $horarios,
            'estados' => CatalogoEstadoSolicitud::orderBy('id')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreSolicitudTraspasoRequest $request, CrearSolicitudTraspasoService $crearService): RedirectResponse
    {
        $solicitud = $crearService->ejecutar($request->validated(), Auth::id());

        event(new SolicitudTraspasoActualizada(
            solicitudId: $solicitud->id,
            accion: 'creada',
            porUsuarioId: Auth::id(),
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        return redirect()->route('traspasos.index')->with('success', 'Solicitud de traspaso creada correctamente.');
    }

    public function show(SolicitudTraspaso $traspaso, ListarSolicitudesTraspasoService $listarService): JsonResponse
    {
        Gate::authorize('traspasos.ver_listado');

        if (! $listarService->usuarioPuedeVer(Auth::user(), $traspaso)) {
            abort(403);
        }

        $traspaso->load([
            'vendedor:id,name',
            'estado:id,nombre',
            'cliente:id,numero_cliente,nombre',
            'almacenOrigen:id,codigo,nombre',
            'horario:id,nombre,dias_para_entrega,descripcion',
            'productos:id,solicitud_traspaso_id,producto_id,sku,descripcion,piezas',
            'respondidaPor:id,name',
            'auditorias.usuario:id,name',
            'auditorias.estadoNuevo:id,nombre',
            'auditorias.estadoAnterior:id,nombre',
        ]);

        return response()->json(['traspaso' => $traspaso]);
    }

    public function actualizarEstado(
        ResponderSolicitudTraspasoRequest $request,
        SolicitudTraspaso $traspaso,
        ResponderSolicitudTraspasoService $responderService
    ): RedirectResponse {
        $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $estadoNuevo = (int) $request->catalogo_estado_solicitud_id;

        if ($idPendiente !== null && (int) $traspaso->catalogo_estado_solicitud_id !== $idPendiente) {
            abort(422, 'Solo se puede responder una solicitud en estado Pendiente.');
        }

        if ($idRespondida !== null && $estadoNuevo === $idRespondida && $idPendiente !== null
            && (int) $traspaso->catalogo_estado_solicitud_id !== $idPendiente) {
            abort(422, 'Solo se puede aprobar una solicitud en estado Pendiente.');
        }

        if ($idIncorrecta !== null && $estadoNuevo !== $idIncorrecta && $estadoNuevo !== $idRespondida) {
            abort(422, 'Estado de respuesta no válido.');
        }

        $datos = $request->validated();
        if ($request->hasFile('evidencia_respuesta')) {
            $datos['evidencia_respuesta'] = $request->file('evidencia_respuesta');
        }

        $responderService->ejecutar($traspaso, $datos, Auth::user());

        event(new SolicitudTraspasoActualizada(
            solicitudId: $traspaso->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $traspaso->vendedor_id,
            departamentoId: $traspaso->departamento_id,
        ));

        return redirect()->back()->with('success', 'Solicitud de traspaso actualizada.');
    }

    public function verificar(
        SolicitudTraspaso $traspaso,
        NotificarTraspasoService $notificar
    ): RedirectResponse {
        Gate::authorize('traspasos.verificar');

        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idVerificada = CatalogoEstadoSolicitud::idDe('Verificada');

        if ($idRespondida === null || (int) $traspaso->catalogo_estado_solicitud_id !== $idRespondida) {
            abort(422, 'Solo se pueden verificar solicitudes respondidas.');
        }

        $estadoAnterior = $traspaso->catalogo_estado_solicitud_id;
        $traspaso->update(['catalogo_estado_solicitud_id' => $idVerificada]);

        AuditoriaSolicitudTraspaso::create([
            'solicitud_traspaso_id' => $traspaso->id,
            'usuario_id' => Auth::id(),
            'estado_anterior_id' => $estadoAnterior,
            'estado_nuevo_id' => $idVerificada,
            'motivo_reporte' => 'Solicitud verificada por auxiliar.',
        ]);

        $notificar->respuesta(
            $traspaso->fresh(['vendedor', 'estado', 'cliente']),
            'verificada',
            'Tu solicitud de traspaso fue verificada.',
            Auth::id()
        );

        event(new SolicitudTraspasoActualizada(
            solicitudId: $traspaso->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $traspaso->vendedor_id,
            departamentoId: $traspaso->departamento_id,
        ));

        return redirect()->back()->with('success', 'Solicitud verificada.');
    }

    public function destroy(
        SolicitudTraspaso $traspaso,
        Request $request,
        EliminarSolicitudTraspasoService $eliminarService
    ): RedirectResponse {
        Gate::authorize('traspasos.eliminar');

        $request->validate(['motivo' => 'required|string|min:5|max:500']);

        $vendedorId = $traspaso->vendedor_id;
        $departamentoId = $traspaso->departamento_id;
        $traspasoId = $traspaso->id;

        $eliminarService->ejecutar($traspaso, $request->motivo, Auth::user());

        event(new SolicitudTraspasoActualizada(
            solicitudId: $traspasoId,
            accion: 'eliminada',
            porUsuarioId: Auth::id(),
            vendedorId: $vendedorId,
            departamentoId: $departamentoId,
        ));

        return redirect()->back()->with('success', 'Solicitud eliminada.');
    }

    public function evidencia(
        SolicitudTraspaso $traspaso,
        ListarSolicitudesTraspasoService $listarService
    ): StreamedResponse {
        $user = Auth::user();
        if (! $user->can('traspasos.ver_listado') && ! $user->can('traspasos.cedis')) {
            abort(403);
        }

        if (! $listarService->usuarioPuedeVer($user, $traspaso)) {
            abort(403);
        }

        if (! $traspaso->evidencia_respuesta_path || ! Storage::disk('public')->exists($traspaso->evidencia_respuesta_path)) {
            abort(404);
        }

        $mime = Storage::disk('public')->mimeType($traspaso->evidencia_respuesta_path) ?: 'application/octet-stream';
        $nombre = basename($traspaso->evidencia_respuesta_path);

        return Storage::disk('public')->response($traspaso->evidencia_respuesta_path, $nombre, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename=\"{$nombre}\"",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
