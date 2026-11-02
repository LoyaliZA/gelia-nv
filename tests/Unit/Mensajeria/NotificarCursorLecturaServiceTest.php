<?php

namespace Tests\Unit\Mensajeria;

use App\Events\ConversacionLeida;
use App\Services\Mensajeria\NotificarCursorLecturaService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificarCursorLecturaServiceTest extends TestCase
{
    public function test_avisa_al_lector_y_a_cada_autor_en_un_solo_evento(): void
    {
        Event::fake([ConversacionLeida::class]);

        $servicio = new NotificarCursorLecturaService;
        $servicio->ejecutar(4, 9, '2026-11-02T18:00:00-06:00', [
            3 => [
                ['id' => 8, 'estado_lectura' => 'leido'],
                ['id' => 9, 'estado_lectura' => 'entregado'],
            ],
            9 => [
                ['id' => 10, 'estado_lectura' => 'leido'],
            ],
        ]);

        Event::assertDispatchedTimes(ConversacionLeida::class, 2);

        Event::assertDispatched(ConversacionLeida::class, function (ConversacionLeida $evento) {
            return $evento->userId === 9
                && $evento->broadcastAs() === 'conversacion.leida'
                && $evento->broadcastOn()[0]->name === 'private-App.Models.User.9'
                && $evento->payload['alcance'] === 'lector'
                && $evento->payload['unread_count'] === 0
                && $evento->payload['conversacion_id'] === 4;
        });

        Event::assertDispatched(ConversacionLeida::class, function (ConversacionLeida $evento) {
            return $evento->userId === 3
                && $evento->payload['alcance'] === 'autor'
                && $evento->payload['mensajes'][0]['estado_lectura'] === 'leido'
                && $evento->payload['mensajes'][1]['estado_lectura'] === 'entregado';
        });
    }
}
