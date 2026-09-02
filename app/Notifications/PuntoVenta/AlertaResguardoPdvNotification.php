<?php

namespace App\Notifications\PuntoVenta;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AlertaResguardoPdvNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public const TIPO_RECEPCION_ESPERADA = 'pdv.resguardo.recepcion_esperada';

    public const TIPO_RECEPCION_FISICA = 'pdv.resguardo.recepcion_fisica';

    public const TIPO_INCIDENCIA = 'pdv.resguardo.incidencia';

    public const TIPO_ENTREGA = 'pdv.resguardo.entrega';

    public const TIPO_PROXIMO_A_VENCER = 'pdv.resguardo.proximo_a_vencer';

    public const TIPO_VENCIDO = 'pdv.resguardo.vencido';

    public const TIPO_ESCALAMIENTO = 'pdv.resguardo.escalamiento';

    /**
     * @param  array<string, mixed>  $extras
     */
    public function __construct(
        public string $tipoAlerta,
        public string $titulo,
        public string $mensajeVisible,
        public int $resguardoId,
        public string $folio,
        public int $sucursalId,
        public string $idempotencyKey,
        public array $extras = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

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
        return array_merge([
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'proceso' => 'Punto de venta',
            'modulo' => 'punto_venta',
            'resguardo_id' => $this->resguardoId,
            'folio' => $this->folio,
            'sucursal_id' => $this->sucursalId,
            'url' => '/punto-venta/resguardos/'.$this->resguardoId,
            'idempotency_key' => $this->idempotencyKey,
            'fecha' => now()->toDateTimeString(),
        ], $this->extras);
    }
}
