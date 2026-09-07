<?php

namespace App\Services\PuntoVenta\Reportes;

use App\Support\PuntoVenta\Reportes\ColumnasExportacionReportePdv;
use App\Support\PuntoVenta\Reportes\EtiquetasMetricaReportePdv;

class FilasExportacionReportePdvService
{
    /**
     * @param  array{resguardos?: array<string, mixed>, turnos_operacion?: array<string, mixed>}  $payloads
     * @return list<array<string, mixed>>
     */
    public function filasParametros(array $payloads, string $tipoReporte): array
    {
        $filas = [
            ['clave' => 'tipo_reporte', 'valor' => $tipoReporte],
        ];

        foreach (['resguardos', 'turnos_operacion'] as $clave) {
            if (! isset($payloads[$clave])) {
                continue;
            }

            $bloque = $payloads[$clave];
            $filas[] = ['clave' => $clave.'.corte_reporte_at', 'valor' => $bloque['corte_reporte_at'] ?? ''];
            $filas[] = ['clave' => $clave.'.rango.desde', 'valor' => $bloque['rango']['desde'] ?? ''];
            $filas[] = ['clave' => $clave.'.rango.hasta', 'valor' => $bloque['rango']['hasta'] ?? ''];

            foreach ($bloque['filtros'] ?? [] as $filtro => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                $filas[] = ['clave' => $clave.'.filtros.'.$filtro, 'valor' => is_scalar($valor) ? (string) $valor : json_encode($valor)];
            }
        }

        return $filas;
    }

    /**
     * @param  array{resguardos?: array<string, mixed>, turnos_operacion?: array<string, mixed>}  $payloads
     * @return list<array<string, mixed>>
     */
    public function filasMetricas(array $payloads): array
    {
        $filas = [];

        foreach (['resguardos', 'turnos_operacion'] as $bloqueClave) {
            if (! isset($payloads[$bloqueClave]['metricas'])) {
                continue;
            }

            foreach ($payloads[$bloqueClave]['metricas'] as $metricaId => $metrica) {
                $filas[] = $this->filaMetrica(
                    EtiquetasMetricaReportePdv::seccion((string) $metricaId),
                    (string) $metricaId,
                    $metrica,
                );
            }

            foreach ($payloads[$bloqueClave]['por_sucursal'] ?? [] as $sucursalId => $metricas) {
                foreach ($metricas as $metricaId => $metrica) {
                    $fila = $this->filaMetrica(
                        'desglose_sucursal',
                        (string) $metricaId,
                        $metrica,
                    );
                    $fila['sucursal_id'] = (string) $sucursalId;
                    $filas[] = $fila;
                }
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $metrica
     * @return array<string, mixed>
     */
    private function filaMetrica(string $seccion, string $metricaId, array $metrica): array
    {
        $percentiles = $metrica['percentiles'] ?? [];

        return [
            'seccion' => $seccion,
            'metrica_id' => $metricaId,
            'etiqueta' => EtiquetasMetricaReportePdv::etiqueta($metricaId),
            'sucursal_id' => '',
            'unidad' => (string) ($metrica['unidad'] ?? ''),
            'valor' => $this->valorPrincipal($metrica),
            'conteo' => $this->campoOpcional($metrica, 'conteo'),
            'promedio_segundos' => $this->campoOpcional($metrica, 'promedio_segundos'),
            'p50' => $this->campoOpcional($percentiles, 'p50'),
            'p90' => $this->campoOpcional($percentiles, 'p90'),
            'p95' => $this->campoOpcional($percentiles, 'p95'),
            'en_curso' => $this->enCurso($metrica),
            'detalle' => $this->detalleJson($metrica),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrica
     */
    private function valorPrincipal(array $metrica): string
    {
        if (array_key_exists('valor', $metrica) && $metrica['valor'] !== null) {
            return (string) $metrica['valor'];
        }

        if (array_key_exists('promedio_segundos', $metrica) && $metrica['promedio_segundos'] !== null) {
            return (string) $metrica['promedio_segundos'];
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function campoOpcional(array $datos, string $clave): string
    {
        if (! array_key_exists($clave, $datos) || $datos[$clave] === null) {
            return '';
        }

        return (string) $datos[$clave];
    }

    /**
     * @param  array<string, mixed>  $metrica
     */
    private function enCurso(array $metrica): string
    {
        if (! array_key_exists('en_curso', $metrica)) {
            return '';
        }

        $valor = $metrica['en_curso'];

        return ($valor === true || $valor === 1 || $valor === '1') ? '1' : '0';
    }

    /**
     * @param  array<string, mixed>  $metrica
     */
    private function detalleJson(array $metrica): string
    {
        $detalle = array_diff_key($metrica, array_flip([
            'id', 'unidad', 'valor', 'conteo', 'promedio_segundos', 'percentiles', 'en_curso',
        ]));

        if ($detalle === []) {
            return '';
        }

        return (string) json_encode($detalle, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, string>
     */
    public function columnasMetricas(): array
    {
        return ColumnasExportacionReportePdv::metricas();
    }

    /**
     * @return array<string, string>
     */
    public function columnasParametros(): array
    {
        return ColumnasExportacionReportePdv::parametros();
    }
}
