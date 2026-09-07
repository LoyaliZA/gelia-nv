<?php

namespace App\Notifications\PuntoVenta;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AlertaTurnoPdvNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TIPO_ASIGNADO = 'pdv.turno.asignado';

    public const TIPO_REATENCION = 'pdv.turno.reatencion';

    public const TIPO_TRANSFERIDO = 'pdv.turno.transferido';

    public function __construct(
        public string $tipoAlerta,
        public string $titulo,
        public string $mensajeVisible,
        public int $turnoId,
        public string $folio,
        public int $sucursalId,
        public string $idempotencyKey,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'proceso' => 'Punto de venta',
            'modulo' => 'punto_venta',
            'turno_id' => $this->turnoId,
            'folio' => $this->folio,
            'sucursal_id' => $this->sucursalId,
            'url' => '/punto-venta/turnos/ventas',
            'idempotency_key' => $this->idempotencyKey,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
