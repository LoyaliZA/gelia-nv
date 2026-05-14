<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\CatalogoListaDescuento;
use App\Models\HistorialMontoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClienteController extends Controller
{
    /**
     * Crea un nuevo cliente desde el modal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_cliente'           => 'required|string|max:255|unique:clientes,numero_cliente',
            'nombre'                   => 'required|string|max:255',
            'vendedor_id'              => 'nullable|exists:users,id',
            'catalogo_tipo_cliente_id' => 'nullable|exists:catalogo_tipo_clientes,id'
        ]);

        // Forzamos la captura del booleano para que no dependa de si el input llegó o no
        $validated['es_heredado'] = $request->boolean('es_heredado');

        Cliente::create($validated);

        return redirect()->back()->with('success', 'Cliente creado exitosamente.');
    }

    /**
     * Actualiza un cliente existente desde el modal.
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'numero_cliente'           => 'required|string|max:255|unique:clientes,numero_cliente,' . $cliente->id,
            'nombre'                   => 'required|string|max:255',
            'vendedor_id'              => 'nullable|exists:users,id',
            'catalogo_tipo_cliente_id' => 'nullable|exists:catalogo_tipo_clientes,id'
        ]);

        // Al usar boolean(), si el checkbox no se marcó, devolverá false automáticamente
        // Esto permite "desactivar" la herencia si ya estaba activa.
        $validated['es_heredado'] = $request->boolean('es_heredado');

        $cliente->update($validated);

        return redirect()->back()->with('success', 'Cliente actualizado exitosamente.');
    }

    /**
     * Procesa un archivo CSV masivo para actualizar montos.
     */
    public function importacionMasiva(Request $request)
    {
        Gate::authorize('cargar_clientes_masivo');
        
        // Cuidado aquí: si desde react lo mandas como 'archivo', debes validarlo como 'archivo'.
        // Si tu form en React usa formCarga.setData('archivo', file), cámbialo aquí a 'archivo'
        $request->validate(['archivo_csv' => 'required|mimes:csv,txt']);

        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get(); // Ordenadas de mayor a menor monto
        
        $path = $request->file('archivo_csv')->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file); // Saltar cabeceras

        DB::beginTransaction();
        try {
            while ($row = fgetcsv($file)) {
                // Asumiendo CSV: numero_cliente [0], nombre [1], monto_actual [2]
                $numeroCliente = $row[0];
                $montoNuevo = (float) $row[2];

                $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();

                if ($cliente && $cliente->monto_venta_actual != $montoNuevo) {
                    $diferencia = $montoNuevo - $cliente->monto_venta_actual;

                    // 1. Evaluar subida o bajada de lista dinámica
                    $nuevaListaId = $cliente->lista_actual_id;
                    foreach ($listas as $lista) {
                        if ($montoNuevo >= $lista->monto_requerido) {
                            $nuevaListaId = $lista->id;
                            break;
                        }
                    }

                    // 2. Registrar historial
                    HistorialMontoCliente::create([
                        'cliente_id' => $cliente->id,
                        'monto_anterior' => $cliente->monto_venta_actual,
                        'monto_nuevo' => $montoNuevo,
                        'diferencia_aplicada' => $diferencia
                    ]);

                    // 3. Actualizar cliente
                    $cliente->update([
                        'monto_venta_actual' => $montoNuevo,
                        'lista_actual_id' => $nuevaListaId
                    ]);
                }
            }
            DB::commit();
            fclose($file);

            return back()->with('success', 'Carga masiva procesada y listas recalculadas correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            return back()->withErrors(['archivo_csv' => 'Error al procesar el archivo: ' . $e->getMessage()]);
        }
    }

    public function misClientes(Request $request)
    {
        $usuarioId = Auth::id();

        // Extraemos los clientes creados por la vendedora o asignados a ella,
        // ordenados por los más recientes para facilitar la visualización inmediata tras el alta.
        $clientes = Cliente::where('vendedor_original_id', $usuarioId)
            ->orWhere('vendedor_id', $usuarioId)
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
    public function registroRapido(Request $request)
    {
        // 1. Validación manual
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'numero_cliente' => 'required|string|max:255|unique:clientes,numero_cliente',
            'nombre'         => 'required|string|max:255',
        ], [
            'numero_cliente.unique'   => 'Este número de cliente ya se encuentra registrado.',
            'numero_cliente.required' => 'El número es obligatorio.',
            'nombre.required'         => 'El nombre es obligatorio.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('mis_clientes.index')
                             ->withErrors($validator)
                             ->withInput();
        }

        $numeroCliente = trim($request->numero_cliente);
        $nombre = trim($request->nombre);
        $usuarioId = Auth::id();

        // 2. Lógica de negocio: Obtener la lista base (PG)
        // Buscamos la lista con el menor monto requerido (usualmente 0 para Público General)
        $listaBase = \App\Models\CatalogoListaDescuento::orderBy('monto_requerido', 'asc')->first();
        $listaBaseId = $listaBase ? $listaBase->id : null;

        try {
            // 3. Transacción segura
            DB::transaction(function () use ($numeroCliente, $nombre, $usuarioId, $listaBaseId) {
                Cliente::create([
                    'numero_cliente'           => $numeroCliente,
                    'nombre'                   => $nombre,
                    'vendedor_id'              => $usuarioId,
                    'vendedor_original_id'     => $usuarioId,
                    'catalogo_tipo_cliente_id' => null, 
                    'lista_actual_id'          => $listaBaseId, // <-- Parche aplicado
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
}