<?php

namespace App\Notifications;

use App\Mail\WooCommerceSyncFalloMail;
use App\Models\Woocommerce\WoocommerceSyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WooCommerceSyncFallida extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public function __construct(public WoocommerceSyncLog $log) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): WooCommerceSyncFalloMail
    {
        return (new WooCommerceSyncFalloMail($this->log))
            ->to($notifiable->email);
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->construirPayload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->construirPayload());
    }

    private function construirPayload(): array
    {
        return [
            'modulo' => 'woocommerce',
            'tipo' => 'woocommerce_sync_fallida',
            'titulo' => 'Sincronización WooCommerce Fallida',
            'mensaje' => "Proceso #{$this->log->id} finalizó con estado: {$this->log->estado}.",
            'mensaje_visible' => "Error en sincronización WooCommerce #{$this->log->id}.",
            'sync_log_id' => $this->log->id,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
