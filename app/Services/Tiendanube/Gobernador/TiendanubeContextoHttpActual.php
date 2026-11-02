<?php

namespace App\Services\Tiendanube\Gobernador;

class TiendanubeContextoHttpActual
{
    public ?string $operacionId = null;

    public ?string $clase = null;

    public ?string $proposito = null;

    public ?string $fase = null;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function usar(string $operacionId, string $clase, string $proposito, string $fase, callable $callback): mixed
    {
        $previo = [$this->operacionId, $this->clase, $this->proposito, $this->fase];
        $this->operacionId = $operacionId;
        $this->clase = $clase;
        $this->proposito = $proposito;
        $this->fase = $fase;

        try {
            return $callback();
        } finally {
            [$this->operacionId, $this->clase, $this->proposito, $this->fase] = $previo;
        }
    }
}
