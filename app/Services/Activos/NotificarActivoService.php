<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\User;
use App\Notifications\AlertaActivo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotificarActivoService
{
    public function __construct(
        private DestinatariosNotificacionActivoService $destinatarios,
    ) {}

    public function ejecutar(
        Activo $activo,
        string $tipoAlerta,
        string $mensaje,
        ?User $actor = null,
        ?User $usuarioDestino = null,
        ?User $responsableAnterior = null,
        array $extras = [],
        bool $usarDedupe = false,
    ): void {
        if ($usarDedupe) {
            $cacheKey = "activo_notif:{$activo->id}:{$tipoAlerta}:" . now()->toDateString();
            if (Cache::has($cacheKey)) {
                return;
            }
            Cache::put($cacheKey, true, now()->endOfDay());
        }

        $activo->loadMissing(['tipo', 'departamento', 'responsable']);

        $usuarios = $this->destinatarios->ejecutar($activo, $usuarioDestino, $responsableAnterior);

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new AlertaActivo($activo, $tipoAlerta, $mensaje, $extras));
    }
}
