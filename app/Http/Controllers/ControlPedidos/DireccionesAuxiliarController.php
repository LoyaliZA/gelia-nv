<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\EnlaceDireccion;
use App\Models\SolicitudDireccion;
use App\Services\Clientes\Direcciones\AprobarSolicitudDireccionService;
use App\Services\Clientes\Direcciones\GenerarEnlaceDireccionService;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Services\Clientes\Direcciones\ValidarEnlaceDireccionService;
use App\Support\Clientes\FormatearDireccionEstructurada;
use App\Support\FormPublicUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DireccionesAuxiliarController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        Gate::authorize('clientes.direcciones.ver');

        $clienteId = $request->integer('cliente_id') ?: null;
        if ($clienteId) {
            return redirect()->route('control_pedidos.direcciones.cliente', $clienteId);
        }

        $termino = trim((string) $request->query('q', ''));

        $query = Cliente::query()
            ->select(['id', 'numero_cliente', 'nombre'])
            ->withCount(['direccionesActivas as direcciones_activas_count']);

        $this->aplicarFiltroCliente($query, $termino);

        $clientes = $query
            ->orderBy('numero_cliente')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('ControlPedidos/Direcciones/Index', [
            'clientes' => $clientes,
            'filtros' => [
                'q' => $termino,
            ],
        ]);
    }

    public function buscarCliente(Request $request): JsonResponse
    {
        Gate::authorize('clientes.direcciones.ver');

        $termino = trim((string) $request->query('q', ''));
        if (mb_strlen($termino) < 2) {
            return response()->json(['data' => []]);
        }

        $query = Cliente::query()->select(['id', 'numero_cliente', 'nombre']);
        $this->aplicarFiltroCliente($query, $termino);

        $data = $query->orderBy('numero_cliente')->limit(15)->get();

        return response()->json(['data' => $data]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Cliente>  $query
     */
    private function aplicarFiltroCliente($query, string $termino): void
    {
        if ($termino === '') {
            return;
        }

        $query->where(function ($sub) use ($termino) {
            if (preg_match('/^\d/', $termino)) {
                $sub->where('numero_cliente', 'like', "{$termino}%");
            }
            $sub->orWhere('nombre', 'like', "{$termino}%");
            if (mb_strlen($termino) >= 3) {
                $sub->orWhere('nombre', 'like', "%{$termino}%");
            }
        });
    }

    public function cliente(Cliente $cliente, GestionDireccionesClienteService $gestion): Response
    {
        Gate::authorize('clientes.direcciones.ver');

        $direcciones = ClienteDireccion::query()
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('esta_activa')
            ->orderByDesc('es_principal')
            ->orderBy('numero_direccion')
            ->orderByDesc('version')
            ->get()
            ->map(fn (ClienteDireccion $d) => [
                'id' => $d->id,
                'numero_direccion' => $d->numero_direccion,
                'etiqueta' => $d->etiqueta,
                'tipo_direccion' => $d->tipo_direccion,
                'nombre_destinatario' => $d->nombre_destinatario,
                'telefono_destinatario' => $d->telefono_destinatario,
                'calle' => $d->calle,
                'numero_exterior' => $d->numero_exterior,
                'numero_interior' => $d->numero_interior,
                'colonia' => $d->colonia,
                'codigo_postal' => $d->codigo_postal,
                'municipio' => $d->municipio,
                'ciudad' => $d->ciudad,
                'estado' => $d->estado,
                'pais' => $d->pais,
                'referencias' => $d->referencias,
                'indicaciones_entrega' => $d->indicaciones_entrega,
                'resumen' => FormatearDireccionEstructurada::resumida($d),
                'es_principal' => $d->es_principal,
                'esta_activa' => $d->esta_activa,
                'estado_verificacion' => $d->estado_verificacion,
                'version' => $d->version,
                'updated_at' => $d->updated_at?->toIso8601String(),
            ]);

        $enlaces = EnlaceDireccion::query()
            ->where('cliente_id', $cliente->id)
            ->whereNull('revocado_en')
            ->where(function ($q) {
                $q->whereNull('expira_en')->orWhere('expira_en', '>', now());
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (EnlaceDireccion $e) => [
                'id' => $e->id,
                'codigo_publico' => $e->codigo_publico,
                'url' => $e->urlPublica(),
                'accion_permitida' => $e->accion_permitida,
                'expira_en' => $e->expira_en?->toIso8601String(),
                'usado_en' => $e->usado_en?->toIso8601String(),
                'esta_vigente' => $e->estaVigente(),
            ]);

        $pendientes = SolicitudDireccion::query()
            ->where(function ($q) use ($cliente) {
                $q->where('cliente_coincidente_id', $cliente->id)
                    ->orWhere('numero_cliente_declarado', $cliente->numero_cliente);
            })
            ->whereIn('estado', [
                SolicitudDireccion::ESTADO_PENDING,
                SolicitudDireccion::ESTADO_IDENTITY_REVIEW,
                SolicitudDireccion::ESTADO_POSSIBLE_DUPLICATE,
                SolicitudDireccion::ESTADO_REQUIRES_CORRECTION,
            ])
            ->count();

        return Inertia::render('ControlPedidos/Direcciones/FichaCliente', [
            'cliente' => [
                'id' => $cliente->id,
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => $cliente->nombre,
            ],
            'direcciones' => $direcciones,
            'enlaces' => $enlaces,
            'solicitudes_pendientes' => $pendientes,
        ]);
    }

    public function store(Request $request, Cliente $cliente, GestionDireccionesClienteService $gestion): RedirectResponse
    {
        Gate::authorize('clientes.direcciones.crear');

        $datos = $this->validarDatosDireccion($request);
        $ctx = [
            'usuario_id' => $request->user()->id,
            'origen' => ClienteDireccion::ORIGEN_INTERNAL,
            'verificar' => $request->boolean('verificar', true),
            'es_principal' => $request->boolean('es_principal'),
        ];

        $tieneActivas = ClienteDireccion::query()
            ->where('cliente_id', $cliente->id)
            ->activas()
            ->exists();

        if (! $tieneActivas) {
            $gestion->crearPrimeraDireccion($cliente->id, $datos, array_merge($ctx, ['es_principal' => true]));
        } else {
            $gestion->crearDireccionAdicional($cliente->id, $datos, $ctx);
        }

        return redirect()->back()->with('success', 'Dirección registrada.');
    }

    public function update(
        Request $request,
        Cliente $cliente,
        ClienteDireccion $direccion,
        GestionDireccionesClienteService $gestion
    ): RedirectResponse {
        Gate::authorize('clientes.direcciones.editar');
        abort_unless($direccion->cliente_id === $cliente->id, 404);

        $datos = $this->validarDatosDireccion($request);

        $nueva = $gestion->crearNuevaVersion($direccion->id, $datos, [
            'usuario_id' => $request->user()->id,
            'origen' => ClienteDireccion::ORIGEN_INTERNAL,
        ]);

        if ($request->boolean('verificar', true)) {
            $gestion->verificar($nueva->id, ['usuario_id' => $request->user()->id]);
        }

        return redirect()->back()->with('success', 'Dirección actualizada (nueva versión).');
    }

    public function marcarPrincipal(
        Request $request,
        Cliente $cliente,
        ClienteDireccion $direccion,
        GestionDireccionesClienteService $gestion
    ): RedirectResponse {
        Gate::authorize('clientes.direcciones.editar');
        abort_unless($direccion->cliente_id === $cliente->id, 404);

        $gestion->marcarComoPrincipal($direccion->id, [
            'usuario_id' => $request->user()->id,
            'origen' => ClienteDireccion::ORIGEN_INTERNAL,
        ]);

        return redirect()->back()->with('success', 'Dirección marcada como principal.');
    }

    public function desactivar(
        Request $request,
        Cliente $cliente,
        ClienteDireccion $direccion,
        GestionDireccionesClienteService $gestion
    ): RedirectResponse {
        Gate::authorize('clientes.direcciones.desactivar');
        abort_unless($direccion->cliente_id === $cliente->id, 404);

        $gestion->desactivar($direccion->id, [
            'usuario_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Dirección desactivada.');
    }

    public function generarEnlace(Request $request, Cliente $cliente, GenerarEnlaceDireccionService $generar): RedirectResponse
    {
        Gate::authorize('clientes.direcciones.generar_enlace');

        $validated = $request->validate([
            'accion' => ['nullable', 'string', 'max:40'],
            'direccion_id' => ['nullable', 'integer', 'exists:cliente_direcciones,id'],
            'horas' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $resultado = $generar->ejecutar($cliente, [
            'accion' => $validated['accion'] ?? null,
            'direccion_id' => $validated['direccion_id'] ?? null,
            'horas' => $validated['horas'] ?? 72,
            'usuario_id' => $request->user()->id,
        ]);

        $url = $resultado['url'] ?? FormPublicUrl::direccionShow($resultado['token']);

        return redirect()->back()->with('success', 'Enlace generado.')->with('enlace_direccion_url', $url);
    }

    public function revocarEnlace(
        Request $request,
        Cliente $cliente,
        EnlaceDireccion $enlace,
        ValidarEnlaceDireccionService $validador
    ): RedirectResponse {
        Gate::authorize('clientes.direcciones.generar_enlace');
        abort_unless($enlace->cliente_id === $cliente->id, 404);

        $validador->revocar($enlace, $request->user()->id);

        return redirect()->back()->with('success', 'Enlace revocado.');
    }

    public function solicitudesIndex(Request $request): Response
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
        if ($clienteId = $request->integer('cliente_id')) {
            $query->where('cliente_coincidente_id', $clienteId);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('folio', 'like', "%{$q}%")
                    ->orWhere('numero_cliente_declarado', 'like', "%{$q}%")
                    ->orWhere('nombre_declarado', 'like', "%{$q}%");
            });
        }

        return Inertia::render('ControlPedidos/Direcciones/BandejaSolicitudes', [
            'solicitudes' => $query->paginate(20)->withQueryString(),
            'filtros' => $request->only(['estado', 'accion', 'con_remision', 'q', 'cliente_id']),
            'rutaBase' => 'control_pedidos.direcciones.solicitudes',
        ]);
    }

    public function solicitudesShow(SolicitudDireccion $solicitud): Response
    {
        Gate::authorize('clientes.direcciones.revisar_solicitudes');

        $solicitud->load(['clienteCoincidente', 'direccionSeleccionada', 'enlace']);
        $existente = $solicitud->direccionSeleccionada;
        $solicitada = $solicitud->datos_solicitados_json ?? [];

        return Inertia::render('ControlPedidos/Direcciones/RevisarSolicitud', [
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
            'rutaBase' => 'control_pedidos.direcciones.solicitudes',
        ]);
    }

    public function solicitudesAprobar(
        Request $request,
        SolicitudDireccion $solicitud,
        AprobarSolicitudDireccionService $aprobar
    ): RedirectResponse {
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
            ->route('control_pedidos.direcciones.solicitudes.index')
            ->with('success', 'Solicitud aprobada.');
    }

    public function solicitudesRechazar(Request $request, SolicitudDireccion $solicitud): RedirectResponse
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
            ->route('control_pedidos.direcciones.solicitudes.index')
            ->with('success', 'Solicitud rechazada.');
    }

    public function solicitudesCorreccion(Request $request, SolicitudDireccion $solicitud): RedirectResponse
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

    public function solicitudesVincular(Request $request, SolicitudDireccion $solicitud): RedirectResponse
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

    public function solicitudesRemision(SolicitudDireccion $solicitud)
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

    private function validarDatosDireccion(Request $request): array
    {
        return $request->validate([
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:30'],
            'calle' => ['required', 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:30'],
            'numero_interior' => ['nullable', 'string', 'max:30'],
            'colonia' => ['required', 'string', 'max:255'],
            'codigo_postal' => ['required', 'regex:/^\d{5}$/'],
            'municipio' => ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:255'],
            'referencias' => ['nullable', 'string'],
            'indicaciones_entrega' => ['nullable', 'string'],
            'etiqueta' => ['nullable', 'string', 'max:100'],
            'tipo_direccion' => ['nullable', 'string', 'max:50'],
            'es_principal' => ['nullable', 'boolean'],
            'verificar' => ['nullable', 'boolean'],
        ]);
    }
}
