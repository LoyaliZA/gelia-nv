<?php

namespace App\Support\ControlPedidos;

use Carbon\Carbon;

/**
 * Corte de Tienda MD sobre el instante en que el servidor acepta el primer reporte de pago.
 * No usa la fecha declarada, la apertura de pantalla, la revisión ni la remisión.
 */
final class CorteMismoDiaTienda
{
    public const HORA = '13:00';

    public const OPORTUNO = 'oportuno';

    public const TARDIO = 'tardio';

    public const SIN_INTENCION = 'sin_intencion';

    /**
     * @return array{estado: string, oportuno: bool, confirmada: false, motivo: ?string, corte: ?string}
     */
    public function evaluar(Carbon $aceptadoEn, string $zona, bool $intencion): array
    {
        if (! $intencion) {
            return [
                'estado' => self::SIN_INTENCION,
                'oportuno' => false,
                'confirmada' => false,
                'motivo' => null,
                'corte' => null,
            ];
        }

        $local = $aceptadoEn->copy()->timezone($zona);
        $config = app(\App\Services\ControlPedidos\ConfiguracionOperativaTienda::class)->obtener();
        $hora = $config['md_corte'];
        [$h, $m] = array_map('intval', explode(':', $hora));
        $corte = $local->copy()->setTime($h, $m, 0);
        if (! in_array($local->dayOfWeekIso, $config['md_dias'], true)) {
            return [
                'estado' => self::TARDIO,
                'oportuno' => false,
                'confirmada' => false,
                'motivo' => 'Este día no está habilitado para Mismo día. Reporte pendiente de acuerdo.',
                'corte' => $corte->toIso8601String(),
            ];
        }

        $oportuno = $local->lessThanOrEqualTo($corte);

        return [
            'estado' => $oportuno ? self::OPORTUNO : self::TARDIO,
            'oportuno' => $oportuno,
            'confirmada' => false,
            'motivo' => $oportuno
                ? 'Reporte dentro del corte de '.$hora.'. Falta verificar ingreso y remisión.'
                : 'Reporte después del corte de '.$hora.'. Pendiente de acuerdo.',
            'corte' => $corte->toIso8601String(),
        ];
    }
}
