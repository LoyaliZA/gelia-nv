<?php

namespace App\Http\Controllers\FuncionesOperativas;

use App\Http\Controllers\Controller;
use App\Services\FuncionesOperativas\CruceAvisoMercanciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class AvisosController extends Controller
{
    public function index()
    {
        return Inertia::render('FuncionesOperativas/Avisos');
    }

    public function procesar(Request $request, CruceAvisoMercanciaService $cruce)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $validator = Validator::make($request->all(), [
            'orden_compra' => 'required|file',
            'aviso_mercancia' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach (['orden_compra', 'aviso_mercancia'] as $campo) {
            $ext = strtolower($request->file($campo)->getClientOriginalExtension());
            if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
                return response()->json([
                    'errors' => [$campo => ['El archivo debe ser Excel o CSV.']],
                ], 422);
            }
        }

        $resultado = ['resultados' => [], 'avisos' => 0, 'compra' => 0];

        $this->procesarArchivoSeguro($request->file('aviso_mercancia'), function ($rutaAviso) use ($request, $cruce, &$resultado) {
            $this->procesarArchivoSeguro($request->file('orden_compra'), function ($rutaCompra) use ($cruce, &$resultado, $rutaAviso) {
                $resultado = $cruce->cruzar($rutaAviso, $rutaCompra);
            });
        });

        if (empty($resultado['resultados'])) {
            return response()->json([
                'error' => 'El cruce finalizó, pero no se encontraron coincidencias de mercancía que haya llegado físicamente en la Orden de Compra.',
                'meta' => [
                    'avisos_cargados' => $resultado['avisos'],
                    'filas_compra' => $resultado['compra'],
                ],
            ], 404);
        }

        Log::info('AROMAS - Cruce de Aviso de Mercancía generado exitosamente.', [
            'coincidencias' => count($resultado['resultados']),
            'avisos' => $resultado['avisos'],
        ]);

        if ($request->boolean('descargar')) {
            $estiloEncabezado = (new \OpenSpout\Common\Entity\Style\Style())->setFontBold();
            $fecha = date('d-m-y');

            return (new FastExcel(collect($resultado['resultados'])))
                ->headerStyle($estiloEncabezado)
                ->download("AVISO-MERCANCIA-CRUZADO-$fecha.xlsx");
        }

        return response()->json([
            'success' => true,
            'data' => $resultado['resultados'],
            'count' => count($resultado['resultados']),
            'meta' => [
                'avisos_cargados' => $resultado['avisos'],
                'filas_compra' => $resultado['compra'],
            ],
        ]);
    }

    private function procesarArchivoSeguro($archivo, callable $callbackLogica): void
    {
        if (! $archivo) {
            return;
        }

        $nombreTemp = 'temp_'.uniqid().'.'.$archivo->getClientOriginalExtension();
        $rutaCompleta = sys_get_temp_dir().'/'.$nombreTemp;
        $archivo->move(sys_get_temp_dir(), $nombreTemp);
        try {
            $callbackLogica($rutaCompleta);
        } finally {
            if (file_exists($rutaCompleta)) {
                unlink($rutaCompleta);
            }
        }
    }
}
