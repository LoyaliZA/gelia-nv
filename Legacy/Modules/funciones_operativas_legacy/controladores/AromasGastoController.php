<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Validator;

class AromasGastoController extends Controller
{
    public function index()
    {
        return view('aromas.gastos');
    }

    public function procesar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'archivo_gastos' => 'required|file',
            'filtro_tipo' => 'nullable|string|in:TODOS,Remisión,Pedido'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filtroTipo = $request->input('filtro_tipo', 'TODOS');
        $listaGastos = [];

        $this->procesarArchivoSeguro($request->file('archivo_gastos'), function ($ruta) use (&$listaGastos, $filtroTipo) {
            
            (new FastExcel)->import($ruta, function ($linea) use (&$listaGastos, $filtroTipo) {
                
                $folioCrudo = $linea['Folio de Venta'] ?? '';
                
                // Lógica de separación ORIGINAL
                $partes = explode(' ', trim((string)$folioCrudo), 2);
                
                $tipoVenta = $partes[0] ?? ''; // Ej: "Remisión"
                $folioVenta = $partes[1] ?? ''; // Ej: "42077"

                // Lógica de filtrado ORIGINAL
                if ($filtroTipo !== 'TODOS' && strcasecmp($tipoVenta, $filtroTipo) !== 0) {
                    return; 
                }

                // Reconstrucción de la fila ORIGINAL
                $listaGastos[] = [
                    'Fecha' => $linea['Fecha'] ?? '',
                    'Cliente' => $linea['Cliente'] ?? '',
                    'Tipo de Venta' => $tipoVenta,      
                    'Folio de Venta' => $folioVenta,    
                    'Folio gasto' => $linea['Folio gasto'] ?? '',
                    'Descripción' => $linea['Descripción'] ?? '',
                    'Cantidad' => $linea['Cantidad'] ?? '',
                    'Importe' => $linea['Importe'] ?? ''
                ];
            });
        });

        // Seguro anti-corrupción XML
        if (empty($listaGastos)) {
            return response()->json([
                'error' => "No se encontraron registros de tipo: $filtroTipo en el archivo proporcionado."
            ], 404);
        }

        // Configuración de estilos ORIGINAL
        $estiloEncabezado = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold();

        $fecha = date('d-m-y');
        return (new FastExcel(collect($listaGastos)))
            ->headerStyle($estiloEncabezado)
            ->download("GASTOS-COMPROBABLES-$fecha.xlsx");
    }

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