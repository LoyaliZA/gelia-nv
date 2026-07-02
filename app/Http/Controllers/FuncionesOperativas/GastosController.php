<?php

namespace App\Http\Controllers\FuncionesOperativas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class GastosController extends Controller
{
    public function index()
    {
        return Inertia::render('FuncionesOperativas/Gastos');
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
            
            $isTitle = true;
            $isHeader = true;
            
            (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$listaGastos, $filtroTipo, &$isTitle, &$isHeader) {
                
                // Saltar la fila 1 (Título "GastosComprobables")
                if ($isTitle) {
                    $isTitle = false;
                    return;
                }
                
                // Saltar la fila 2 (Encabezados reales)
                if ($isHeader) {
                    $isHeader = false;
                    return;
                }
                
                // Mapeo por índices numéricos (A=0, B=1, C=2, D=3, E=4, F=5, G=6)
                $folioCrudo = $linea[2] ?? '';
                
                // Lógica de separación ORIGINAL
                $partes = explode(' ', trim((string)$folioCrudo), 2);
                
                $tipoVenta = $partes[0] ?? ''; // Ej: "Remisión"
                
                // Extraer el folio y convertirlo a número si es posible, de lo contrario mantenerlo como cadena
                $folioVentaRaw = trim($partes[1] ?? '');
                $folioVenta = is_numeric($folioVentaRaw) ? (float)$folioVentaRaw : $folioVentaRaw;

                // Lógica de filtrado ORIGINAL
                if ($filtroTipo !== 'TODOS' && strcasecmp($tipoVenta, $filtroTipo) !== 0) {
                    return; 
                }

                // Ignorar filas completamente vacías al final del archivo
                if (empty(trim((string)($linea[0] ?? ''))) && empty(trim((string)$folioCrudo))) {
                    return;
                }

                // Reconstrucción de la fila
                $listaGastos[] = [
                    'Fecha' => $linea[0] ?? '',
                    'Cliente' => $linea[1] ?? '',
                    'Tipo de Venta' => $tipoVenta,      
                    'Folio de Venta' => $folioVenta,    
                    'Folio gasto' => is_numeric(trim($linea[3] ?? '')) ? (float)trim($linea[3]) : ($linea[3] ?? ''),
                    'Descripción' => $linea[4] ?? '',
                    'Cantidad' => is_numeric(trim($linea[5] ?? '')) ? (float)trim($linea[5]) : ($linea[5] ?? ''),
                    'Importe' => is_numeric(trim($linea[6] ?? '')) ? (float)trim($linea[6]) : ($linea[6] ?? '')
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