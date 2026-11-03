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
        $corte = $local->copy()->setTime(13, 0, 0);
        if ($local->isWeekend()) {
            return [
                'estado' => self::TARDIO,
                'oportuno' => false,
                'confirmada' => false,
                'motivo' => 'Mismo día se solicita de lunes a viernes. Este reporte queda pendiente de acuerdo.',
                'corte' => $corte->toIso8601String(),
            ];
        }

        $oportuno = $local->lessThanOrEqualTo($corte);

        return [
            'estado' => $oportuno ? self::OPORTUNO : self::TARDIO,
            'oportuno' => $oportuno,
            'confirmada' => false,
            'motivo' => $oportuno
                ? 'El reporte de pago entró hasta las 13:00. Aún faltan disponibilidad, documentación, preparación y traslado.'
                : 'El reporte llegó después de las 13:00. Queda pendiente de acuerdo, sin cambiar la modalidad.',
            'corte' => $corte->toIso8601String(),
        ];
    }
}
