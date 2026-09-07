<?php

namespace App\Listeners\PuntoVenta\Broadcast;

use App\Events\PuntoVenta\Broadcast\CambioPdvBroadcast;
use App\Support\PuntoVenta\Broadcast\PdvRealtimeMapper;
use Illuminate\Events\Dispatcher;

final class TransmitirCambioPdvRealtimeSubscriber
{
    public function __construct(
        private readonly PdvRealtimeMapper $mapper,
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        $suscripciones = [];

        foreach (PdvRealtimeMapper::eventosSoportados() as $clase) {
            $suscripciones[$clase] = 'handle';
        }

        return $suscripciones;
    }

    public function handle(object $event): void
    {
        foreach ($this->mapper->transmisiones($event) as $transmision) {
            broadcast(new CambioPdvBroadcast(
                $transmision['channels'],
                $transmision['envelope'],
            ));
        }
    }
}
