<?php

namespace App\Services\Mensajeria;

use App\Events\MensajeEnviado;
use App\Events\MensajeRecibidoUsuario;
use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\User;
use App\Services\WebPush\EnviarWebPushService;

class NotificarMensajeEnviadoService
{
    public function __construct(
        private EnviarWebPushService $webPush,
    ) {}

    public function ejecutar(Conversacion $conversacion, User $remitente, array $mensajeFormateado): void
    {
        broadcast(new MensajeEnviado($conversacion->id, $mensajeFormateado))->toOthers();

        $participantes = ConversacionParticipante::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('user_id', '!=', $remitente->id)
            ->pluck('user_id');

        foreach ($participantes as $userId) {
            broadcast(new MensajeRecibidoUsuario($userId, $mensajeFormateado))->toOthers();
        }

        $this->enviarPushMensaje($conversacion, $remitente, $mensajeFormateado, $participantes);
    }

    private function enviarPushMensaje(
        Conversacion $conversacion,
        User $remitente,
        array $mensaje,
        $participanteIds
    ): void {
        if (!$this->webPush->estaConfigurado() || $participanteIds->isEmpty()) {
            return;
        }

        $nombre = $mensaje['user']['name'] ?? $remitente->name ?? 'Contacto';
        $texto = $mensaje['contenido'] ?? null;
        if (!$texto && !empty($mensaje['tipo'])) {
            $texto = match ($mensaje['tipo']) {
                'imagen' => '📷 Imagen',
                'video' => '🎬 Video',
                'audio' => '🎤 Audio',
                'archivo' => '📎 Archivo',
                default => 'Nuevo mensaje',
            };
        }

        $this->webPush->enviarAUsuarios($participanteIds, [
            'title' => "Mensaje de {$nombre}",
            'body' => mb_substr(trim((string) $texto) ?: 'Nuevo mensaje', 0, 180),
            'url' => '/mensajeria?conversacion=' . $conversacion->id,
            'tag' => 'mensaje-conv-' . $conversacion->id,
            'tipo' => 'mensajeria',
            'data' => [
                'conversacion_id' => $conversacion->id,
                'mensaje_id' => $mensaje['id'] ?? null,
            ],
        ]);
    }
}
