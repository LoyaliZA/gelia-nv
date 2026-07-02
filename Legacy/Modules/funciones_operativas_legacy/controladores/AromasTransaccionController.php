<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Validator;

class AromasTransaccionController extends Controller
{
    // 1. Muestra la página web (la vista) de Transacciones
    public function index()
    {
        return view('aromas.transacciones');
    }

    // 2. Recibe el Excel, limpia los saltos de línea y devuelve el formato correcto
    public function procesar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'archivo_transacciones' => 'required|file'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $listaTransacciones = [];

        // 1. Procesamiento y Sanitización de Datos (Elimina los saltos de línea del banco)
        $this->procesarArchivoSeguro($request->file('archivo_transacciones'), function ($ruta) use (&$listaTransacciones) {
            (new FastExcel)->import($ruta, function ($linea) use (&$listaTransacciones) {
                
                // Función interna (Closure) para limpiar enters \n, \r y espacios múltiples
                $limpiarTexto = function ($texto) {
                    return trim(preg_replace('/\s+/', ' ', (string)($texto ?? '')));
                };

                // Armamos la fila con cada columna estrictamente sanitizada
                $listaTransacciones[] = [
                    'Fecha Movimiento'  => $limpiarTexto($linea['Fecha Movimiento'] ?? ''),
                    'Fecha Captura'     => $limpiarTexto($linea['Fecha Captura'] ?? ''),
                    'Cliente/Proveedor' => $limpiarTexto($linea['Cliente/Proveedor'] ?? ''),
                    'Transacción'       => $limpiarTexto($linea['Transacción'] ?? ''),
                    'Depósito'          => $limpiarTexto($linea['Depósito'] ?? ''),
                    'Forma de Pago'     => $limpiarTexto($linea['Forma de Pago'] ?? ''),
                    'Referencia'        => $limpiarTexto($linea['Referencia'] ?? ''),
                    'Concepto'          => $limpiarTexto($linea['Concepto'] ?? '')
                ];
            });
        });

        // Seguro anti-corrupción XML
        if (empty($listaTransacciones)) {
            return response()->json([
                'error' => "El archivo no contiene datos válidos o las columnas no coinciden con el formato del banco."
            ], 404);
        }

        // 2. Configuración de estilos Avanzados (Negrita + Bordes Perimetrales + Evitar Ajuste)
        $colorNegro = \OpenSpout\Common\Entity\Style\Color::BLACK;
        $grosorLinea = \OpenSpout\Common\Entity\Style\Border::WIDTH_THIN;
        $estiloSolido = \OpenSpout\Common\Entity\Style\Border::STYLE_SOLID;

        // Instanciamos el marco celda por celda (Abajo, Arriba, Izquierda, Derecha)
        $bordePerimetral = new \OpenSpout\Common\Entity\Style\Border(
            new \OpenSpout\Common\Entity\Style\BorderPart(\OpenSpout\Common\Entity\Style\Border::BOTTOM, $colorNegro, $grosorLinea, $estiloSolido),
            new \OpenSpout\Common\Entity\Style\BorderPart(\OpenSpout\Common\Entity\Style\Border::TOP, $colorNegro, $grosorLinea, $estiloSolido),
            new \OpenSpout\Common\Entity\Style\BorderPart(\OpenSpout\Common\Entity\Style\Border::LEFT, $colorNegro, $grosorLinea, $estiloSolido),
            new \OpenSpout\Common\Entity\Style\BorderPart(\OpenSpout\Common\Entity\Style\Border::RIGHT, $colorNegro, $grosorLinea, $estiloSolido)
        );

        // Inyectamos el borde, la negrita y apagamos explícitamente el "wrap text"
        $estiloEncabezado = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold()
            ->setBorder($bordePerimetral)
            ->setShouldWrapText(false);

        // Configuramos un estilo extra para las filas de datos, asegurando alturas estándar
        $estiloFilas = (new \OpenSpout\Common\Entity\Style\Style())
            ->setShouldWrapText(false);

        $fecha = date('d-m-y');
        return (new FastExcel(collect($listaTransacciones)))
            ->headerStyle($estiloEncabezado)
            ->rowsStyle($estiloFilas)
            ->download("TRANSACCIONES-BANCARIAS-$fecha.xlsx");
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