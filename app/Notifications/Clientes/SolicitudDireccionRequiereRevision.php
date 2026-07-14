<?php

namespace App\Notifications\Clientes;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SolicitudDireccionRequiereRevision extends Notification
{
    use Queueable;

    public function __construct(
        public int $pendientes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'solicitud_direccion_revision',
            'titulo' => 'Solicitudes de dirección pendientes',
            'mensaje' => "Hay {$this->pendientes} solicitud(es) de dirección por revisar.",
            'url' => route('admin.clientes.direcciones.solicitudes.index'),
            'pendientes' => $this->pendientes,
        ];
    }
}
