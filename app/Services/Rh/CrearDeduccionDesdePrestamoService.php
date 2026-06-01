<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhPrestamoPagoFijo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CrearDeduccionDesdePrestamoService
{
    public function __construct(
        private GenerarFolioDeduccionService $generarFolio,
    ) {}

    public function ejecutar(
        RhPrestamoPagoFijo $prestamo,
        User $registrador,
        Carbon $fechaOcurrencia,
        ?Carbon $fechaDeduccionNomina = null,
    ): RhDeduccion {
        $colaborador = RhColaborador::with(['departamento', 'area'])->findOrFail($prestamo->rh_colaborador_id);
        $monto = round((float) $prestamo->monto_cuota, 2);
        $numeroCuota = $prestamo->pagos_realizados + 1;

        return RhDeduccion::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $this->generarFolio->ejecutar(),
            'fecha_ocurrencia' => $fechaOcurrencia->toDateString(),
            'rh_colaborador_id' => $colaborador->id,
            'rh_prestamo_pago_fijo_id' => $prestamo->id,
            'numero_cuota' => $numeroCuota,
            'monto_deduccion_base' => $monto,
            'factor_multiplicador' => 1,
            'total_parcial' => $monto,
            'monto_total_final' => $monto,
            'deduccion_salario_base' => $monto,
            'deduccion_bono_puntualidad' => 0,
            'deduccion_bono_productividad' => 0,
            'total_deduccion' => (int) round($monto),
            'origen_deduccion' => RhDeduccion::ORIGEN_NOMINA,
            'descripcion_detallada' => $prestamo->observaciones,
            'fecha_deduccion_nomina' => ($fechaDeduccionNomina ?? $fechaOcurrencia)->toDateString(),
            'estado_deduccion' => RhDeduccion::ESTADO_PENDIENTE_NOMINA,
            'salario_diario_snapshot' => $colaborador->salario_diario,
            'bono_puntualidad_diario_snapshot' => $colaborador->bono_puntualidad_diario,
            'bono_productividad_diario_snapshot' => $colaborador->bono_productividad_diario,
            'regla_nombre_snapshot' => $prestamo->concepto,
            'regla_comportamiento_snapshot' => 'prestamo_pago_fijo',
            'departamento_snapshot' => $colaborador->departamento?->nombre,
            'area_snapshot' => $colaborador->area?->nombre,
            'registrado_por_id' => $registrador->id,
        ]);
    }
}
