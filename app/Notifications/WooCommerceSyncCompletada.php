<?php

namespace App\Notifications;

use App\Mail\WooCommerceSyncExitoMail;
use App\Models\Woocommerce\WoocommerceSyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WooCommerceSyncCompletada extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public WoocommerceSyncLog $log,
        public string $csvPath
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast', 'mail'];

        return $channels;
    }

    public function toMail(object $notifiable): WooCommerceSyncExitoMail
    {
        return (new WooCommerceSyncExitoMail($this->log, $this->csvPath))
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
            'tipo' => 'woocommerce_sync_completada',
            'titulo' => 'Sincronización WooCommerce Completada',
            'mensaje' => "Proceso #{$this->log->id}: {$this->log->procesados} de {$this->log->total_productos} productos procesados.",
            'mensaje_visible' => "Sincronización WooCommerce #{$this->log->id} completada.",
            'sync_log_id' => $this->log->id,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
