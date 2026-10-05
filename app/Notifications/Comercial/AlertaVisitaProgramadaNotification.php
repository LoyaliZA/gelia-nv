<?php

namespace App\Notifications\Comercial;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AlertaVisitaProgramadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TIPO_LLEGADA = 'comercial.visita_programada.llegada';

    public function __construct(
        public string $tipoAlerta,
        public string $titulo,
        public string $mensajeVisible,
        public int $visitaId,
        public int $sucursalId,
        public string $idempotencyKey,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];
        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'proceso' => 'Operaciones',
            'modulo' => 'visitas_programadas',
            'visita_programada_id' => $this->visitaId,
            'sucursal_id' => $this->sucursalId,
            'url' => '/visitas-programadas?vista=historial',
            'idempotency_key' => $this->idempotencyKey,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
