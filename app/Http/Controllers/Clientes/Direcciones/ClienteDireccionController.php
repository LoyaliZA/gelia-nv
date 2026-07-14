<?php

namespace App\Http\Controllers\Clientes\Direcciones;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\EnlaceDireccion;
use App\Services\Clientes\Direcciones\GenerarEnlaceDireccionService;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Services\Clientes\Direcciones\ValidarEnlaceDireccionService;
use App\Support\Clientes\FormatearDireccionEstructurada;
use App\Support\FormPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClienteDireccionController extends Controller
{
    public function index(Cliente $cliente, GestionDireccionesClienteService $gestion): Response
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
                'accion_permitida' => $e->accion_permitida,
                'expira_en' => $e->expira_en?->toIso8601String(),
                'usado_en' => $e->usado_en?->toIso8601String(),
                'esta_vigente' => $e->estaVigente(),
            ]);

        return Inertia::render('Clientes/Direcciones/Index', [
            'cliente' => [
                'id' => $cliente->id,
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => $cliente->nombre,
            ],
            'direcciones' => $direcciones,
            'enlaces' => $enlaces,
            'verificadas' => $gestion->listarActivasVerificadasPorCliente($cliente->id)->values(),
        ]);
    }

    public function store(Request $request, Cliente $cliente, GestionDireccionesClienteService $gestion)
    {
        Gate::authorize('clientes.direcciones.crear');

        $datos = $request->validate([
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

    public function update(Request $request, Cliente $cliente, ClienteDireccion $direccion, GestionDireccionesClienteService $gestion)
    {
        Gate::authorize('clientes.direcciones.editar');
        abort_unless($direccion->cliente_id === $cliente->id, 404);

        $datos = $request->validate([
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
        ]);

        $nueva = $gestion->crearNuevaVersion($direccion->id, $datos, [
            'usuario_id' => $request->user()->id,
            'origen' => ClienteDireccion::ORIGEN_INTERNAL,
        ]);

        if ($request->boolean('verificar', true)) {
            $gestion->verificar($nueva->id, ['usuario_id' => $request->user()->id]);
        }

        return redirect()->back()->with('success', 'Dirección actualizada (nueva versión).');
    }

    public function marcarPrincipal(Request $request, Cliente $cliente, ClienteDireccion $direccion, GestionDireccionesClienteService $gestion)
    {
        Gate::authorize('clientes.direcciones.editar');
        abort_unless($direccion->cliente_id === $cliente->id, 404);

        $gestion->marcarComoPrincipal($direccion->id, [
            'usuario_id' => $request->user()->id,
            'origen' => ClienteDireccion::ORIGEN_INTERNAL,
        ]);

        return redirect()->back()->with('success', 'Dirección marcada como principal.');
    }

    public function desactivar(Request $request, Cliente $cliente, ClienteDireccion $direccion, GestionDireccionesClienteService $gestion)
    {
        Gate::authorize('clientes.direcciones.desactivar');
        abort_unless($direccion->cliente_id === $cliente->id, 404);

        $gestion->desactivar($direccion->id, [
            'usuario_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Dirección desactivada.');
    }

    public function generarEnlace(Request $request, Cliente $cliente, GenerarEnlaceDireccionService $generar)
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

    public function revocarEnlace(Request $request, Cliente $cliente, EnlaceDireccion $enlace, ValidarEnlaceDireccionService $validador)
    {
        Gate::authorize('clientes.direcciones.generar_enlace');
        abort_unless($enlace->cliente_id === $cliente->id, 404);

        $validador->revocar($enlace, $request->user()->id);

        return redirect()->back()->with('success', 'Enlace revocado.');
    }
}
