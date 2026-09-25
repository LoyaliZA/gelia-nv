<?php

namespace Tests\Unit\Notifications;

use App\Services\WebPush\ConstruirPayloadDesdeNotificacionService;
use Tests\TestCase;

class DestinoNotificacionPushTest extends TestCase
{
    public function test_solicitud_abre_busqueda_y_no_el_listado_con_folio(): void
    {
        $payload = app(ConstruirPayloadDesdeNotificacionService::class)->desdeArray([
            'solicitud_id' => 42,
            'tipo' => 'nueva',
            'titulo' => 'Solicitud',
            'mensaje_visible' => 'Nueva',
        ]);

        $this->assertSame(url('/solicitudes?q=42'), $payload['url']);
    }

    public function test_ticket_de_listado_abre_el_registro(): void
    {
        $payload = app(ConstruirPayloadDesdeNotificacionService::class)->desdeArray([
            'ticket_id' => 9,
            'url' => '/soporte/agente/tickets',
            'titulo' => 'Ticket',
        ]);

        $this->assertSame(url('/soporte/agente/tickets/9'), $payload['url']);
    }

    public function test_activo_abre_la_ficha(): void
    {
        $payload = app(ConstruirPayloadDesdeNotificacionService::class)->desdeArray([
            'activo_id' => 3,
            'titulo' => 'Activo',
        ]);

        $this->assertSame(url('/activos/3'), $payload['url']);
    }

    public function test_resguardo_conserva_su_url(): void
    {
        $payload = app(ConstruirPayloadDesdeNotificacionService::class)->desdeArray([
            'modulo' => 'punto_venta',
            'resguardo_id' => 15,
            'url' => '/punto-venta/resguardos/15',
            'titulo' => 'Resguardo',
        ]);

        $this->assertSame(url('/punto-venta/resguardos/15'), $payload['url']);
    }
}
