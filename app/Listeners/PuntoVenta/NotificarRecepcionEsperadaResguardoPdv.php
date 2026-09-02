<?php

namespace App\Listeners\PuntoVenta;

use App\Events\PuntoVenta\RecepcionEsperadaPdvCreada;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Services\PuntoVenta\Resguardos\CrearRecepcionEsperadaPdvService;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;

class NotificarRecepcionEsperadaResguardoPdv
{
    public function __construct(
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    public function handle(RecepcionEsperadaPdvCreada $event): void
    {
        $clave = ResguardoPdvEvento::query()
            ->where('resguardo_id', $event->resguardo->id)
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA)
            ->value('idempotency_key');

        if (! is_string($clave) || $clave === '') {
            $clave = CrearRecepcionEsperadaPdvService::claveIdempotencia($event->pedidoBmaId, $event->sucursalId);
        }

        $this->notificaciones->recepcionEsperada(
            $event->resguardo,
            $event->sucursalId,
            $clave,
        );
    }
}
