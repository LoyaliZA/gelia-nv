<?php

namespace App\Services\ControlPedidos;

use Carbon\Carbon;

/**
 * Única fuente de cálculo de fecha límite de resguardo/espera.
 * Guarda snapshot en la tarea; cambiar config no altera límites ya persistidos.
 */
class CalcularFechaLimiteResguardoService
{
    public function __construct(
        private CancelacionOperativaConfig $config,
    ) {}

    /**
     * @return array{fecha_limite: Carbon, snapshot: array<string, mixed>}
     */
    public function calcular(?Carbon $desde = null): array
    {
        $zona = $this->config->zonaHoraria();
        $desde ??= Carbon::now($zona);
        $base = $desde->copy()->timezone($zona);
        $dias = $this->config->diasResguardo();
        $tipo = $this->config->diasTipo();

        if ($tipo === CancelacionOperativaConfig::DIAS_HABILES) {
            $limite = $this->sumarDiasHabiles($base, $dias)->endOfDay();
        } else {
            // Default explícito: días naturales.
            $limite = $base->copy()->addDays($dias)->endOfDay();
        }

        return [
            'fecha_limite' => $limite,
            'snapshot' => $this->config->snapshotRegla(),
        ];
    }

    private function sumarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $cursor = $desde->copy();
        $restantes = $dias;
        while ($restantes > 0) {
            $cursor->addDay();
            // ponytail: fin de semana = sábado/domingo; feriados requieren catálogo futuro.
            if (! $cursor->isWeekend()) {
                $restantes--;
            }
        }

        return $cursor;
    }
}
