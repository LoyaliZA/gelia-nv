<?php

namespace App\Services\Tiendanube\Gobernador;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Support\Tiendanube\TiendanubeClaseTurno;
use App\Support\Tiendanube\TiendanubePropositoHttp;

/**
 * Anuncia la demanda al entrar y la retira al terminar, cancelar o interrumpir.
 * Con el gobernador apagado no toca Redis ni cambia la clase.
 */
class TiendanubeEmisorHttp
{
    public function __construct(
        private readonly TiendanubeGobernador $gobernador,
        private readonly TiendanubeContextoHttpActual $contexto,
    ) {}

    public function pendiente(TiendanubeClaseTurno $clase): bool
    {
        return (bool) config('tiendanube.gobernador_activo', false)
            && $this->contexto->clase !== $clase->value;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function bajo(TiendanubeClaseTurno $clase, string $operacionId, string $fase, callable $callback): mixed
    {
        if (! config('tiendanube.gobernador_activo', false)) {
            return $callback();
        }

        $storeId = (int) (TiendanubeConfiguracion::obtener()->store_id ?? 0);
        if ($storeId > 0) {
            $this->gobernador->anunciarDemanda($storeId, $clase->value, $operacionId);
        }

        try {
            return $this->contexto->usar(
                $operacionId,
                $clase->value,
                TiendanubePropositoHttp::Intento->value,
                $fase,
                $callback,
            );
        } finally {
            if ($storeId > 0) {
                $this->gobernador->retirarDemanda($storeId, $clase->value, $operacionId);
            }
        }
    }
}
