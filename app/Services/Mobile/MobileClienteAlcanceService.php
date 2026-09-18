<?php

namespace App\Services\Mobile;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class MobileClienteAlcanceService
{
    /**
     * @return array{vendedor_id: ?int, vendedor_original_id: ?int, es_inactivo: bool, lista_actual_id: ?int, catalogo_tipo_cliente_id: ?int}
     */
    public function snapshotAlcance(Cliente $cliente): array
    {
        return $this->snapshotDesdeArray([
            'vendedor_id' => $cliente->vendedor_id,
            'vendedor_original_id' => $cliente->vendedor_original_id,
            'es_inactivo' => $cliente->es_inactivo,
            'lista_actual_id' => $cliente->lista_actual_id,
            'catalogo_tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
        ]);
    }

    /**
     * @return array{vendedor_id: ?int, vendedor_original_id: ?int, es_inactivo: bool, lista_actual_id: ?int, catalogo_tipo_cliente_id: ?int}
     */
    public function snapshotDesdeOriginales(Cliente $cliente): array
    {
        return $this->snapshotDesdeArray($cliente->getOriginal());
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array{vendedor_id: ?int, vendedor_original_id: ?int, es_inactivo: bool, lista_actual_id: ?int, catalogo_tipo_cliente_id: ?int}
     */
    private function snapshotDesdeArray(array $attrs): array
    {
        return [
            'vendedor_id' => ! empty($attrs['vendedor_id']) ? (int) $attrs['vendedor_id'] : null,
            'vendedor_original_id' => ! empty($attrs['vendedor_original_id']) ? (int) $attrs['vendedor_original_id'] : null,
            'es_inactivo' => (bool) ($attrs['es_inactivo'] ?? false),
            'lista_actual_id' => ! empty($attrs['lista_actual_id']) ? (int) $attrs['lista_actual_id'] : null,
            'catalogo_tipo_cliente_id' => ! empty($attrs['catalogo_tipo_cliente_id'])
                ? (int) $attrs['catalogo_tipo_cliente_id']
                : null,
        ];
    }

    public function tieneAccesoMovil(User $user): bool
    {
        return $this->puede($user, 'clientes.ver') || $this->puede($user, 'mis_clientes.gestionar');
    }

    public function puedeAcceder(User $user, Cliente $cliente): bool
    {
        if ($cliente->trashed()) {
            return false;
        }

        if ($this->puede($user, 'clientes.ver')) {
            return true;
        }

        if (! $this->puede($user, 'mis_clientes.gestionar')) {
            return false;
        }

        return (int) $cliente->vendedor_id === (int) $user->id
            || (int) $cliente->vendedor_original_id === (int) $user->id;
    }

    public function queryPara(User $user): Builder
    {
        $query = Cliente::query()->orderBy('id');

        if ($this->puede($user, 'clientes.ver')) {
            return $query;
        }

        if ($this->puede($user, 'mis_clientes.gestionar')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('vendedor_id', $user->id)
                    ->orWhere('vendedor_original_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function tipoAlcance(User $user): string
    {
        if ($this->puede($user, 'clientes.ver')) {
            return 'full';
        }

        if ($this->puede($user, 'mis_clientes.gestionar')) {
            return 'vendedor';
        }

        return 'none';
    }

    /**
     * @return array{grant: array<int, int>, revoke: array<int, int>}
     */
    public function resolverDestinatarios(?array $scopeAntes, ?array $scopeDespues, bool $eliminado = false): array
    {
        $antes = $eliminado ? $this->idsConVisibilidadDesdeScope($scopeAntes) : $this->idsConVisibilidadDesdeScope($scopeAntes);
        $despues = $eliminado ? [] : $this->idsConVisibilidadDesdeScope($scopeDespues);

        return [
            'grant' => array_values(array_diff($despues, $eliminado ? $antes : [])),
            'revoke' => array_values(array_diff($antes, $despues)),
        ];
    }

    /**
     * Usuarios que deben conservar (o recibir) el registro ahora.
     *
     * @return array<int, int>
     */
    public function idsConVisibilidadDesdeScope(?array $scope): array
    {
        if ($scope === null) {
            return [];
        }

        $ids = $this->idsAccesoCompleto();
        $vendedores = array_unique(array_filter([
            $scope['vendedor_id'] ?? null,
            $scope['vendedor_original_id'] ?? null,
        ]));

        foreach ($vendedores as $vendedorId) {
            $vendedorId = (int) $vendedorId;
            if ($vendedorId <= 0 || in_array($vendedorId, $ids, true)) {
                continue;
            }

            if ($this->usuarioTieneAlcanceVendedor($vendedorId)) {
                $ids[] = $vendedorId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, int>
     */
    public function idsAccesoCompleto(): array
    {
        return Cache::remember('mobile.ids_acceso_completo', 30, function () {
            $porPermiso = [];
            $superAdmins = [];

            try {
                $porPermiso = User::permission('clientes.ver')->pluck('id')->all();
            } catch (\Throwable) {
                $porPermiso = [];
            }

            try {
                $superAdmins = User::role('Super Admin')->pluck('id')->all();
            } catch (\Throwable) {
                $superAdmins = [];
            }

            return array_values(array_unique(array_map('intval', array_merge($porPermiso, $superAdmins))));
        });
    }

    private function usuarioTieneAlcanceVendedor(int $userId): bool
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return false;
        }

        return $this->puede($user, 'mis_clientes.gestionar');
    }

    public function puede(User $user, string $permiso): bool
    {
        if ($user->permissions()->where('permissions.name', $permiso)->exists()) {
            return true;
        }

        try {
            return $user->can($permiso);
        } catch (\Throwable) {
            try {
                return $user->hasRole('Super Admin');
            } catch (\Throwable) {
                return false;
            }
        }
    }
}
