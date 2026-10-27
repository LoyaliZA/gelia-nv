<?php

namespace App\Services\Tiendanube\Gobernador;

use App\Exceptions\Tiendanube\TiendanubeApiRateLimitException;
use App\Exceptions\Tiendanube\TiendanubePresupuestoLocalException;
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

        $mantenerDemanda = false;
        try {
            return $this->contexto->usar(
                $operacionId,
                $clase->value,
                TiendanubePropositoHttp::Intento->value,
                $fase,
                $callback,
            );
        } catch (TiendanubePresupuestoLocalException $e) {
            $mantenerDemanda = true;
            $this->renovarDemanda($storeId, $clase, $operacionId, $e->turno->reanudarDespuesAt);
            throw $e;
        } catch (TiendanubeApiRateLimitException $e) {
            $mantenerDemanda = true;
            $reanudar = $storeId > 0
                ? (int) $this->gobernador->espera($storeId, $operacionId)['reanudar_despues_at']
                : 0;
            $this->renovarDemanda($storeId, $clase, $operacionId, $reanudar);
            throw $e;
        } finally {
            if (! $mantenerDemanda && $storeId > 0) {
                $this->gobernador->retirarDemanda($storeId, $clase->value, $operacionId);
            }
        }
    }

    private function renovarDemanda(int $storeId, TiendanubeClaseTurno $clase, string $operacionId, int $reanudar): void
    {
        if ($storeId <= 0) {
            return;
        }

        $margen = max(1000, (int) config('tiendanube.gobernador_demanda_margen_ms', 30_000));
        $ahora = $this->gobernador->marcaTemporal();
        $this->gobernador->anunciarDemanda($storeId, $clase->value, $operacionId, max($ahora, $reanudar) + $margen);
    }
}
