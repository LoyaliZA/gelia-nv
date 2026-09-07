<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoReatencion;
use App\Events\PuntoVenta\TurnoTransferido;
use App\Services\PuntoVenta\Turnos\NotificarTurnoPdvService;
use Illuminate\Events\Dispatcher;

final class NotificarTurnoPdvSubscriber
{
    public function __construct(
        private readonly NotificarTurnoPdvService $notificaciones,
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TurnoAsignado::class => 'handleAsignado',
            TurnoReatencion::class => 'handleReatencion',
            TurnoTransferido::class => 'handleTransferido',
        ];
    }

    public function handleAsignado(TurnoAsignado $event): void
    {
        $this->notificaciones->desdeEvento(
            $event->turno,
            $event->atencion,
            $event->evento,
            $event->sucursalId,
        );
    }

    public function handleReatencion(TurnoReatencion $event): void
    {
        $this->notificaciones->desdeEvento(
            $event->turno,
            $event->atencion,
            $event->evento,
            $event->sucursalId,
        );
    }

    public function handleTransferido(TurnoTransferido $event): void
    {
        $this->notificaciones->desdeEvento(
            $event->turno,
            $event->atencionNueva,
            $event->evento,
            $event->sucursalId,
        );
    }
}
