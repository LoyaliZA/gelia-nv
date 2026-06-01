<?php

namespace App\Listeners;

use App\Services\WebPush\ConstruirPayloadDesdeNotificacionService;
use App\Services\WebPush\EnviarWebPushService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class EnviarWebPushTrasNotificacion
{
    public function __construct(
        private EnviarWebPushService $webPush,
        private ConstruirPayloadDesdeNotificacionService $payloadBuilder,
    ) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (!$this->webPush->estaConfigurado()) {
            return;
        }

        $data = $event->response;
        if (!is_array($data)) {
            return;
        }

        try {
            $payload = $this->payloadBuilder->desdeArray($data);
            $this->webPush->enviarAUsuario($event->notifiable, $payload);
        } catch (\Throwable $e) {
            Log::error('[WebPush] Error tras notificación database', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
