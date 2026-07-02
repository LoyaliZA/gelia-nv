<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AromasAvisosController extends Controller
{
    // Carga de la nueva vista de UI
    public function index()
    {
        return view('aromas.avisos');
    }

    // Motor de cruce O(N) para intersección de inventario
    public function procesar(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $validator = Validator::make($request->all(), [
            'orden_compra' => 'required|file|mimes:xlsx,xls,csv',
            'aviso_mercancia' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $diccionarioAvisos = [];

        // 1. CREACIÓN DEL HASH MAP: Extracción de UPCs del Aviso de Mercancía
        $this->procesarArchivoSeguro($request->file('aviso_mercancia'), function ($ruta) use (&$diccionarioAvisos) {
            (new FastExcel)->import($ruta, function ($linea) use (&$diccionarioAvisos) {
                $upc = trim((string)($linea['UPC'] ?? ''));
                if ($upc !== '') {
                    $upcLimpio = ltrim($upc, '0');
                    $diccionarioAvisos[$upcLimpio] = [
                        'vendedor' => $linea['VENDEDOR'] ?? 'SIN ASIGNAR',
                        'cliente' => $linea['CLIENTE'] ?? 'SIN DATOS'
                    ];
                }
            });
        });

        $resultados = [];

        // 2. CRUCE DINÁMICO: Escaneo de la Orden de Compra
        $this->procesarArchivoSeguro($request->file('orden_compra'), function ($ruta) use (&$resultados, $diccionarioAvisos) {
            $leyendoDatos = false;
            $indices = [];

            // Se lee sin cabeceras para evadir la fila basura "Compra,,,,," de Wizerp
            (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$resultados, $diccionarioAvisos, &$leyendoDatos, &$indices) {
                $valores = array_values($linea);

                // Detección en tiempo real de la fila de encabezados reales
                if (!$leyendoDatos) {
                    if (in_array('SKU', $valores) && in_array('Recibido', $valores)) {
                        $leyendoDatos = true;
                        $indices['sku'] = array_search('SKU', $valores);
                        // BLINDAJE: Forzamos la Columna B (Índice 1) evadiendo errores de codificación UTF-8 de Wizerp
                        $indices['descripcion'] = 1; 
                        $indices['recibido'] = array_search('Recibido', $valores);
                    }
                    return;
                }

                $skuCrudo = trim((string)($linea[$indices['sku']] ?? ''));
                $skuLimpio = ltrim($skuCrudo, '0');
                $recibido = (int)($linea[$indices['recibido']] ?? 0);

                // Lógica de negocio: Existe en el aviso Y llegaron piezas reales
                if ($skuLimpio !== '' && isset($diccionarioAvisos[$skuLimpio]) && $recibido > 0) {
                    $resultados[] = [
                        'SKU' => $skuCrudo,
                        // Extracción estricta de la Columna B
                        'Descripción' => $linea[$indices['descripcion']] ?? 'SIN DESCRIPCION',
                        'Piezas Recibidas' => $recibido,
                        'Vendedor Asignado' => $diccionarioAvisos[$skuLimpio]['vendedor'],
                        'Clientes en Espera' => $diccionarioAvisos[$skuLimpio]['cliente'],
                    ];
                }
            });
        });

        if (empty($resultados)) {
            return response()->json(['error' => 'El cruce finalizó, pero no se encontraron coincidencias de mercancía que haya llegado físicamente en la Orden de Compra.'], 404);
        }

        Log::info("AROMAS - Cruce de Aviso de Mercancía generado exitosamente (Renderizado en pantalla).");
        
        return response()->json([
            'success' => true,
            'data' => $resultados, // Este es el arreglo con SKU, Descripción, Piezas, etc.
            'count' => count($resultados)
        ]);
    }

    // Abstracción para el manejo seguro de almacenamiento en I/O
    private function procesarArchivoSeguro($archivo, callable $callbackLogica)
    {
        if (!$archivo) return;
        $nombreTemp = 'temp_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
        $rutaCompleta = sys_get_temp_dir() . '/' . $nombreTemp;
        $archivo->move(sys_get_temp_dir(), $nombreTemp);
        try {
            $callbackLogica($rutaCompleta);
        } finally {
            if (file_exists($rutaCompleta)) unlink($rutaCompleta);
        }
    }
}