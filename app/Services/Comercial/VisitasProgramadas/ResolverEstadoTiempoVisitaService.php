<?php

namespace App\Services\Comercial\VisitasProgramadas;

use App\Models\Comercial\VisitaClienteProgramada;
use Carbon\CarbonInterface;

final class ResolverEstadoTiempoVisitaService
{
    public const EN_TIEMPO = 'en_tiempo';

    public const RETRASADO = 'retrasado';

    public const SIN_REFERENCIA = 'sin_referencia';

    public function resolver(VisitaClienteProgramada $visita, CarbonInterface $ahora): string
    {
        if ($visita->estado !== VisitaClienteProgramada::ESTADO_PROGRAMADA) {
            return self::SIN_REFERENCIA;
        }

        $fecha = $visita->fecha->toDateString();
        $hoy = $ahora->toDateString();

        if ($fecha !== $hoy) {
            return $fecha > $hoy ? self::EN_TIEMPO : self::SIN_REFERENCIA;
        }

        if ($visita->tipo_hora === VisitaClienteProgramada::TIPO_HORA_SIN) {
            return self::EN_TIEMPO;
        }

        $tz = $ahora->getTimezone();

        if ($visita->tipo_hora === VisitaClienteProgramada::TIPO_HORA_EXACTA && $visita->hora_exacta) {
            $limite = $ahora->copy()->setTimezone($tz)->setTimeFromTimeString((string) $visita->hora_exacta);

            return $ahora->greaterThan($limite) ? self::RETRASADO : self::EN_TIEMPO;
        }

        if ($visita->tipo_hora === VisitaClienteProgramada::TIPO_HORA_RANGO && $visita->hora_fin) {
            $fin = $ahora->copy()->setTimezone($tz)->setTimeFromTimeString((string) $visita->hora_fin);

            return $ahora->greaterThan($fin) ? self::RETRASADO : self::EN_TIEMPO;
        }

        return self::SIN_REFERENCIA;
    }
}
