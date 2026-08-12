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
            // #region agent log
            $this->agentDebugLog('A', 'ResolverDestinatarios:porDepartamento', 'early empty', [
                'departamento_id' => $departamentoId,
                'permisos' => $permisos,
                'excluir' => $excluirUserId,
            ]);
            // #endregion

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

        $final = $operativos->merge($supervisores)->unique('id')->values();

        // #region agent log
        $this->agentDebugLog('A', 'ResolverDestinatarios:porDepartamento', 'resolved', [
            'departamento_id' => $departamentoId,
            'permisos' => $permisos,
            'excluir' => $excluirUserId,
            'operativos_ids' => $operativos->pluck('id')->values()->all(),
            'supervisores_ids' => $supervisores->pluck('id')->values()->all(),
            'final_ids' => $final->pluck('id')->values()->all(),
            'super_admin_in_final' => $final->contains(fn (User $u) => $u->hasRole('Super Admin')),
            'runId' => 'post-fix',
        ]);
        // #endregion

        return $final;
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

        // #region agent log
        $this->agentDebugLog('B', 'ResolverDestinatarios:conVendedorOpcional', 'merged', [
            'incluir_vendedor' => $incluirVendedor,
            'vendedor_id' => $vendedor?->id,
            'final_ids' => $merged->pluck('id')->values()->all(),
            'super_admin_in_final' => $merged->contains(fn (User $u) => $u->hasRole('Super Admin')),
            'runId' => 'post-fix',
        ]);
        // #endregion

        return $merged;
    }

    // #region agent log
    private function agentDebugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $line = json_encode([
            'sessionId' => '80055b',
            'runId' => $data['runId'] ?? 'pre-fix',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);

        @file_put_contents(base_path('.cursor/debug-80055b.log'), $line . "\n", FILE_APPEND | LOCK_EX);
    }
    // #endregion
}
