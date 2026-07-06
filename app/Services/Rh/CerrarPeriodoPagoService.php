<?php

namespace App\Services\Rh;

use App\Models\RhConfiguracion;
use App\Models\RhHorasExtra;
use App\Models\RhSalidaPersonal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CerrarPeriodoPagoService
{
    public function __construct(
        private SellarConsolidadoDeduccionesService $sellarDeducciones,
    ) {}

    /**
     * @return array{deducciones: int, salidas: int, horas_extra: int}
     */
    public function ejecutar(Carbon $fechaInicio, Carbon $fechaFin, Carbon $fechaPago, User $usuario, bool $forzar = false): array
    {
        if ($fechaPago->gt($fechaFin)) {
            throw ValidationException::withMessages([
                'fecha_pago' => 'La fecha de pago no puede ser posterior al fin del periodo.',
            ]);
        }

        $config = RhConfiguracion::obtener();
        $inicioStr = $fechaInicio->toDateString();
        $finStr = $fechaFin->toDateString();

        if (!$forzar
            && $config->periodo_cerrado_en
            && $config->periodo_cerrado_inicio?->toDateString() === $inicioStr
            && $config->periodo_cerrado_fin?->toDateString() === $finStr) {
            throw ValidationException::withMessages([
                'periodo' => 'Este periodo ya fue cerrado. Confirme de nuevo si desea re-ejecutar el cierre.',
            ]);
        }

        $fechaPagoStr = $fechaPago->toDateString();
        $resultado = ['deducciones' => 0, 'salidas' => 0, 'horas_extra' => 0];

        DB::transaction(function () use ($fechaInicio, $fechaFin, $fechaPagoStr, $usuario, $inicioStr, $finStr, &$resultado, $config) {
            $resultado['deducciones'] = $this->sellarDeducciones->ejecutar($fechaInicio, $fechaFin, $usuario);

            $resultado['salidas'] = RhSalidaPersonal::query()
                ->whereNull('fecha_deduccion_nomina')
                ->whereBetween('fecha_evento', [$inicioStr, $finStr])
                ->update(['fecha_deduccion_nomina' => $fechaPagoStr]);

            $resultado['horas_extra'] = RhHorasExtra::query()
                ->where('estado_pago', 'pendiente')
                ->whereBetween('fecha_turno', [$inicioStr, $finStr])
                ->update([
                    'fecha_programada_pago' => $fechaPagoStr,
                    'estado_pago' => 'programado',
                ]);

            $config->update([
                'periodo_cerrado_en' => now(),
                'periodo_cerrado_por_id' => $usuario->id,
                'periodo_cerrado_inicio' => $inicioStr,
                'periodo_cerrado_fin' => $finStr,
            ]);
        });

        return $resultado;
    }

    public function periodoYaCerrado(Carbon $fechaInicio, Carbon $fechaFin): bool
    {
        $config = RhConfiguracion::obtener();

        return $config->periodo_cerrado_en
            && $config->periodo_cerrado_inicio?->toDateString() === $fechaInicio->toDateString()
            && $config->periodo_cerrado_fin?->toDateString() === $fechaFin->toDateString();
    }
}
