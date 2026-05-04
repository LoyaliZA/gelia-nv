<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Cliente;
use App\Models\CatalogoListaDescuento;
use App\Models\HistorialMontoCliente;
use App\Models\TabuladorComision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    // --- VISTAS EXISTENTES ---
    public function enlaces(): Response {
        return Inertia::render('Admin/Enlaces');
    }

    public function catalogos(): Response {
        return Inertia::render('Admin/Catalogos');
    }

    public function usuarios(): Response {
        // En el futuro puedes mandar los usuarios reales desde BD aquí
        return Inertia::render('Admin/Usuarios');
    }

    // --- MÓDULO DE CLIENTES (WIZERP) ---
    public function clientes(): Response {
        $clientes = Cliente::with('listaDescuento')->get();
        return Inertia::render('Admin/Clientes', ['clientes' => $clientes]);
    }

    public function importarClientes(Request $request)
    {
        Gate::authorize('cargar_clientes_masivo');
        
        $request->validate(['archivo' => 'required|mimes:csv,txt,xlsx,xls']);

        // Aquí usamos fopen para CSV como lo diseñamos previamente
        $path = $request->file('archivo')->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();

        DB::beginTransaction();
        try {
            while ($row = fgetcsv($file)) {
                $numeroCliente = $row[0];
                $montoNuevo = (float) $row[2]; // Asume: Num [0], Nombre [1], Monto [2]

                $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();

                if ($cliente && $cliente->monto_venta_actual != $montoNuevo) {
                    $diferencia = $montoNuevo - $cliente->monto_venta_actual;
                    $nuevaListaId = $cliente->lista_actual_id;

                    foreach ($listas as $lista) {
                        if ($montoNuevo >= $lista->monto_requerido) {
                            $nuevaListaId = $lista->id;
                            break;
                        }
                    }

                    HistorialMontoCliente::create([
                        'cliente_id' => $cliente->id,
                        'monto_anterior' => $cliente->monto_venta_actual,
                        'monto_nuevo' => $montoNuevo,
                        'diferencia_aplicada' => $diferencia
                    ]);

                    $cliente->update([
                        'monto_venta_actual' => $montoNuevo,
                        'lista_actual_id' => $nuevaListaId
                    ]);
                }
            }
            DB::commit();
            fclose($file);

            return back()->with('success', 'Base de datos de clientes actualizada correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            return back()->withErrors(['archivo' => 'Error procesando archivo: ' . $e->getMessage()]);
        }
    }

    public function historialCliente(Cliente $cliente) {
        // Retornará el historial para mostrarlo en el frontend (ej. un modal)
        return response()->json($cliente->historialMontos()->get());
    }

    // --- MÓDULO DE COMISIONES ---
    public function comisiones(): Response {
        $tabulador = TabuladorComision::with('proceso')->get();
        return Inertia::render('Admin/Comisiones', ['tabulador' => $tabulador]);
    }

    public function actualizarComision(Request $request, $id) {
        // Aquí deberías tener un permiso específico, ej: Gate::authorize('configurar_comisiones');
        
        $request->validate([
            'monto_comision' => 'required|numeric|min:0',
            'activo' => 'boolean'
        ]);

        $comision = TabuladorComision::findOrFail($id);
        $comision->update($request->only(['monto_comision', 'activo']));

        return back()->with('success', 'Tabulador actualizado exitosamente.');
    }

    // --- NOTIFICACIONES ---
    public function marcarNotificacionLeida($id) {
        $notificacion = auth()->user()->notifications()->findOrFail($id);
        $notificacion->markAsRead();
        return back();
    }
}