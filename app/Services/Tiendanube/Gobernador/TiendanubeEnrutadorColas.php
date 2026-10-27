<?php

namespace App\Services\Tiendanube\Gobernador;

use App\Jobs\Tiendanube\Precios\ProcesarPrecioEjecucionJob;
use App\Support\Tiendanube\TiendanubeClaseTurno;
use DateTimeInterface;

/**
 * Con colas_dedicadas en falso el trabajo queda en la conexión y la cola default.
 * La conexión `tiendanube` existe para una activación posterior y no reemplaza
 * el retry_after global de `database`.
 */
class TiendanubeEnrutadorColas
{
    public function conexion(): string
    {
        if (config('tiendanube.colas_dedicadas', false)) {
            return 'tiendanube';
        }

        return (string) config('queue.default', 'database');
    }

    public function cola(TiendanubeClaseTurno $clase): string
    {
        if (! config('tiendanube.colas_dedicadas', false)) {
            return 'default';
        }

        return match ($clase) {
            TiendanubeClaseTurno::Interactivo => 'tiendanube_interactiva',
            TiendanubeClaseTurno::Recuperacion => 'tiendanube_recuperacion',
            TiendanubeClaseTurno::Publicacion => 'tiendanube_publicacion',
            TiendanubeClaseTurno::Catalogo => 'tiendanube_catalogo',
        };
    }

    public function despacharPrecio(string $ejecucionId, ?DateTimeInterface $delay = null): void
    {
        $job = new ProcesarPrecioEjecucionJob($ejecucionId);
        if (config('tiendanube.colas_dedicadas', false)) {
            $job->onConnection($this->conexion());
            $job->onQueue($this->cola(TiendanubeClaseTurno::Publicacion));
        }
        if ($delay !== null) {
            $job->delay($delay);
        }

        dispatch($job);
    }
}
