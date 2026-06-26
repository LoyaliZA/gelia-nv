<?php

namespace App\Services\Cobranza;

use Carbon\Carbon;

class CobranzaAlertasReglasService
{
    public function configuracionPorDefecto(): array
    {
        return [
            'intervalo_dias' => 3,
            'umbral_diario' => 30,
            'dias_gracia' => 3,
            'dias_habiles' => [1, 2, 3, 4, 5],
        ];
    }

    public function normalizar(array $configuracion): array
    {
        $defaults = $this->configuracionPorDefecto();

        $diasHabiles = $configuracion['dias_habiles'] ?? $defaults['dias_habiles'];
        if (!is_array($diasHabiles)) {
            $diasHabiles = $defaults['dias_habiles'];
        }

        $diasHabiles = array_values(array_unique(array_filter(array_map(
            static fn ($dia) => (int) $dia,
            $diasHabiles
        ), static fn (int $dia) => $dia >= 1 && $dia <= 7)));

        if ($diasHabiles === []) {
            $diasHabiles = $defaults['dias_habiles'];
        }

        sort($diasHabiles);

        return [
            'intervalo_dias' => max(1, (int) ($configuracion['intervalo_dias'] ?? $defaults['intervalo_dias'])),
            'umbral_diario' => max(1, (int) ($configuracion['umbral_diario'] ?? $defaults['umbral_diario'])),
            'dias_gracia' => max(0, (int) ($configuracion['dias_gracia'] ?? $defaults['dias_gracia'])),
            'dias_habiles' => $diasHabiles,
        ];
    }

    public function esDiaHabil(Carbon $fecha, array $configuracion): bool
    {
        $config = $this->normalizar($configuracion);

        return in_array($fecha->isoWeekday(), $config['dias_habiles'], true);
    }

    /**
     * Determina si un crédito vencido corresponde llamar/notificar hoy según gracia, intervalo y umbral.
     */
    public function esDiaDeLlamada(int $diasAtraso, array $configuracion): bool
    {
        if ($diasAtraso < 1) {
            return false;
        }

        $config = $this->normalizar($configuracion);

        // ponytail: skip the umbral_diario and intervalo_dias check, only check if it is post dias_gracia (3 days post corte)
        if ($diasAtraso <= $config['dias_gracia']) {
            return false;
        }

        return true;
    }

    public function diasParaProximaLlamada(int $diasAtraso, array $configuracion): int
    {
        if ($diasAtraso < 1) {
            return 0;
        }

        $config = $this->normalizar($configuracion);

        if ($this->esDiaDeLlamada($diasAtraso, $config)) {
            return 0;
        }

        // ponytail: since calls start daily after grace, remaining days is simply grace + 1 - delay
        return ($config['dias_gracia'] + 1) - $diasAtraso;
    }
}
