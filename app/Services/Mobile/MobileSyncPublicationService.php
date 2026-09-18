<?php

namespace App\Services\Mobile;

use App\Jobs\NotificarMobileSyncJob;
use App\Models\Cliente;
use App\Models\MobileSyncPublication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MobileSyncPublicationService
{
    public const AGGREGATE_CLIENTE = 'cliente';

    public function __construct(
        protected MobileClienteAlcanceService $alcance
    ) {}

    public function maxSeq(): int
    {
        return (int) (MobileSyncPublication::query()->max('seq') ?? 0);
    }

    public function minSeq(): int
    {
        return (int) (MobileSyncPublication::query()->min('seq') ?? 0);
    }

    public function publicarCliente(
        Cliente $cliente,
        ?array $scopeAntes,
        bool $fueCreado = false,
        bool $fueEliminado = false,
        ?string $batchId = null
    ): MobileSyncPublication {
        $scopeDespues = $fueEliminado ? null : $this->alcance->snapshotAlcance($cliente);

        return $this->insertarPublicacion(
            $cliente->id,
            $scopeAntes,
            $scopeDespues,
            $fueCreado,
            $fueEliminado,
            $batchId
        );
    }

    /**
     * @param  array<int, array{cliente_id: int, scope_before: ?array, scope_after: ?array, created?: bool, deleted?: bool}>  $cambios
     */
    public function publishBatch(array $cambios): void
    {
        if ($cambios === []) {
            return;
        }

        $batchId = (string) Str::uuid();
        $destinatarios = [];
        $ultimoSeq = 0;

        foreach ($cambios as $cambio) {
            $publicacion = $this->insertarPublicacion(
                (int) $cambio['cliente_id'],
                $cambio['scope_before'] ?? null,
                $cambio['scope_after'] ?? null,
                (bool) ($cambio['created'] ?? false),
                (bool) ($cambio['deleted'] ?? false),
                $batchId
            );
            $ultimoSeq = $publicacion->seq;
            $destinatarios = array_merge(
                $destinatarios,
                $publicacion->grant_user_ids ?? [],
                $publicacion->revoke_user_ids ?? []
            );
        }

        $this->notificar(array_unique(array_map('intval', $destinatarios)), $ultimoSeq, false);
    }

    /**
     * @param  Collection<int, Cliente>  $antesPorId
     * @param  Collection<int, Cliente>  $despuesPorId
     */
    public function publishDiffColeccion(Collection $antesPorId, Collection $despuesPorId): void
    {
        $cambios = [];

        foreach ($despuesPorId as $id => $cliente) {
            $antes = $antesPorId->get($id);
            $scopeAntes = $antes ? $this->alcance->snapshotAlcance($antes) : null;
            $scopeDespues = $this->alcance->snapshotAlcance($cliente);

            if ($antes && $scopeAntes === $scopeDespues && ! $this->payloadPudoCambiar($antes, $cliente)) {
                continue;
            }

            $cambios[] = [
                'cliente_id' => (int) $id,
                'scope_before' => $scopeAntes,
                'scope_after' => $scopeDespues,
                'created' => $antes === null,
            ];
        }

        foreach ($antesPorId as $id => $cliente) {
            if ($despuesPorId->has($id)) {
                continue;
            }

            $cambios[] = [
                'cliente_id' => (int) $id,
                'scope_before' => $this->alcance->snapshotAlcance($cliente),
                'scope_after' => null,
                'deleted' => true,
            ];
        }

        $this->publishBatch($cambios);
    }

    private function insertarPublicacion(
        int $clienteId,
        ?array $scopeAntes,
        ?array $scopeDespues,
        bool $fueCreado,
        bool $fueEliminado,
        ?string $batchId
    ): MobileSyncPublication {
        $viewersAntes = $this->alcance->idsConVisibilidadDesdeScope($scopeAntes);
        $viewersDespues = $fueEliminado ? [] : $this->alcance->idsConVisibilidadDesdeScope($scopeDespues);
        $revoke = array_values(array_diff($viewersAntes, $viewersDespues));
        $grant = array_values($viewersDespues);

        if ($fueEliminado) {
            $operation = 'deleted';
        } elseif ($fueCreado || $scopeAntes === null) {
            $operation = 'granted';
        } elseif ($grant === [] && $revoke !== []) {
            $operation = 'revoked';
        } else {
            $operation = 'updated';
        }

        $publicacion = MobileSyncPublication::create([
            'batch_id' => $batchId,
            'aggregate_type' => self::AGGREGATE_CLIENTE,
            'aggregate_id' => $clienteId,
            'operation' => $operation,
            'grant_user_ids' => $grant,
            'revoke_user_ids' => $revoke,
            'scope_before' => $scopeAntes,
            'scope_after' => $scopeDespues,
            'occurred_at' => now(),
        ]);

        $this->notificar(array_unique(array_merge($grant, $revoke)), (int) $publicacion->seq, false);

        return $publicacion;
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function notificar(array $userIds, int $maxSeq, bool $requiresBootstrap): void
    {
        $segundos = max(1, (int) config('mobile.notify_debounce_seconds', 4));

        foreach (array_unique(array_filter($userIds)) as $userId) {
            $userId = (int) $userId;
            $payloadKey = "mobile_sync_notify:{$userId}";
            $prev = Cache::get($payloadKey, ['requires_bootstrap' => false, 'max_seq' => 0]);
            Cache::put($payloadKey, [
                'requires_bootstrap' => $requiresBootstrap || ($prev['requires_bootstrap'] ?? false),
                'max_seq' => max((int) ($prev['max_seq'] ?? 0), $maxSeq),
            ], $segundos + 2);

            $lockKey = "mobile_sync_notify_lock:{$userId}";
            if (! Cache::add($lockKey, 1, $segundos)) {
                continue;
            }

            NotificarMobileSyncJob::dispatch($userId)->delay(now()->addSeconds($segundos));
        }
    }

    private function payloadPudoCambiar(Cliente $antes, Cliente $despues): bool
    {
        $campos = [
            'nombre', 'nombre_razon_social', 'rfc', 'lista_actual_id', 'vendedor_id',
            'vendedor_original_id', 'catalogo_tipo_cliente_id', 'es_inactivo', 'es_heredado',
            'lista_bloqueada', 'monto_credito_autorizado', 'dias_credito', 'fecha_inicio_credito',
            'codigo_postal', 'regimen_fiscal', 'correo_electronico', 'uso_factura',
            'direccion_fiscal', 'colonia_fiscal', 'municipio_fiscal', 'estado_fiscal', 'pais_fiscal',
            'numero_cliente',
        ];

        foreach ($campos as $campo) {
            if ($antes->{$campo} != $despues->{$campo}) {
                return true;
            }
        }

        return false;
    }
}
