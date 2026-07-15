<?php

namespace App\Http\Controllers\Clientes\Direcciones;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SolicitudDireccion;
use App\Models\User;
use App\Notifications\Clientes\SolicitudDireccionRequiereRevision;
use App\Services\Clientes\Direcciones\AprobarSolicitudDireccionService;
use App\Support\Clientes\FormatearDireccionEstructurada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudDireccionRevisionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        $query = SolicitudDireccion::query()
            ->with(['clienteCoincidente:id,numero_cliente,nombre', 'revisadaPorUsuario:id,name'])
            ->orderByDesc('created_at');

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }
        if ($accion = $request->query('accion')) {
            $query->where('accion_solicitada', $accion);
        }
        if ($request->boolean('con_remision')) {
            $query->where('anexa_remision', true);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('folio', 'like', "%{$q}%")
                    ->orWhere('numero_cliente_declarado', 'like', "%{$q}%")
                    ->orWhere('nombre_declarado', 'like', "%{$q}%");
            });
        }

        $solicitudes = $query->paginate(20)->withQueryString();

        return Inertia::render('Clientes/Direcciones/BandejaSolicitudes', [
            'solicitudes' => $solicitudes,
            'filtros' => $request->only(['estado', 'accion', 'con_remision', 'q']),
        ]);
    }

    public function show(SolicitudDireccion $solicitud): Response
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        $solicitud->load(['clienteCoincidente', 'direccionSeleccionada', 'enlace']);

        $existente = $solicitud->direccionSeleccionada;
        $solicitada = $solicitud->datos_solicitados_json ?? [];

        return Inertia::render('Clientes/Direcciones/RevisarSolicitud', [
            'solicitud' => $solicitud,
            'comparacion' => [
                'existente' => $existente ? [
                    'resumen' => FormatearDireccionEstructurada::ejecutar($existente),
                    'datos' => $existente->only([
                        'nombre_destinatario', 'telefono_destinatario', 'calle', 'numero_exterior',
                        'numero_interior', 'colonia', 'codigo_postal', 'municipio', 'ciudad', 'estado', 'pais',
                    ]),
                ] : null,
                'solicitada' => $solicitada,
                'resumen_solicitada' => FormatearDireccionEstructurada::ejecutar($solicitada),
            ],
        ]);
    }

    public function aprobar(Request $request, SolicitudDireccion $solicitud, AprobarSolicitudDireccionService $aprobar)
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        $validated = $request->validate([
            'notas' => ['nullable', 'string', 'max:2000'],
            'correcciones' => ['nullable', 'array'],
        ]);

        $aprobar->ejecutar($solicitud, [
            'usuario_id' => $request->user()->id,
            'notas' => $validated['notas'] ?? null,
            'correcciones' => $validated['correcciones'] ?? [],
        ]);

        return redirect()
            ->route('admin.clientes.direcciones.solicitudes.index')
            ->with('success', 'Solicitud aprobada.');
    }

    public function rechazar(Request $request, SolicitudDireccion $solicitud)
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        $validated = $request->validate([
            'notas' => ['required', 'string', 'max:2000'],
        ]);

        $solicitud->update([
            'estado' => SolicitudDireccion::ESTADO_REJECTED,
            'notas_validacion' => $validated['notas'],
            'revisada_por' => $request->user()->id,
            'revisada_en' => now(),
        ]);

        return redirect()
            ->route('admin.clientes.direcciones.solicitudes.index')
            ->with('success', 'Solicitud rechazada.');
    }

    public function solicitarCorreccion(Request $request, SolicitudDireccion $solicitud)
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        $validated = $request->validate([
            'notas' => ['required', 'string', 'max:2000'],
        ]);

        $solicitud->update([
            'estado' => SolicitudDireccion::ESTADO_REQUIRES_CORRECTION,
            'notas_validacion' => $validated['notas'],
            'revisada_por' => $request->user()->id,
            'revisada_en' => now(),
        ]);

        return redirect()->back()->with('success', 'Se solicitó corrección al cliente.');
    }

    public function vincularCliente(Request $request, SolicitudDireccion $solicitud)
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
        ]);

        $solicitud->update([
            'cliente_coincidente_id' => $validated['cliente_id'],
            'estado' => SolicitudDireccion::ESTADO_VERIFIED,
            'revisada_por' => $request->user()->id,
            'revisada_en' => now(),
        ]);

        return redirect()->back()->with('success', 'Cliente vinculado.');
    }

    public function descargarRemision(SolicitudDireccion $solicitud)
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        if (! $solicitud->archivo_remision || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($solicitud->archivo_remision)) {
            abort(404, 'Archivo no encontrado.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $solicitud->archivo_remision,
            basename($solicitud->archivo_remision)
        );
    }

    public function notificarPendientes(): void
    {
        $pendientes = SolicitudDireccion::query()
            ->whereIn('estado', [
                SolicitudDireccion::ESTADO_PENDING,
                SolicitudDireccion::ESTADO_IDENTITY_REVIEW,
                SolicitudDireccion::ESTADO_POSSIBLE_DUPLICATE,
            ])
            ->count();

        if ($pendientes === 0) {
            return;
        }

        $revisores = User::permission('clientes.direcciones.revisar_solicitudes')->get();
        Notification::send($revisores, new SolicitudDireccionRequiereRevision($pendientes));
    }
}
