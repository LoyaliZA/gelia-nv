<?php

namespace App\Services\ControlPedidos;

/**
 * Ajusta costo_envio al entrar/salir de un match de reexpedición sin doble suma.
 */
class AplicarCostoReexpedicion
{
    /**
     * @return array{costo_envio: float, costo_aplicado: float}
     */
    public static function siguiente(float|int|string|null $costoEnvioActual, float|int|string|null $costoPrevioAplicado, float|int|string|null $nuevoCostoAdicional): array
    {
        $envio = (float) ($costoEnvioActual ?? 0);
        $previo = (float) ($costoPrevioAplicado ?? 0);
        $nuevo = max(0.0, (float) ($nuevoCostoAdicional ?? 0));

        $base = $envio - $previo;
        if ($base < 0) {
            $base = 0.0;
        }

        return [
            'costo_envio' => round($base + $nuevo, 2),
            'costo_aplicado' => round($nuevo, 2),
        ];
    }
}
