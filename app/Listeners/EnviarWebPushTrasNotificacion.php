<?php

namespace App\Listeners;

use App\Services\WebPush\ConstruirPayloadDesdeNotificacionService;
use App\Services\WebPush\EnviarWebPushService;
use Illuminate\Database\Eloquent\Model;
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

        // DatabaseChannel devuelve el modelo DatabaseNotification, no el array de toDatabase().
        $data = $this->extraerDatosNotificacion($event->response);
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

    /**
     * @param  mixed  $response
     * @return array<string, mixed>|null
     */
    private function extraerDatosNotificacion(mixed $response): ?array
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof Model) {
            $data = $response->getAttribute('data');

            return is_array($data) ? $data : null;
        }

        return null;
    }
}
