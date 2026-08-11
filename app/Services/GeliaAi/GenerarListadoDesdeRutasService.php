<?php

namespace App\Services\GeliaAi;

use App\Models\CustomList;
use App\Services\Listados\ExportarListadoExcelService;
use App\Services\Listados\PorcentajesListadoService;
use Rap2hpoutre\FastExcel\FastExcel;
use RuntimeException;

/**
 * Motor de listados desde rutas absolutas (sin UploadedFile/move).
 * Misma lógica de cruce que AromasListasController::generar.
 */
class GenerarListadoDesdeRutasService
{
    /** @var array<string, list<string>> */
    public const COLUMNAS_DEFAULT = [
        'resurtido' => ['Folio', 'SKU', 'Descripcion', 'Existencia', 'PG', 'Plataformas', 'Bronce'],
        'costos' => ['Almacen', 'SKU', 'Descripcion', 'Existencia', 'CostoWizerp'],
        'actualizada' => ['Folio', 'SKU', 'Descripcion', 'Existencia', 'CostoCalculado', 'Plataformas'],
        'inventario' => ['Folio', 'SKU', 'Descripcion', 'Existencia', 'PG', 'Bronce'],
        'venta_especial' => ['Folio', 'SKU', 'Descripcion', 'Existencia', 'PG', 'VentaEspecial'],
        'meli' => ['Folio', 'SKU', 'Descripcion', 'Existencia', 'CostoFull', 'CostoMSI'],
    ];

    public function __construct(
        private PorcentajesListadoService $porcentajes,
        private ExportarListadoExcelService $exportarExcel,
    ) {}

