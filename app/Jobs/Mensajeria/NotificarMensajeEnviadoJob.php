<?php

namespace App\Jobs\Mensajeria;

use App\Models\Conversacion;
use App\Models\User;
use App\Services\Mensajeria\NotificarMensajeEnviadoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotificarMensajeEnviadoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $conversacionId,
        public int $remitenteId,
        public array $mensaje,
    ) {}

    public function handle(NotificarMensajeEnviadoService $service): void
    {
        $conversacion = Conversacion::query()->find($this->conversacionId);
        $remitente = User::query()->find($this->remitenteId);
        if (! $conversacion || ! $remitente) {
            return;
        }

        $service->difundir($conversacion, $remitente, $this->mensaje);
    }
}
