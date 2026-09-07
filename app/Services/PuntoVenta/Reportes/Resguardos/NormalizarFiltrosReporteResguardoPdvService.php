<?php

namespace App\Services\PuntoVenta\Reportes\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class NormalizarFiltrosReporteResguardoPdvService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   desde: Carbon,
     *   hasta: Carbon,
     *   corte_reporte_at: Carbon,
     *   sucursal_id: int|null,
     *   estado: string|null,
     *   antiguedad: string|null,
     *   tipo_incidencia: string|null
     * }
     */
    public function normalizar(array $filtros, ?Carbon $ahora = null): array
    {
        $corte = $this->parsearFecha($filtros['corte_reporte_at'] ?? null, $ahora ?? now());
        $desde = $this->parsearFecha($filtros['desde'] ?? null, $corte->copy()->startOfMonth());
        $hasta = $this->parsearFecha($filtros['hasta'] ?? null, $corte->copy()->addSecond());

        if ($desde->gte($hasta)) {
            throw ValidationException::withMessages([
                'hasta' => 'El límite superior del rango debe ser posterior a desde.',
            ]);
        }

        $estado = $this->normalizarEstado($filtros['estado'] ?? null);
        $antiguedad = $this->normalizarAntiguedad($filtros['antiguedad'] ?? null);
        $tipoIncidencia = $this->normalizarTipoIncidencia($filtros['tipo_incidencia'] ?? null);
        $sucursalId = $this->normalizarSucursalId($filtros['sucursal_id'] ?? null);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'corte_reporte_at' => $corte,
            'sucursal_id' => $sucursalId,
            'estado' => $estado,
            'antiguedad' => $antiguedad,
            'tipo_incidencia' => $tipoIncidencia,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function paraPayload(array $filtros): array
    {
        return [
            'desde' => $filtros['desde']->toIso8601String(),
            'hasta' => $filtros['hasta']->toIso8601String(),
            'corte_reporte_at' => $filtros['corte_reporte_at']->toIso8601String(),
            'sucursal_id' => $filtros['sucursal_id'],
            'estado' => $filtros['estado'],
            'antiguedad' => $filtros['antiguedad'],
            'tipo_incidencia' => $filtros['tipo_incidencia'],
        ];
    }

    private function parsearFecha(mixed $valor, Carbon $fallback): Carbon
    {
        if ($valor instanceof Carbon) {
            return $valor->copy();
        }

        if ($valor === null || $valor === '') {
            return $fallback->copy();
        }

        return Carbon::parse((string) $valor);
    }

    private function normalizarEstado(mixed $estado): ?string
    {
        if ($estado === null || $estado === '') {
            return null;
        }

        $estado = (string) $estado;
        $permitidos = [
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
            ResguardoPdv::ESTADO_ENTREGADO,
            ResguardoPdv::ESTADO_DEVUELTO,
        ];

        if (! in_array($estado, $permitidos, true)) {
            throw ValidationException::withMessages([
                'estado' => 'Estado de resguardo no válido para el reporte.',
            ]);
        }

        return $estado;
    }

    private function normalizarAntiguedad(mixed $antiguedad): ?string
    {
        if ($antiguedad === null || $antiguedad === '') {
            return null;
        }

        $antiguedad = (string) $antiguedad;
        if (! in_array($antiguedad, AntiguedadOperativaResguardoPdv::valores(), true)) {
            throw ValidationException::withMessages([
                'antiguedad' => 'Clasificación de antigüedad no válida.',
            ]);
        }

        return $antiguedad;
    }

    private function normalizarTipoIncidencia(mixed $tipo): ?string
    {
        if ($tipo === null || $tipo === '') {
            return null;
        }

        $tipo = (string) $tipo;
        $permitidos = [
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
            ResguardoPdvIncidencia::TIPO_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE,
        ];

        if (! in_array($tipo, $permitidos, true)) {
            throw ValidationException::withMessages([
                'tipo_incidencia' => 'Tipo de incidencia no válido.',
            ]);
        }

        return $tipo;
    }

    private function normalizarSucursalId(mixed $sucursalId): ?int
    {
        if ($sucursalId === null || $sucursalId === '') {
            return null;
        }

        if (! is_numeric($sucursalId)) {
            throw ValidationException::withMessages([
                'sucursal_id' => 'Sucursal no válida.',
            ]);
        }

        return (int) $sucursalId;
    }
}
