<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GuardarClienteRequest;
use App\Models\Cliente;
use App\Models\CatalogoListaDescuento;
use App\Services\Clientes\CorreccionEmergenciaNumeroClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClienteController extends Controller
{
    /**
     * Crea un nuevo cliente desde el modal.
     */
    public function store(GuardarClienteRequest $request)
    {
        $validated = $this->datosClienteDesdeRequest($request);

        Cliente::create($validated);

        return redirect()->back()->with('success', 'Cliente creado exitosamente.');
    }

    /**
     * Actualiza un cliente existente desde el modal.
     */
    public function update(GuardarClienteRequest $request, Cliente $cliente, CorreccionEmergenciaNumeroClienteService $correccionEmergencia)
    {
        $validated = $this->datosClienteDesdeRequest($request);
        $numero = $validated['numero_cliente'];
        $nombre = $validated['nombre'];
        unset($validated['numero_cliente'], $validated['nombre']);

        if ($request->boolean('correccion_emergencia')) {
            Gate::authorize('clientes.correccion_emergencia');
            $correccionEmergencia->aplicar($cliente, $numero, $nombre, $validated);
        } else {
            $cliente->update(array_merge($validated, [
                'numero_cliente' => $numero,
                'nombre' => $nombre,
            ]));
        }

        return redirect()->back()->with('success', 'Cliente actualizado exitosamente.');
    }

    private function datosClienteDesdeRequest(GuardarClienteRequest $request): array
    {
        $validated = $request->validated();
        unset($validated['correccion_emergencia']);

        $validated['es_heredado'] = $request->boolean('es_heredado');
        $validated['lista_bloqueada'] = $request->boolean('lista_bloqueada');
        $validated['es_inactivo'] = $request->boolean('es_inactivo');

        if (array_key_exists('lista_actual_id', $validated) && $validated['lista_actual_id'] === null) {
            unset($validated['lista_actual_id']);
        }

        return $validated;
    }

    /**
     * Procesa un archivo CSV masivo para actualizar montos.
     */
    public function importacionMasiva(Request $request, \App\Services\Clientes\ImportarClientesWizerpService $importarService)
    {
        Gate::authorize('cargar_clientes_masivo');

        // Se corrige la regla de validación a 'archivo' para coincidir con el form de React
        $request->validate(['archivo' => 'required|file|mimes:csv,txt']);

        try {
            $resultado = $importarService->ejecutar($request->file('archivo'));

            return back()
                ->with('success', 'Carga masiva procesada y listas recalculadas correctamente.')
                ->with('reporte_importacion', $resultado['ascensos'] ?? []);
                
        } catch (\Exception $e) {
            return back()->withErrors(['archivo' => 'Error al procesar el archivo: ' . $e->getMessage()]);
        }
    }

    public function misClientes(Request $request)
    {
        $usuarioId = Auth::id();

        // Corrección en los nombres de las relaciones según Cliente.php
        $clientes = Cliente::with(['listaDescuento', 'tipo'])
            ->where(function ($q) use ($usuarioId) {
                $q->where('vendedor_original_id', $usuarioId)
                    ->orWhere('vendedor_id', $usuarioId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Clientes/MisClientes', [
            'clientes' => $clientes
        ]);
    }

    /**
     * Procesa la captura rápida de un cliente nuevo.
     * Se maneja la validación manualmente y se captura cualquier excepción SQL.
     */
    public function registroRapido(GuardarClienteRequest $request)
    {
        $numeroCliente = trim($request->numero_cliente);
        $nombre = trim($request->nombre);
        $usuarioId = Auth::id();

        $listaBase = CatalogoListaDescuento::orderBy('monto_requerido', 'asc')->first();
        $listaBaseId = $listaBase ? $listaBase->id : null;

        try {
            DB::transaction(function () use ($numeroCliente, $nombre, $usuarioId, $listaBaseId) {
                Cliente::create([
                    'numero_cliente'           => $numeroCliente,
                    'nombre'                   => $nombre,
                    'vendedor_id'              => $usuarioId,
                    'vendedor_original_id'     => $usuarioId,
                    'catalogo_tipo_cliente_id' => null,
                    'lista_actual_id'          => $listaBaseId,
                    'es_heredado'              => false,
                    'monto_venta_actual'       => 0.00
                ]);
            });

            return redirect()->route('mis_clientes.index')->with('success', 'Cliente registrado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('mis_clientes.index')->withErrors([
                'numero_cliente' => 'Fallo en BD: ' . $e->getMessage()
            ])->withInput();
        }
    }
    /**
     * Obtiene los clientes con listas protegidas o administrativas.
     */
    public function obtenerEspeciales()
    {
        $clientesEspeciales = Cliente::with(['listaDescuento'])
            ->where(function ($q) {
                $q->where('lista_bloqueada', true)
                    ->orWhereHas('listaDescuento', function ($sub) {
                        $sub->whereIn('nombre', ['COLABORADORES', 'PLATAFORMAS']);
                    });
            })
            ->limit(500)
            ->get(['id', 'numero_cliente', 'nombre', 'lista_actual_id', 'lista_bloqueada']);

        return response()->json($clientesEspeciales);
    }

    /**
     * Activa o desactiva la protección de lista para un cliente específico.
     */
    public function toggleBloqueoLista(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'bloquear'   => 'required|boolean'
        ]);

        $cliente = Cliente::findOrFail($validated['cliente_id']);
        $cliente->update([
            'lista_bloqueada' => $validated['bloquear']
        ]);

        return response()->json([
            'success' => true,
            'mensaje' => 'Estado de protección actualizado correctamente.'
        ]);
    }

    /**
     * Activa o desactiva la protección de lista para múltiples clientes a la vez.
     */
    public function toggleBloqueoMasivo(Request $request)
    {
        $validated = $request->validate([
            'cliente_ids'   => 'required|array',
            'cliente_ids.*' => 'exists:clientes,id',
            'bloquear'      => 'required|boolean'
        ]);

        Cliente::whereIn('id', $validated['cliente_ids'])->update([
            'lista_bloqueada' => $validated['bloquear']
        ]);

        return response()->json([
            'success' => true,
            'mensaje' => 'Estado de protección actualizado masivamente.'
        ]);
    }
    
}
