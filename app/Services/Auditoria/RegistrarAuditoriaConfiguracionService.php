<?php

namespace App\Services\Auditoria;

use App\Models\AuditoriaConfiguracion;
use Illuminate\Support\Facades\Auth;

class RegistrarAuditoriaConfiguracionService
{
    /**
     * Registra un cambio en la configuración.
     *
     * @param string $modulo El módulo afectado (ej. "Usuarios", "Perfil", "Roles").
     * @param string $accion La acción realizada (ej. "Asignación de permisos").
     * @param array|null $detalles Un arreglo con los detalles o snapshot del cambio.
     * @param int|null $targetUserId El ID del usuario al que se le aplica el cambio.
     * @return AuditoriaConfiguracion
     */
    public static function ejecutar(string $modulo, string $accion, ?array $detalles = null, ?int $targetUserId = null): AuditoriaConfiguracion
    {
        return AuditoriaConfiguracion::create([
            'user_id' => Auth::id(),
            'target_user_id' => $targetUserId,
            'modulo' => $modulo,
            'accion' => $accion,
            'detalles' => $detalles,
        ]);
    }
}
