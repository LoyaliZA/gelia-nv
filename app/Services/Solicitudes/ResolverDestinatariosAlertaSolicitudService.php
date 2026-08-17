<?php

namespace App\Services\Solicitudes;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Destinatarios de alertas TAG/Lista / operativas:
 * - Auxiliar/encargada del departamento (permisos del flujo).
 * - Super Admin / Administrador globales (supervisión; patrón histórico GELIA).
 */
class ResolverDestinatariosAlertaSolicitudService
{
    public const ROLES_SUPERVISION = ['Super Admin', 'Administrador'];

    /**
     * @param  list<string>  $permisos
     * @return Collection<int, User>
     */
    public function porDepartamento(
        ?int $departamentoId,
        array $permisos,
        ?int $excluirUserId = null,
    ): Collection {
        if ($permisos === []) {
            return collect();
        }

        $operativos = collect();
        if ($departamentoId) {
            $candidatos = User::permission($permisos)
                ->whereHas('departamentos', function ($query) use ($departamentoId) {
                    $query->where('departamentos.id', $departamentoId);
                })
                ->when($excluirUserId !== null, fn ($q) => $q->where('id', '!=', $excluirUserId))
                ->get();

            // Evita duplicar a quien ya entra como supervisor global.
            $operativos = $candidatos
                ->reject(fn (User $u) => $u->hasAnyRole(self::ROLES_SUPERVISION))
                ->values();
        }

        $supervisores = User::role(self::ROLES_SUPERVISION)
            ->when($excluirUserId !== null, fn ($q) => $q->where('id', '!=', $excluirUserId))
            ->get();

        return $operativos->merge($supervisores)->unique('id')->values();
    }

    /**
     * @param  list<string>  $permisos
     * @return Collection<int, User>
     */
    public function conVendedorOpcional(
        ?int $departamentoId,
        array $permisos,
        bool $incluirVendedor = false,
        ?User $vendedor = null,
        ?int $excluirUserId = null,
    ): Collection {
        $destinatarios = collect();

        if ($incluirVendedor && $vendedor) {
            $destinatarios->push($vendedor);
        }

        $merged = $destinatarios
            ->merge($this->porDepartamento($departamentoId, $permisos, $excluirUserId))
            ->unique('id')
            ->when(
                $excluirUserId !== null,
                fn (Collection $c) => $c->reject(fn (User $u) => (int) $u->id === (int) $excluirUserId)
            )
            ->values();

        return $merged;
    }
}
