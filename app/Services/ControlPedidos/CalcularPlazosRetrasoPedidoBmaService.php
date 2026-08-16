<?php

namespace App\Services\ControlPedidos;

use Carbon\Carbon;

class CalcularPlazosRetrasoPedidoBmaService
{
    public function __construct(
        private PlazosRetrasoPedidoBmaConfig $configService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function esDiaHabil(Carbon $fecha, ?array $config = null): bool
    {
        $config = $this->configService->normalizar($config ?? $this->configService->obtener());

        return in_array((int) $fecha->isoWeekday(), $config['dias_habiles'], true);
    }

    /**
     * Día de origen del SLA: si el ancla cae fuera de día hábil o después del corte,
     * empieza el siguiente día hábil.
     *
     * @param  array<string, mixed>|null  $config
     */
    public function diaOrigen(Carbon $ancla, ?array $config = null): Carbon
    {
        $config = $this->configService->normalizar($config ?? $this->configService->obtener());
        $ancla = $ancla->copy()->timezone(config('app.timezone'));
        [$hora, $minuto] = array_map('intval', explode(':', $config['hora_corte']));

        $dia = $ancla->copy()->startOfDay();

        if (! $this->esDiaHabil($dia, $config)) {
            return $this->siguienteDiaHabil($dia, $config);
        }

        $corte = $dia->copy()->setTime($hora, $minuto, 0);
        if ($ancla->gte($corte)) {
            return $this->siguienteDiaHabil($dia, $config);
        }

        return $dia;
    }

    /**
     * Deadline = día de origen + N días hábiles, a la hora de corte.
     *
     * @param  array<string, mixed>|null  $config
     */
    public function deadlineDesdeAncla(Carbon $ancla, int $diasHabilesPlazo, ?array $config = null): Carbon
    {
        $config = $this->configService->normalizar($config ?? $this->configService->obtener());
        $dias = max(1, $diasHabilesPlazo);
        $dia = $this->diaOrigen($ancla, $config);

        for ($i = 0; $i < $dias; $i++) {
            $dia = $this->siguienteDiaHabil($dia, $config);
        }

        [$hora, $minuto] = array_map('intval', explode(':', $config['hora_corte']));

        return $dia->copy()->setTime($hora, $minuto, 0);
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function siguienteDiaHabil(Carbon $desde, ?array $config = null): Carbon
    {
        $config = $this->configService->normalizar($config ?? $this->configService->obtener());
        $cursor = $desde->copy()->startOfDay()->addDay();

        // ponytail: bounded scan; 14 days covers any reasonable dias_habiles set
        for ($i = 0; $i < 14; $i++) {
            if ($this->esDiaHabil($cursor, $config)) {
                return $cursor;
            }
            $cursor->addDay();
        }

        return $cursor;
    }
}
