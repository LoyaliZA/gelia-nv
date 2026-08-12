<?php

namespace App\Services\Solicitudes;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Destinatarios de alertas TAG/Lista / operativas: auxiliar o encargada del departamento.
 * No incluye Super Admin / Administrador (supervisan por UI; suelen estar en Cedis y otros deptos).
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
        if (!$departamentoId || $permisos === []) {
            return collect();
        }

        return User::permission($permisos)
            ->whereHas('departamentos', function ($query) use ($departamentoId) {
                $query->where('departamentos.id', $departamentoId);
            })
            ->when($excluirUserId !== null, fn ($q) => $q->where('id', '!=', $excluirUserId))
            ->get()
            ->reject(fn (User $u) => $u->hasAnyRole(self::ROLES_SUPERVISION))
            ->values();
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

        return $destinatarios
            ->merge($this->porDepartamento($departamentoId, $permisos, $excluirUserId))
            ->unique('id')
            ->when(
                $excluirUserId !== null,
                fn (Collection $c) => $c->reject(fn (User $u) => (int) $u->id === (int) $excluirUserId)
            )
            ->values();
    }
}
