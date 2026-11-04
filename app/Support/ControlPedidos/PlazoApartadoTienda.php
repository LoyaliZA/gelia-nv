<?php

namespace App\Support\ControlPedidos;

use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Carbon\Carbon;

/**
 * Vigencia del apartado impago T-01.
 * Lunes a viernes 17:00 y sábado 13:00, en la zona de preparación.
 * El cierre operativo configurado (predeterminado 20:00) no alarga ni sustituye este plazo.
 */
final class PlazoApartadoTienda
{
    public const HORA_SEMANA = '17:00';

    public const HORA_SABADO = '13:00';

    public const MOTIVO_CONFIGURACION = 'configuracion';

    public const MOTIVO_VENCIDO = 'apartado_vencido';

    public const MOTIVO_CIERRE = 'cierre_operativo';

    public const MENSAJE_CONFIGURACION = 'Falta la zona horaria de preparación. No se calcula la vigencia del apartado.';

    public const MENSAJE_VENCIDO = 'El apartado ya venció. No se renueva automáticamente.';

    public const MENSAJE_CIERRE = 'El cierre operativo de hoy ya pasó. El apartado no se confirma fuera de su vigencia.';

    public function __construct(
        private PreparacionTiendaConfig $config,
    ) {}

    /**
     * @return array{permite: bool, motivo: string, mensaje: string, vence_at: ?Carbon}
     */
    public function evaluarConfirmacion(Carbon $momento, ?Carbon $fechaLimite): array
    {
        $zona = $this->zonaValida();
        if ($zona === null) {
            return $this->rechazo(self::MOTIVO_CONFIGURACION, self::MENSAJE_CONFIGURACION, null);
        }

        $local = $momento->copy()->timezone($zona);
        if ($fechaLimite !== null && $momento->greaterThanOrEqualTo($fechaLimite)) {
            return $this->rechazo(self::MOTIVO_VENCIDO, self::MENSAJE_VENCIDO, $fechaLimite->copy());
        }

        if ($local->isSaturday() && $local->hour < 9) {
            return $this->rechazo(self::MOTIVO_CIERRE, 'El horario sabatino de apartado comienza a las 09:00.', null);
        }
        $corte = $this->corteDelDia($local);
        if ($corte === null || $momento->greaterThanOrEqualTo($corte)) {
            return $this->rechazo(self::MOTIVO_CIERRE, self::MENSAJE_CIERRE, $corte);
        }

        $vence = $corte;
        if ($fechaLimite !== null && $fechaLimite->lessThan($corte)) {
            $vence = $fechaLimite->copy();
        }

        return [
            'permite' => true,
            'motivo' => 'vigente',
            'mensaje' => '',
            'vence_at' => $vence,
        ];
    }

    public function corteDelDia(Carbon $local): ?Carbon
    {
        if ($local->isSunday()) {
            return null;
        }

        $hora = $local->isSaturday() ? self::HORA_SABADO : self::HORA_SEMANA;
        [$h, $m] = array_map('intval', explode(':', $hora));

        return $local->copy()->setTime($h, $m, 0);
    }

    private function zonaValida(): ?string
    {
        $zona = trim($this->config->zonaHoraria());
        if ($zona === '' || ! in_array($zona, timezone_identifiers_list(), true)) {
            return null;
        }

        return $zona;
    }

    /**
     * @return array{permite: bool, motivo: string, mensaje: string, vence_at: ?Carbon}
     */
    private function rechazo(string $motivo, string $mensaje, ?Carbon $vence): array
    {
        return [
            'permite' => false,
            'motivo' => $motivo,
            'mensaje' => $mensaje,
            'vence_at' => $vence,
        ];
    }
}
