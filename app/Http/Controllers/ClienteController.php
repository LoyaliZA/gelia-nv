<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\CatalogoListaDescuento;
use App\Models\HistorialMontoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClienteController extends Controller
{
    /**
     * Procesa un archivo CSV masivo para actualizar montos.
     */
    public function importacionMasiva(Request $request)
    {
        Gate::authorize('cargar_clientes_masivo');
        
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
}