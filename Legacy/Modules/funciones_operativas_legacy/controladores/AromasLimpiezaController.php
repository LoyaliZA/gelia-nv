<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LimpiezaCsvService;
use Rap2hpoutre\FastExcel\FastExcel;

class AromasLimpiezaController extends Controller
{
    protected $limpiezaService;

    public function __construct(LimpiezaCsvService $limpiezaService)
    {
        $this->limpiezaService = $limpiezaService;
    }

    public function index()
    {
        return view('aromas.limpieza');
    }

    public function procesar(Request $request)
    {
        $request->validate([
            'archivo_sucio' => 'required|file|mimes:csv,txt,xlsx,xls'
        ], [
            'archivo_sucio.required' => 'Debes seleccionar un archivo.',
            'archivo_sucio.mimes' => 'El archivo debe ser de tipo CSV o Excel.'
        ]);

        try {
            $nombreTemp = 'temp_limpieza_' . uniqid() . '.' . $request->file('archivo_sucio')->getClientOriginalExtension();
            $request->file('archivo_sucio')->move(sys_get_temp_dir(), $nombreTemp);
            $rutaArchivo = sys_get_temp_dir() . '/' . $nombreTemp;
            
            // Ejecutamos la lógica aislada en el servicio
            $datosLimpios = $this->limpiezaService->procesarYLimpiar($rutaArchivo);
            
            // Eliminamos el archivo temporal
            if (file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }
            
            $nombreArchivo = 'PRODUCTOS-LIMPIOS-' . date('d-m-Y') . '.xlsx';
            
            // Exportamos y descargamos directamente
            return (new FastExcel($datosLimpios))->download($nombreArchivo);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar el archivo: ' . $e->getMessage()], 500);
        }
    }
}