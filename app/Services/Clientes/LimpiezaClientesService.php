<?php

namespace App\Services\Clientes;

use Rap2hpoutre\FastExcel\FastExcel;

class LimpiezaClientesService
{
    public function procesar($archivo, array $columnasSeleccionadas, bool $incluirSinId, ?string $orden, bool $filtroEspecial)
    {
        $listaCompleta = [];

        $this->procesarArchivoSeguro($archivo, function ($ruta) use (&$listaCompleta, $incluirSinId, $filtroEspecial) {
            (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$listaCompleta, $incluirSinId, $filtroEspecial) {
                
                $idRaw = $linea[0] ?? '';
                if ($idRaw === 'Clientes' || $idRaw === 'ID' || $idRaw === '') {
                    if (!$incluirSinId || $idRaw === 'Clientes' || $idRaw === 'ID') return;
                }

                $id = ltrim(trim((string)$idRaw), '0');
                if (!$incluirSinId && $id === '') return;

                $limpiarTexto = function ($texto) {
                    $texto = trim(preg_replace('/\s+/', ' ', (string)($texto ?? '')));
                    return mb_check_encoding($texto, 'UTF-8') ? $texto : mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
                };

                // Recuperación dinámica de columnas desbordadas
                $totalCols = count($linea);
                $tagsRaw = $totalCols > 28 ? implode(', ', array_slice($linea, 26, $totalCols - 27)) : ($linea[26] ?? '');
                $tipoRaw = $totalCols > 28 ? ($linea[$totalCols - 1] ?? '') : ($linea[27] ?? '');

                $grupoDescuento = $limpiarTexto($linea[24] ?? '');
                $tagsLimpios = $limpiarTexto($tagsRaw);

                // LÓGICA DEL FILTRO ESPECIAL
                if ($filtroEspecial) {
                    if ($grupoDescuento !== '' || $tagsLimpios === '') {
                        return;
                    }
                }

                $listaCompleta[] = [
                    'ID' => ($id === '') ? '' : (is_numeric($id) ? (int)$id : $id),
                    'NOMBRE' => $limpiarTexto($linea[1] ?? ''),
                    'DIRECCION_FISCAL' => $limpiarTexto($linea[2] ?? ''),
                    'COLONIA_FISCAL' => $limpiarTexto($linea[3] ?? ''),
                    'MUNICIPIO_FISCAL' => $limpiarTexto($linea[4] ?? ''),
                    'CP_FISCAL' => $limpiarTexto($linea[5] ?? ''),
                    'ESTADO_FISCAL' => $limpiarTexto($linea[6] ?? ''),
                    'PAIS_FISCAL' => $limpiarTexto($linea[7] ?? ''),
                    'DIRECCION_CONTACTO' => $limpiarTexto($linea[8] ?? ''),
                    'COLONIA_CONTACTO' => $limpiarTexto($linea[9] ?? ''),
                    'MUNICIPIO_CONTACTO' => $limpiarTexto($linea[10] ?? ''),
                    'ESTADO_CONTACTO' => $limpiarTexto($linea[11] ?? ''),
                    'PAIS_CONTACTO' => $limpiarTexto($linea[12] ?? ''),
                    'CP_CONTACTO' => $limpiarTexto($linea[13] ?? ''),
                    'RFC' => $limpiarTexto($linea[14] ?? ''),
                    'TELEFONO' => $limpiarTexto($linea[15] ?? ''),
                    'EMAIL' => $limpiarTexto($linea[16] ?? ''),
                    'LIMITE_CREDITO' => (float)$limpiarTexto($linea[17] ?? '0'),
                    'CREDITO_DISPONIBLE' => (float)$limpiarTexto($linea[18] ?? '0'),
                    'DIAS_CHEQUE_POSTFECHADO' => (int)$limpiarTexto($linea[19] ?? '0'),
                    'DIAS_VENCIMIENTO' => (int)$limpiarTexto($linea[20] ?? '0'),
                    'PARTE_RELACIONAL' => (int)$limpiarTexto($linea[21] ?? '0'),
                    'REGIMEN_FISCAL' => $limpiarTexto($linea[22] ?? ''),
                    'USO_DE_CFDI' => $limpiarTexto($linea[23] ?? ''),
                    'GRUPO_DESCUENTO' => is_numeric($grupoDescuento) ? (int)$grupoDescuento : $grupoDescuento,
                    'VARIABLE_CONTABLE' => $limpiarTexto($linea[25] ?? ''),
                    'TAGS' => $tagsLimpios,
                    'TIPO' => $limpiarTexto($tipoRaw)
                ];
            });
        });

        if ($orden) {
            usort($listaCompleta, function ($a, $b) use ($orden) {
                switch ($orden) {
                    case 'id_asc':
                        return (int)$a['ID'] <=> (int)$b['ID'];
                    case 'id_desc':
                        return (int)$b['ID'] <=> (int)$a['ID'];
                    case 'nombre_asc':
                        return strcasecmp($a['NOMBRE'], $b['NOMBRE']);
                    case 'nombre_desc':
                        return strcasecmp($b['NOMBRE'], $a['NOMBRE']);
                    default:
                        return 0;
                }
            });
        }

        $listaFinal = [];
        foreach ($listaCompleta as $fila) {
            $filaFiltrada = [];
            foreach ($columnasSeleccionadas as $col) {
                if (array_key_exists($col, $fila)) {
                    $filaFiltrada[$col] = $fila[$col];
                }
            }
            if (!empty($filaFiltrada)) {
                $listaFinal[] = $filaFiltrada;
            }
        }

        $fecha = date('d-m-y');
        return (new FastExcel(collect($listaFinal)))->download("CLIENTES-PERSONALIZADO-$fecha.xlsx");
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
