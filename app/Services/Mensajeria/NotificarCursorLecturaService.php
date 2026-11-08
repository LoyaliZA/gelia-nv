<?php

namespace App\Services\Mensajeria;

use App\Events\ConversacionLeida;

class NotificarCursorLecturaService
{
    /**
     * @param  array<int, array<int, array{id: int, estado_lectura: string}>>  $porAutor
     */
    public function ejecutar(int $conversacionId, int $lectorId, string $ultimoLeidoAt, array $porAutor): void
    {
        broadcast(new ConversacionLeida($lectorId, [
            'alcance' => 'lector',
            'conversacion_id' => $conversacionId,
            'lector_id' => $lectorId,
            'ultimo_leido_at' => $ultimoLeidoAt,
            'unread_count' => 0,
        ]));

        foreach ($porAutor as $autorId => $info) {
            $autorId = (int) $autorId;
            if ($autorId === $lectorId) {
                continue;
            }

            $esLista = array_is_list($info);
            $mensajes = $esLista ? $info : ($info['mensajes'] ?? []);
            $leidoHasta = $esLista ? null : ($info['leido_hasta_id'] ?? null);
            if ($mensajes === [] && ! $leidoHasta) {
                continue;
            }

            broadcast(new ConversacionLeida($autorId, [
                'alcance' => 'autor',
                'conversacion_id' => $conversacionId,
                'lector_id' => $lectorId,
                'ultimo_leido_at' => $ultimoLeidoAt,
                'mensajes' => array_values($mensajes),
                'leido_hasta_id' => $leidoHasta,
                'estado_lectura' => $esLista ? null : ($info['estado_lectura'] ?? null),
            ]));
        }
    }
}
