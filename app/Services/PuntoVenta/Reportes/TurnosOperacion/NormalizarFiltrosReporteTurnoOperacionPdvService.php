<?php

namespace App\Services\PuntoVenta\Reportes\TurnosOperacion;

use App\Models\PuntoVenta\TurnoPdv;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class NormalizarFiltrosReporteTurnoOperacionPdvService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   desde: Carbon,
     *   hasta: Carbon,
     *   corte_reporte_at: Carbon,
     *   sucursal_id: int|null,
     *   servicio: string|null,
     *   user_id: int|null,
     *   fecha_operativa: string|null,
     *   franja_desde: string|null,
     *   franja_hasta: string|null,
     *   alcance: string|null
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

        $franjaDesde = $this->normalizarHoraFranja($filtros['franja_desde'] ?? null, 'franja_desde');
        $franjaHasta = $this->normalizarHoraFranja($filtros['franja_hasta'] ?? null, 'franja_hasta');

        if (($franjaDesde !== null) xor ($franjaHasta !== null)) {
            throw ValidationException::withMessages([
                'franja' => 'Debe indicar franja_desde y franja_hasta juntos.',
            ]);
        }

        if ($franjaDesde !== null && $franjaDesde >= $franjaHasta) {
            throw ValidationException::withMessages([
                'franja_hasta' => 'La franja debe ser un intervalo [desde, hasta) válido.',
            ]);
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'corte_reporte_at' => $corte,
            'sucursal_id' => $this->normalizarSucursalId($filtros['sucursal_id'] ?? null),
            'servicio' => $this->normalizarServicio($filtros['servicio'] ?? null),
            'user_id' => $this->normalizarUserId($filtros['user_id'] ?? null),
            'fecha_operativa' => $this->normalizarFechaOperativa($filtros['fecha_operativa'] ?? null),
            'franja_desde' => $franjaDesde,
            'franja_hasta' => $franjaHasta,
            'alcance' => $this->normalizarAlcance($filtros['alcance'] ?? null),
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
            'servicio' => $filtros['servicio'],
            'user_id' => $filtros['user_id'],
            'fecha_operativa' => $filtros['fecha_operativa'],
            'franja_desde' => $filtros['franja_desde'],
            'franja_hasta' => $filtros['franja_hasta'],
            'alcance' => $filtros['alcance_resuelto'] ?? $filtros['alcance'],
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

    private function normalizarUserId(mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        if (! is_numeric($userId)) {
            throw ValidationException::withMessages([
                'user_id' => 'Persona no válida.',
            ]);
        }

        return (int) $userId;
    }

    private function normalizarServicio(mixed $servicio): ?string
    {
        if ($servicio === null || $servicio === '') {
            return null;
        }

        $servicio = (string) $servicio;
        if ($servicio !== TurnoPdv::SERVICIO_VENTAS) {
            throw ValidationException::withMessages([
                'servicio' => 'Servicio no válido para el reporte.',
            ]);
        }

        return $servicio;
    }

    private function normalizarFechaOperativa(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $fecha = (string) $fecha;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            throw ValidationException::withMessages([
                'fecha_operativa' => 'Fecha operativa no válida.',
            ]);
        }

        return $fecha;
    }

    private function normalizarHoraFranja(mixed $valor, string $campo): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (! is_string($valor) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', trim($valor), $coincidencias) !== 1) {
            throw ValidationException::withMessages([
                $campo => 'Hora de franja no válida.',
            ]);
        }

        return $coincidencias[1].':'.$coincidencias[2];
    }

    private function normalizarAlcance(mixed $alcance): ?string
    {
        if ($alcance === null || $alcance === '') {
            return null;
        }

        $alcance = (string) $alcance;
        $permitidos = [
            MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO,
            MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO,
            MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL,
        ];

        if (! in_array($alcance, $permitidos, true)) {
            throw ValidationException::withMessages([
                'alcance' => 'Alcance no válido para el reporte.',
            ]);
        }

        return $alcance;
    }
}
