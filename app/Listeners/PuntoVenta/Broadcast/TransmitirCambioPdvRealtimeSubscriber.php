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
        if ($this->eventoEsDemo($event)) {
            return;
        }

        foreach ($this->mapper->transmisiones($event) as $transmision) {
            broadcast(new CambioPdvBroadcast(
                $transmision['channels'],
                $transmision['envelope'],
            ));
        }
    }

    private function eventoEsDemo(object $event): bool
    {
        foreach (['resguardo', 'turno'] as $propiedad) {
            $modelo = $event->{$propiedad} ?? null;
            if (is_object($modelo) && (bool) ($modelo->es_demo ?? false)) {
                return true;
            }
        }

        return false;
    }
}
