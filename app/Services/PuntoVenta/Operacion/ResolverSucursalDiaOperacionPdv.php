<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

final class ResolverSucursalDiaOperacionPdv
{
    public function __construct(
        private readonly OperacionPdvConfig $config,
    ) {}

    public function obtenerOCrear(int $sucursalId, ?CarbonInterface $momento = null): SucursalDiaOperacionPdv
    {
        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $momento);

        $existente = SucursalDiaOperacionPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->first();

        if ($existente instanceof SucursalDiaOperacionPdv) {
            return $existente;
        }

        try {
            return SucursalDiaOperacionPdv::query()->create([
                'sucursal_id' => $sucursalId,
                'fecha_operativa' => $fechaOperativa,
                'acepta_altas' => true,
                'cierre_automatico_invalidado' => false,
                'version' => 1,
            ]);
        } catch (UniqueConstraintViolationException|QueryException) {
            return SucursalDiaOperacionPdv::query()
                ->where('sucursal_id', $sucursalId)
                ->whereDate('fecha_operativa', $fechaOperativa)
                ->firstOrFail();
        }
    }
}
