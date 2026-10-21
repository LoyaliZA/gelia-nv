<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuardarEquipoComisionEmpaqueService
{
    public function guardar(int $areaId, array $lineas, string $motivo, User $responsable, ?int $versionEsperada = null): RepartoComisionEmpaque
    {
        return DB::transaction(function () use ($areaId, $lineas, $motivo, $responsable, $versionEsperada) {
            $area = AreaComisionEmpaque::query()->lockForUpdate()->findOrFail($areaId);
            if (! $area->activo) {
                throw ValidationException::withMessages(['area_id' => 'Seleccione un área activa.']);
            }
            $normalizadas = [];
            foreach ($lineas as $i => $linea) {
                $usuario = User::query()->find($linea['user_id'] ?? 0);
                if (! $usuario || isset($normalizadas[$usuario->id])) {
                    throw ValidationException::withMessages(['lineas' => 'Seleccione cuentas distintas para cada colaborador.']);
                }
                try {
                    $puntos = DineroComisionEmpaque::puntosBase((string) $linea['porcentaje']);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(["lineas.$i.porcentaje" => 'Escriba un porcentaje con hasta dos decimales.']);
                }
                if ($puntos <= 0 || $puntos > 10000) {
                    throw ValidationException::withMessages(['lineas' => 'Cada porcentaje debe ser mayor a cero y no exceder 100%.']);
                }
                $normalizadas[$usuario->id] = ['usuario' => $usuario, 'puntos' => $puntos];
            }
            if (array_sum(array_column($normalizadas, 'puntos')) !== 10000) {
                throw ValidationException::withMessages(['lineas' => 'El reparto del fondo común debe sumar exactamente 100%.']);
            }
            $anterior = RepartoComisionEmpaque::permanente($areaId);
            if ($versionEsperada !== null && $versionEsperada !== (int) ($anterior?->id ?? 0)) {
                throw ValidationException::withMessages(['equipo' => 'Otra persona actualizó el reparto. Recargue y revise los porcentajes antes de guardar.']);
            }
            $ahora = now();
            if ($anterior) {
                $anterior->update(['vigente_hasta' => $ahora]);
            }
            $reparto = RepartoComisionEmpaque::query()->create([
                'area_id' => $areaId, 'permanente' => true, 'motivo' => $motivo,
                'vigente_desde' => $ahora, 'creado_por_id' => $responsable->id,
            ]);
            foreach ($normalizadas as $linea) {
                $persona = ParticipanteComisionEmpaque::query()->firstOrCreate(
                    ['user_id' => $linea['usuario']->id],
                    ['nombre' => $linea['usuario']->name, 'activo' => true],
                );
                if (! $persona->activo) {
                    throw ValidationException::withMessages(['lineas' => 'La cuenta '.$persona->nombre.' está inactiva en comisiones. Reactívela antes de incluirla.']);
                }
                $reparto->lineas()->create(['participante_id' => $persona->id, 'puntos_base' => $linea['puntos']]);
            }
            AuditoriaComisionEmpaque::anotar($responsable, 'equipo', $reparto->id, 'guardar_reparto_permanente', $motivo, ['anterior_id' => $anterior?->id, 'lineas' => $reparto->load('lineas')->lineas->toArray()]);

            return $reparto;
        });
    }
}
