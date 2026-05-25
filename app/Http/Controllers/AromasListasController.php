<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\CustomList;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\Listados\PorcentajesListadoService;
use Inertia\Inertia;
use Exception;

class AromasListasController extends Controller
{

    // ══════════════════════════════════════════════════════════════════════
    // 0. RENDERIZADO DE LA VISTA PRINCIPAL (SPA)
    // ══════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // Obtenemos solo las listas del usuario o las compartidas con él
        $listasPersonalizadas = CustomList::with('sharedUsers')
            ->where('active', true)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId) // Es el dueño
                    ->orWhereHas('sharedUsers', function ($q) use ($userId) {
                        $q->where('users.id', $userId); // Se la compartieron
                    });
            })->get();

        $configuracionListados = DB::table('gelia_settings')->pluck('value', 'key');

        // Usuarios disponibles para compartir (excluyendo al usuario actual)
        $usuariosSistema = User::select('id', 'name')
            ->where('id', '!=', $userId)
            ->get();

        return Inertia::render('FuncionesOperativas/Listados', [
            'listas_personalizadas' => $listasPersonalizadas,
            'configuracion_listados' => $configuracionListados,
            'usuarios_sistema' => $usuariosSistema
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 1. GESTIÓN DE PLANTILLAS Y PRIVACIDAD
    // ══════════════════════════════════════════════════════════════════════

    public function guardarLista(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo_lista' => 'required|string|max:50',
            'descripcion' => 'nullable|string',
            'color' => 'required|string',
            'icono_personalizado' => 'required|string', // <-- AGREGAR ESTA LÍNEA
            'archivos_requeridos' => 'required|array',
            'columnas_exportar' => 'required|array',
            'nombre_archivo_salida' => 'required|string|max:50',
            'shared_users' => 'nullable|array'
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        try {
            $lista = CustomList::create([
                'user_id' => $request->user()->id,
                'nombre_creador' => $request->user()->name,
                'titulo_lista' => $request->titulo_lista,
                'descripcion' => $request->descripcion,
                'color' => $request->color,
                'icono_personalizado' => $request->icono_personalizado, // <-- AGREGAR ESTA LÍNEA
                'archivos_requeridos' => $request->archivos_requeridos,
                'columnas_exportar' => $request->columnas_exportar,
                'nombre_archivo_salida' => strtoupper($request->nombre_archivo_salida),
                'solo_con_existencia' => $request->boolean('solo_con_existencia'),
                'filtro_relojes' => $request->boolean('filtro_relojes'),
                'active' => true
            ]);

            // Sincronizar usuarios compartidos
            if ($request->has('shared_users')) {
                $lista->sharedUsers()->sync($request->shared_users);
            }

            Log::info("AROMAS - Nueva lista creada: '{$lista->titulo_lista}'");
            return response()->json(['message' => 'Lista creada con éxito']);
        } catch (Exception $e) {
            Log::error("Error al guardar lista: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo crear la lista.'], 500);
        }
    }

    public function actualizarLista(Request $request, $id)
    {
        $lista = CustomList::find($id);

        if (!$lista) return response()->json(['error' => 'Lista no encontrada'], 404);

        // Validación de Propiedad
        if ($lista->user_id !== $request->user()->id && !$request->user()->hasRole('Super Admin')) {
            return response()->json(['error' => 'No tienes permisos para editar esta lista'], 403);
        }

        $validator = Validator::make($request->all(), [
            'titulo_lista' => 'required|string|max:50',
            'color' => 'required|string',
            'icono_personalizado' => 'required|string', // <-- AGREGAR ESTA LÍNEA
            'archivos_requeridos' => 'required|array',
            'columnas_exportar' => 'required|array',
            'nombre_archivo_salida' => 'required|string|max:50',
            'shared_users' => 'nullable|array'
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        try {
            $lista->update([
                'titulo_lista' => $request->titulo_lista,
                'descripcion' => $request->descripcion,
                'color' => $request->color,
                'icono_personalizado' => $request->icono_personalizado, // <-- AGREGAR ESTA LÍNEA
                'archivos_requeridos' => $request->archivos_requeridos,
                'columnas_exportar' => $request->columnas_exportar,
                'nombre_archivo_salida' => strtoupper($request->nombre_archivo_salida),
                'solo_con_existencia' => $request->boolean('solo_con_existencia'),
                'filtro_relojes' => $request->boolean('filtro_relojes'),
            ]);

            $lista->sharedUsers()->sync($request->shared_users ?? []);

            return response()->json(['message' => 'Lista actualizada con éxito']);
        } catch (Exception $e) {
            return response()->json(['error' => 'No se pudo actualizar la lista.'], 500);
        }
    }

    public function eliminarLista(Request $request, $id)
    {
        $lista = CustomList::find($id);

        if (!$lista) return response()->json(['error' => 'Lista no encontrada.'], 404);

        if ($lista->user_id !== $request->user()->id && !$request->user()->hasRole('Super Admin')) {
            return response()->json(['error' => 'No tienes permisos para eliminar esta lista'], 403);
        }

        try {
            $lista->active = false;
            $lista->save();
            return response()->json(['message' => 'Lista eliminada correctamente.']);
        } catch (Exception $e) {
            return response()->json(['error' => 'No se pudo procesar la baja de la lista.'], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // 2. MOTOR PROCESADOR Y CRUCE DE INVENTARIOS
    // ══════════════════════════════════════════════════════════════════════

    public function generar(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $multiplicadores = app(PorcentajesListadoService::class)->obtenerMultiplicadores();

        $tipoLista = $request->input('tipo_lista', 'PERSONALIZADA');
        $fecha = date('d-m-y');
        $esListaPersonalizadaBD = is_numeric($tipoLista);
        $configuracionBD = null;

        if ($esListaPersonalizadaBD) {
            $configuracionBD = CustomList::find($tipoLista);
            if (!$configuracionBD) return response()->json(['error' => 'Lista no encontrada'], 404);

            $nombreArchivo = $configuracionBD->nombre_archivo_salida . "-$fecha.xlsx";
            $columnasSeleccionadas = $configuracionBD->columnas_exportar;
        } else {
            $ordenCadena = $request->input('orden_final');

            switch ($tipoLista) {
                case 'resurtido':
                    $nombreArchivo = "LISTA-DE-RESURTIDO-$fecha.xlsx";
                    break;
                case 'costos':
                    $nombreArchivo = "LISTA-DE-COSTOS-$fecha.xlsx";
                    break;
                case 'actualizada':
                    $nombreArchivo = "LISTA-ACTUALIZADA-$fecha.xlsx";
                    break;
                case 'inventario':
                    $nombreArchivo = "LISTA-DE-INVENTARIO-$fecha.xlsx";
                    break;
                case 'venta_especial':
                    $nombreArchivo = "VENTA-ESPECIAL-0+-$fecha.xlsx";
                    break;
                default:
                    $nombreArchivo = "LISTA-PERSONALIZADA-$fecha.xlsx";
                    break;
            }

            if (!empty($ordenCadena)) {
                $columnasSeleccionadas = explode(',', $ordenCadena);
            } elseif ($request->has('columnas')) {
                $columnasSeleccionadas = $request->input('columnas');
            } else {
                return response()->json(['error' => 'Debes seleccionar columnas.'], 422);
            }
        }

        $rules = ['existencias' => 'required|file'];
        $messages = [];

        if ($esListaPersonalizadaBD) {
            $reqs = $configuracionBD->archivos_requeridos;
            if (in_array('precios', $reqs)) $rules['precios'] = 'required|file';
            if (in_array('costos', $reqs)) $rules['costos'] = 'required|file';
        } else {
            $rules['precios'] = 'nullable|file|required_without:costos';
            $rules['costos'] = 'nullable|file|required_without:precios';
            $messages['precios.required_without'] = 'Debes subir Precios o Costos.';
            $messages['costos.required_without'] = 'Debes subir Precios o Costos.';
        }

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $diccionarioPrecios = [];
        if ($request->hasFile('precios')) {
            $this->procesarArchivoSeguro($request->file('precios'), function ($ruta) use (&$diccionarioPrecios) {
                (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$diccionarioPrecios) {
                    if (!isset($linea[1]) || $linea[1] == 'CODIGO_DEL_PRODUCTO' || $linea[1] == '') return;
                    $sku = ltrim(trim((string)$linea[1]), '0');
                    $precio = $linea[7] ?? 0;
                    $diccionarioPrecios[$sku] = is_numeric($precio) ? (float)$precio : 0.0;
                });
            });
        }

        $diccionarioCostosWizerp = [];
        if ($request->hasFile('costos')) {
            $this->procesarArchivoSeguro($request->file('costos'), function ($ruta) use (&$diccionarioCostosWizerp) {
                (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$diccionarioCostosWizerp) {
                    if (!isset($linea[1]) || $linea[1] == 'SKU' || $linea[1] == '') return;
                    $sku = ltrim(trim((string)$linea[1]), '0');
                    $costo = $linea[5] ?? 0;
                    $costoLimpio = str_replace(['$', ','], '', (string)$costo);
                    $diccionarioCostosWizerp[$sku] = is_numeric($costoLimpio) ? (float)$costoLimpio : 0.0;
                });
            });
        }

        $listaCompleta = [];
        $inconsistencias = [];
        $tienePrecios = $request->hasFile('precios');

        $this->procesarArchivoSeguro($request->file('existencias'), function ($ruta) use (&$listaCompleta, &$inconsistencias, $diccionarioPrecios, $diccionarioCostosWizerp, $columnasSeleccionadas, $esListaPersonalizadaBD, $configuracionBD, $multiplicadores, $tienePrecios) {

            (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$listaCompleta, &$inconsistencias, $diccionarioPrecios, $diccionarioCostosWizerp, $columnasSeleccionadas, $esListaPersonalizadaBD, $configuracionBD, $multiplicadores, $tienePrecios) {
                if (!isset($linea[4]) || $linea[4] == 'Código') return;

                $skuCrudo = trim((string)$linea[4]);
                if ($skuCrudo === '') return;
                $skuBuscador = ltrim($skuCrudo, '0');

                $existenciaRaw = $linea[10] ?? 0;
                $existencia = is_numeric($existenciaRaw) ? (int)$existenciaRaw : 0;

                if ($esListaPersonalizadaBD && $configuracionBD->solo_con_existencia) {
                    if ($existencia <= 0) return;
                }

                $almacen = $linea[1] ?? '';
                $folio = $linea[3] ?? '';
                $descripcion = $linea[5] ?? '';
                $marca = $linea[6] ?? '';

                if ($esListaPersonalizadaBD && $configuracionBD->filtro_relojes) {
                    $primeraLetra = strtoupper(substr(ltrim($descripcion), 0, 1));
                    if ($primeraLetra !== 'R') return;
                }

                $pg = $diccionarioPrecios[$skuBuscador] ?? 0.0;
                $costoWizerp = $diccionarioCostosWizerp[$skuBuscador] ?? 0.0;

                if ($tienePrecios && $existencia > 0 && $pg <= 0) {
                    $inconsistencias[] = [
                        'sku' => $skuCrudo,
                        'descripcion' => $descripcion,
                        'almacen' => $almacen,
                        'existencia' => $existencia
                    ];
                }

                $fila = [];
                foreach ($columnasSeleccionadas as $columna) {
                    switch ($columna) {
                        case 'Folio':
                            // Si es numérico, lo multiplicamos por 1 para forzar el casteo a int/float sin perder datos.
                            // Si tiene letras (ej. "FOL-123"), se queda como string.
                            $fila['Folio'] = is_numeric($folio) ? $folio * 1 : $folio;
                            break;
                        case 'SKU':
                            // Hacemos lo mismo para el SKUCrudo
                            $fila['SKU'] = is_numeric($skuCrudo) ? $skuCrudo * 1 : $skuCrudo;
                            break;
                        case 'Descripcion':
                            $fila['Descripcion'] = $descripcion;
                            break;
                        case 'Existencia':
                            $fila['Existencia'] = $existencia;
                            break;
                        case 'PG':
                            $fila['PG'] = round($pg, 2);
                            break;
                        case 'Bronce':
                            $fila['Bronce'] = round($pg * $multiplicadores['bronce'], 2);
                            break;
                        case 'Plata':
                            $fila['Plata'] = round($pg * $multiplicadores['plata'], 2);
                            break;
                        case 'Oro':
                            $fila['Oro'] = round($pg * $multiplicadores['oro'], 2);
                            break;
                        case 'Diamante':
                            $fila['Diamante'] = round($pg * $multiplicadores['diamante'], 2);
                            break;
                        case 'Lista3':
                            $fila['Lista3'] = round($pg * $multiplicadores['lista3'], 2);
                            break;
                        case 'Lista4':
                            $fila['Lista4'] = round($pg * $multiplicadores['lista4'], 2);
                            break;
                        case 'VentaEspecial':
                            $fila['Venta Especial'] = round($pg * $multiplicadores['venta_especial'], 2);
                            break;
                        case 'ListaBoutique':
                            $fila['Lista Boutique'] = round($pg * $multiplicadores['boutique'], 2);
                            break;
                        case 'Plataformas':
                            $fila['Plataformas'] = round($pg * $multiplicadores['plataformas'], 2);
                            break;
                        case 'CostoWizerp':
                            $fila['Costo (Wizerp)'] = round($costoWizerp, 2);
                            break;
                        case 'CostoCalculado':
                            $fila['Costo (Calculado)'] = round($pg > 0 ? $pg / $multiplicadores['divisor_costo'] : 0.0, 2);
                            break;
                        case 'Almacen':
                            $fila['Almacen'] = $almacen;
                            break;
                        case 'Marca':
                            $fila['Marca'] = $marca;
                            break;
                    }
                }
                $listaCompleta[] = $fila;
            });
        });

        if (in_array('Descripcion', $columnasSeleccionadas) && !empty($listaCompleta)) {
            $descripciones = array_column($listaCompleta, 'Descripcion');
            array_multisort($descripciones, SORT_ASC, SORT_STRING | SORT_FLAG_CASE, $listaCompleta);
        }

        if (count($inconsistencias) > 0) {
            $tempFilename = 'excel_temp_' . uniqid() . '.xlsx';
            $tempDir = storage_path('app/temp');

            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = $tempDir . '/' . $tempFilename;
            (new FastExcel($listaCompleta))->export($tempPath);

            return response()->json([
                'requiere_confirmacion' => true,
                'inconsistencias' => $inconsistencias,
                'temp_file' => $tempFilename,
                'nombre_descarga' => $nombreArchivo
            ]);
        }

        return (new FastExcel($listaCompleta))->download($nombreArchivo);
    }

    public function descargarTemporal(Request $request)
    {
        $request->validate([
            'temp_file' => ['required', 'string', 'regex:/^excel_temp_[a-zA-Z0-9]+\.xlsx$/'],
            'nombre_descarga' => 'required|string'
        ]);

        $path = storage_path('app/temp/' . $request->temp_file);

        if (!file_exists($path)) {
            return response()->json(['error' => 'El archivo temporal ha expirado o ya fue procesado.'], 404);
        }

        return response()->download($path, $request->nombre_descarga)->deleteFileAfterSend(true);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 3. CONFIGURACIONES GLOBALES DE PORCENTAJES
    // ══════════════════════════════════════════════════════════════════════

    public function obtenerConfiguracion()
    {
        $settings = DB::table('gelia_settings')->pluck('value', 'key');
        return response()->json($settings);
    }

    public function guardarConfiguracion(Request $request)
    {
        try {
            $configuraciones = $request->except('_token');

            foreach ($configuraciones as $key => $value) {
                DB::table('gelia_settings')->updateOrInsert(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'created_at' => DB::raw('IFNULL(created_at, NOW())'),
                        'updated_at' => now()
                    ]
                );
            }

            return response()->json(['message' => 'Configuración global actualizada con éxito']);
        } catch (Exception $e) {
            Log::error("Error al guardar configuración global de listados: " . $e->getMessage());
            return response()->json(['error' => 'Fallo al sincronizar los parámetros globales.'], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // 4. AUXILIARES DE SISTEMA
    // ══════════════════════════════════════════════════════════════════════

    private function procesarArchivoSeguro($archivo, callable $callbackLogica)
    {
        if (!$archivo) return;
        $nombreTemp = 'temp_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
        $rutaCompleta = sys_get_temp_dir() . '/' . $nombreTemp;
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