    /**
     * @param  array{existencias: string, precios?: string|null, costos?: string|null}  $rutas
     * @param  array{nota_encabezado?: string|null, mostrar_nota_encabezado?: bool|null}  $opciones
     * @return array{nombre_descarga: string, temp_file: string, temp_path: string, filas: int, inconsistencias: list<array<string, mixed>>}
     */
    public function generar(string|int $tipoLista, array $rutas, array $opciones = []): array
    {
        if (empty($rutas['existencias']) || ! is_file($rutas['existencias'])) {
            throw new RuntimeException('Archivo de existencias requerido.');
        }

        $tipoLista = $this->normalizarTipoLista($tipoLista);

        $multiplicadores = $this->porcentajes->obtenerMultiplicadores();
        $fecha = date('d-m-y');
        $esListaPersonalizadaBD = is_numeric($tipoLista);
        $configuracionBD = null;

        if ($esListaPersonalizadaBD) {
            $configuracionBD = CustomList::find($tipoLista);
            if (! $configuracionBD) {
                throw new RuntimeException('Lista personalizada no encontrada.');
            }
            $nombreArchivo = $configuracionBD->nombre_archivo_salida."-{$fecha}.xlsx";
            $columnasSeleccionadas = $configuracionBD->columnas_exportar;
            $reqs = $configuracionBD->archivos_requeridos ?? [];
            if (in_array('precios', $reqs, true) && empty($rutas['precios'])) {
                throw new RuntimeException('Esta lista requiere archivo de precios.');
            }
            if (in_array('costos', $reqs, true) && empty($rutas['costos'])) {
                throw new RuntimeException('Esta lista requiere archivo de costos.');
            }
        } else {
            $tipo = (string) $tipoLista;
            if (! isset(self::COLUMNAS_DEFAULT[$tipo])) {
                throw new RuntimeException(
                    'Tipo de lista no reconocido: '.$tipo.'. Usa: '.implode(', ', array_keys(self::COLUMNAS_DEFAULT))
                );
            }
            $nombreArchivo = match ($tipo) {
                'resurtido' => "LISTA-DE-RESURTIDO-{$fecha}.xlsx",
                'costos' => "LISTA-DE-COSTOS-{$fecha}.xlsx",
                'actualizada' => "LISTA-ACTUALIZADA-{$fecha}.xlsx",
                'inventario' => "LISTA-DE-INVENTARIO-{$fecha}.xlsx",
                'venta_especial' => "VENTA-ESPECIAL-0+-{$fecha}.xlsx",
                'meli' => "LISTA-MELI-{$fecha}.xlsx",
            };
            $columnasSeleccionadas = self::COLUMNAS_DEFAULT[$tipo];
        }

        $diccionarioPrecios = [];
        if (! empty($rutas['precios']) && is_file($rutas['precios'])) {
            (new FastExcel)->withoutHeaders()->import($rutas['precios'], function ($linea) use (&$diccionarioPrecios) {
                if (! isset($linea[1]) || $linea[1] == 'CODIGO_DEL_PRODUCTO' || $linea[1] == '') {
                    return;
                }
                $sku = ltrim(trim((string) $linea[1]), '0');
                $precio = $linea[7] ?? 0;
                $diccionarioPrecios[$sku] = is_numeric($precio) ? (float) $precio : 0.0;
            });
        }

        $diccionarioCostosWizerp = [];
        if (! empty($rutas['costos']) && is_file($rutas['costos'])) {
            (new FastExcel)->withoutHeaders()->import($rutas['costos'], function ($linea) use (&$diccionarioCostosWizerp) {
                if (! isset($linea[1]) || $linea[1] == 'SKU' || $linea[1] == '') {
                    return;
                }
                $sku = ltrim(trim((string) $linea[1]), '0');
                $costo = $linea[5] ?? 0;
                $costoLimpio = str_replace(['$', ','], '', (string) $costo);
                $diccionarioCostosWizerp[$sku] = is_numeric($costoLimpio) ? (float) $costoLimpio : 0.0;
            });
        }

        $listaCompleta = [];
        $inconsistencias = [];
        $tienePrecios = ! empty($rutas['precios']) && is_file($rutas['precios']);

        (new FastExcel)->withoutHeaders()->import($rutas['existencias'], function ($linea) use (
            &$listaCompleta,
            &$inconsistencias,
            $diccionarioPrecios,
            $diccionarioCostosWizerp,
            $columnasSeleccionadas,
            $esListaPersonalizadaBD,
            $configuracionBD,
            $multiplicadores,
            $tienePrecios,
        ) {
            if (! isset($linea[4]) || $linea[4] == 'Código') {
                return;
            }

            $skuCrudo = trim((string) $linea[4]);
            if ($skuCrudo === '') {
                return;
            }
            $skuBuscador = ltrim($skuCrudo, '0');

            $existenciaRaw = $linea[10] ?? 0;
            $existencia = is_numeric($existenciaRaw) ? (int) $existenciaRaw : 0;

            if ($esListaPersonalizadaBD && $configuracionBD->solo_con_existencia && $existencia <= 0) {
                return;
            }

            $almacen = $linea[1] ?? '';
            $folio = $linea[3] ?? '';
            $descripcion = $linea[5] ?? '';
            $marca = $linea[6] ?? '';

            if ($esListaPersonalizadaBD && $configuracionBD->filtro_relojes) {
                $primeraLetra = strtoupper(substr(ltrim((string) $descripcion), 0, 1));
                if ($primeraLetra !== 'R') {
                    return;
                }
            }

            $pg = $diccionarioPrecios[$skuBuscador] ?? 0.0;
            $costoWizerp = $diccionarioCostosWizerp[$skuBuscador] ?? 0.0;

            if ($tienePrecios && $existencia > 0 && $pg <= 0) {
                $inconsistencias[] = [
                    'sku' => $skuCrudo,
                    'descripcion' => $descripcion,
                    'almacen' => $almacen,
                    'existencia' => $existencia,
                ];
            }

            $fila = [];
            foreach ($columnasSeleccionadas as $columna) {
                switch ($columna) {
                    case 'Folio':
                        $fila['Folio'] = is_numeric($folio) ? $folio * 1 : $folio;
                        break;
                    case 'SKU':
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
                    case 'Plataformas':
                        $fila['Plataformas'] = round($pg * $multiplicadores['plataformas'], 2);
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
                    case 'CostoFull': {
                        $plataformas = $pg * $multiplicadores['plataformas'];
                        $fila['Costo Full'] = round(PorcentajesListadoService::calcularCostoMeli(
                            $plataformas,
                            $multiplicadores['meli_factor_base'],
                            $multiplicadores['meli_full_multiplicador'],
                            $multiplicadores['meli_full_fijo_1'],
                            $multiplicadores['meli_full_fijo_2']
                        ), 2);
                        break;
                    }
                    case 'CostoMSI': {
                        $plataformas = $pg * $multiplicadores['plataformas'];
                        $fila['Costo MSI'] = round(PorcentajesListadoService::calcularCostoMeli(
                            $plataformas,
                            $multiplicadores['meli_factor_base'],
                            $multiplicadores['meli_msi_multiplicador'],
                            $multiplicadores['meli_msi_fijo_1'],
                            $multiplicadores['meli_msi_fijo_2']
                        ), 2);
                        break;
                    }
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

        if (in_array('Descripcion', $columnasSeleccionadas, true) && $listaCompleta !== []) {
            $descripciones = array_column($listaCompleta, 'Descripcion');
            array_multisort($descripciones, SORT_ASC, SORT_STRING | SORT_FLAG_CASE, $listaCompleta);
        }

        $tempFilename = 'excel_temp_'.uniqid().'.xlsx';
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir.'/'.$tempFilename;

        $nota = $this->exportarExcel->resolverNota(
            array_key_exists('mostrar_nota_encabezado', $opciones)
                ? (bool) $opciones['mostrar_nota_encabezado']
                : null,
            $opciones['nota_encabezado'] ?? null,
            $esListaPersonalizadaBD ? $configuracionBD : null,
        );
        $this->exportarExcel->exportar($listaCompleta, $tempPath, $nota);

        return [
            'nombre_descarga' => $nombreArchivo,
            'temp_file' => $tempFilename,
            'temp_path' => $tempPath,
            'filas' => count($listaCompleta),
            'inconsistencias' => array_slice($inconsistencias, 0, 50),
        ];
    }

    /**
     * Normaliza aliases del LLM (MELI, Lista MELI, etc.) a claves internas.
     */
    public function normalizarTipoLista(string|int $tipoLista): string|int
    {
        if (is_numeric($tipoLista)) {
            return (int) $tipoLista;
        }

        $t = mb_strtolower(trim((string) $tipoLista));
        $t = str_replace(['-', ' '], '_', $t);
        $t = preg_replace('/_+/', '_', $t) ?? $t;

        $aliases = [
            'meli' => 'meli',
            'lista_meli' => 'meli',
            'listameli' => 'meli',
            'mercadolibre' => 'meli',
            'mercado_libre' => 'meli',
            'resurtido' => 'resurtido',
            'lista_resurtido' => 'resurtido',
            'costos' => 'costos',
            'lista_costos' => 'costos',
            'actualizada' => 'actualizada',
            'lista_actualizada' => 'actualizada',
            'inventario' => 'inventario',
            'lista_inventario' => 'inventario',
            'venta_especial' => 'venta_especial',
            'ventaespecial' => 'venta_especial',
        ];

        return $aliases[$t] ?? $t;
    }
}
