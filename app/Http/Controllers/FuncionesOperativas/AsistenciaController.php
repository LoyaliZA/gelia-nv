<?php

namespace App\Http\Controllers\FuncionesOperativas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;
use Carbon\Carbon;
use Inertia\Inertia;

class AsistenciaController extends Controller
{
    public function index()
    {
        return Inertia::render('FuncionesOperativas/Asistencia');
    }

    public function procesar(Request $request)
    {
        // 1. Validación estricta para evitar el error de OpenSpout
        $validator = Validator::make($request->all(), [
            'archivo_asistencia' => 'required|file|mimes:xlsx,csv' 
        ], [
            'archivo_asistencia.mimes' => 'Por favor, abre tu archivo .xls y dale a "Guardar como -> Libro de Excel (.xlsx)" antes de subirlo. El sistema no soporta formatos antiguos.'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $mesNombre = 'Mes';
        $año = date('Y');
        $mesNumero = date('m');
        $dataExportar = [];

        // 2. Procesamiento Seguro del Archivo
        $this->procesarArchivoSeguro($request->file('archivo_asistencia'), function ($ruta) use (&$dataExportar, &$mesNombre, &$año, &$mesNumero) {
            
            $leyendoEmpleados = false;
            $indices = [];

            (new FastExcel)->withoutHeaders()->import($ruta, function ($fila) use (&$dataExportar, &$leyendoEmpleados, &$indices, &$mesNombre, &$año, &$mesNumero) {
                
                $celdas = array_values($fila);

                // A) Buscar el mes y año
                if (isset($celdas[0]) && is_string($celdas[0]) && str_contains($celdas[0], 'Made Date:')) {
                    if (preg_match('/\d{4}\/\d{2}\/\d{2}/', $celdas[0], $matches)) {
                        $fechaStr = str_replace('/', '-', $matches[0]);
                        $fecha = Carbon::parse($fechaStr);
                        $mesNombre = $fecha->translatedFormat('F');
                        $año = $fecha->format('Y');
                        $mesNumero = $fecha->format('m');
                    }
                    return;
                }

                // B) Identificar encabezados dinámicos
                if (!$leyendoEmpleados && in_array('Name', $celdas)) {
                    foreach ($celdas as $index => $valor) {
                        $valor = trim((string)$valor);
                        if ($valor === 'Name') $indices['nombre'] = $index;
                        if ($valor === 'Department') $indices['departamento'] = $index;
                        if (is_numeric($valor)) {
                            $indices['dias'][$valor] = $index; 
                        }
                    }
                    $leyendoEmpleados = true;
                    return;
                }

                // C) Procesar Empleados
                if ($leyendoEmpleados) {
                    $nombre = $celdas[$indices['nombre']] ?? null;
                    $departamento = $celdas[$indices['departamento']] ?? null;

                    if (empty(trim((string)$nombre)) || str_contains(strtolower($nombre), 'administrador')) {
                        return;
                    }

                    foreach ($indices['dias'] as $diaNumero => $colIndex) {
                        $registroDia = $celdas[$colIndex] ?? '';
                        $entrada = '';
                        $salida = '';

                        if (!empty(trim((string)$registroDia))) {
                            $horas = array_map('trim', explode("\n", trim($registroDia)));
                            $horas = array_filter($horas);
                            $horas = array_values($horas);

                            $entrada = $horas[0] ?? 'Sin registro';
                            $salida = $horas[1] ?? 'Sin registro';
                        }

                        $fechaAsistencia = sprintf('%04d-%02d-%02d', $año, $mesNumero, $diaNumero);

                        $dataExportar[] = [
                            'Fecha de la asistencia' => Carbon::parse($fechaAsistencia)->format('d/m/Y'),
                            'Nombre' => $nombre,
                            'Departamento' => $departamento,
                            'Hora de entrada' => $entrada === '' ? 'Falta' : $entrada,
                            'Hora de salida'  => $salida === '' ? 'Falta' : $salida,
                        ];
                    }
                }
            });
        });

        // 3. Generar y descargar
        $nombreArchivoFinal = 'Asistencia-' . ucfirst($mesNombre) . '-' . $año . '.xlsx';
        return (new FastExcel(collect($dataExportar)))->download($nombreArchivoFinal);
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
