<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class MobileScopeVersionService
{
    public function compute(User $user): string
    {
        $permisos = $this->nombresPermisos($user)
            ->intersect($this->relevantPermissions())
            ->sort()
            ->values()
            ->all();

        if ($this->esSuperAdmin($user)) {
            array_unshift($permisos, '__super_admin__');
        }

        $payload = [
            'permisos' => array_values(array_unique($permisos)),
            'field_policy_version' => (int) config('mobile.field_policy_version', 1),
            'serializer_version' => (int) config('mobile.serializer_version', 1),
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * @return array<int, string>
     */
    public function relevantPermissions(): array
    {
        return array_values(array_unique(array_merge(
            config('mobile.permisos_relevantes') ?: ['clientes.ver', 'mis_clientes.gestionar', 'cobranza.editar_credito'],
            config('mobile.permisos_alcance') ?: ['clientes.ver', 'mis_clientes.gestionar'],
        )));
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function nombresPermisos(User $user)
    {
        $ids = DB::table('model_has_permissions')
            ->where('model_id', $user->id)
            ->where('model_type', $user->getMorphClass())
            ->pluck('permission_id');

        return Permission::query()->whereIn('id', $ids)->pluck('name');
    }

    public function permisosRelevantesCambiaron(array $antes, array $despues): bool
    {
        $relevantes = $this->relevantPermissions();
        $antes = array_values(array_intersect($antes, $relevantes));
        $despues = array_values(array_intersect($despues, $relevantes));
        sort($antes);
        sort($despues);

        return $antes !== $despues;
    }

    public function invalidarCacheUsuariosAccesoCompleto(): void
    {
        Cache::forget('mobile.ids_acceso_completo');
    }

    private function esSuperAdmin(User $user): bool
    {
        try {
            return $user->hasRole('Super Admin');
        } catch (\Throwable) {
            return false;
        }
    }
}
