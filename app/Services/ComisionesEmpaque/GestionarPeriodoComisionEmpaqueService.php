<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\PeriodoComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarPeriodoComisionEmpaqueService
{
    public function guardar(array $datos, User $responsable, ?PeriodoComisionEmpaque $periodo = null): PeriodoComisionEmpaque
    {
        return DB::transaction(function () use ($datos, $responsable, $periodo) {
            $area = AreaComisionEmpaque::query()->lockForUpdate()->findOrFail($datos['area_id']);
            if (! $area->activo) {
                throw ValidationException::withMessages(['area_id' => 'Elija un área activa.']);
            }
            $zona = ConfiguracionComisionEmpaque::actual()->zona_horaria;
            $desde = DineroComisionEmpaque::inicioDia($datos['desde'], $zona);
            $hasta = DineroComisionEmpaque::inicioDia($datos['hasta'], $zona)->addDay();
            if ($hasta->lte($desde)) {
                throw ValidationException::withMessages(['hasta' => 'El fin debe ser igual o posterior al inicio.']);
            }
            $antes = null;
            if ($periodo) {
                $periodo = PeriodoComisionEmpaque::query()->lockForUpdate()->findOrFail($periodo->id);
                $antes = $periodo->toArray();
                if ((int) $periodo->area_id !== (int) $area->id) {
                    throw ValidationException::withMessages(['area_id' => 'El área del periodo no se puede cambiar.']);
                }
                if (mb_strlen(trim($datos['motivo'] ?? '')) < 5) {
                    throw ValidationException::withMessages(['motivo' => 'Explique el cambio con al menos 5 caracteres.']);
                }
                if ($periodo->corte()) {
                    throw ValidationException::withMessages(['periodo' => 'Este periodo tiene un corte confirmado. Sus fechas y su reparto se conservan. Cree el siguiente periodo.']);
                }
            }
            $solapa = PeriodoComisionEmpaque::query()->where('area_id', $area->id)
                ->when($periodo, fn ($q) => $q->where('id', '!=', $periodo->id))
                ->where('desde', '<', $hasta)->where('hasta', '>', $desde)->exists();
            $cortado = CorteComisionEmpaque::query()->where('area_id', $area->id)->where('estado', CorteComisionEmpaque::CONFIRMADO)
                ->where('limite_desde', '<', $hasta)->where('limite_hasta', '>=', $desde)->exists();
            if ($solapa || $cortado) {
                throw ValidationException::withMessages(['desde' => 'Las fechas se cruzan con otro periodo o corte confirmado del área. Elija días que no estén incluidos.']);
            }
            $atributos = ['area_id' => $area->id, 'nombre' => trim($datos['nombre']), 'desde' => $desde, 'hasta' => $hasta, 'motivo' => $datos['motivo'] ?? null];
            if ($periodo) {
                $periodo->update($atributos);
            } else {
                $periodo = PeriodoComisionEmpaque::query()->create($atributos + ['creado_por_id' => $responsable->id]);
            }
            AuditoriaComisionEmpaque::anotar($responsable, 'periodo', $periodo->id, $antes ? 'actualizar' : 'crear', $datos['motivo'] ?? null, ['antes' => $antes, 'despues' => $periodo->toArray()]);

            return $periodo;
        });
    }
}
