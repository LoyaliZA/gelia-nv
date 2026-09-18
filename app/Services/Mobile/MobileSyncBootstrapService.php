<?php

namespace App\Services\Mobile;

use App\Models\MobileBootstrapSnapshot;
use App\Models\MobileBootstrapSnapshotItem;
use App\Models\MobileDevice;
use App\Models\MobileUserSyncState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileSyncBootstrapService
{
    public function __construct(
        protected MobileClienteAlcanceService $alcance,
        protected MobileClienteSerializerService $serializer,
        protected MobileSyncPublicationService $publicaciones,
        protected MobileScopeVersionService $scopeVersion
    ) {}

    /**
     * @return array{snapshot_id: string, scope_version: string, publish_seq_at_start: int, total_items: int, status: string}
     */
    public function crear(User $user, MobileDevice $device): array
    {
        $scopeVersion = $this->scopeVersion->compute($user);

        $snapshot = DB::transaction(function () use ($user, $device, $scopeVersion) {
            MobileBootstrapSnapshot::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    MobileBootstrapSnapshot::STATUS_BUILDING,
                    MobileBootstrapSnapshot::STATUS_READY,
                ])
                ->update(['status' => MobileBootstrapSnapshot::STATUS_EXPIRED]);

            $seqInicio = $this->publicaciones->maxSeq();

            $snapshot = MobileBootstrapSnapshot::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'scope_version' => $scopeVersion,
                'publish_seq_at_start' => $seqInicio,
                'total_items' => 0,
                'max_cliente_id' => null,
                'status' => MobileBootstrapSnapshot::STATUS_BUILDING,
                'expires_at' => now()->addHours((int) config('mobile.bootstrap_ttl_hours', 24)),
            ]);

            $total = 0;
            $maxId = null;

            $this->alcance->queryPara($user)
                ->with(['listaDescuento', 'vendedor', 'tipo'])
                ->orderBy('id')
                ->chunkById(200, function ($clientes) use ($user, $snapshot, &$total, &$maxId) {
                    foreach ($clientes as $cliente) {
                        MobileBootstrapSnapshotItem::query()->create([
                            'snapshot_id' => $snapshot->id,
                            'cliente_id' => $cliente->id,
                            'payload' => $this->serializer->serializar($cliente, $user),
                        ]);
                        $total++;
                        $maxId = $cliente->id;
                    }
                });

            $snapshot->update([
                'total_items' => $total,
                'max_cliente_id' => $maxId,
                'status' => MobileBootstrapSnapshot::STATUS_READY,
            ]);

            MobileUserSyncState::query()->updateOrCreate(
                ['mobile_device_id' => $device->id],
                [
                    'scope_version' => $scopeVersion,
                    'bootstrap_snapshot_id' => $snapshot->id,
                    'last_sync_at' => now(),
                ]
            );

            return $snapshot->fresh();
        });

        return [
            'snapshot_id' => $snapshot->id,
            'scope_version' => $snapshot->scope_version,
            'publish_seq_at_start' => $snapshot->publish_seq_at_start,
            'total_items' => $snapshot->total_items,
            'status' => $snapshot->status,
        ];
    }

    /**
     * @return array{data: array<int, mixed>, has_more: bool, last_cliente_id: ?int, snapshot_id: string, total_items: int}
     */
    public function pagina(User $user, string $snapshotId, int $afterClienteId, int $limit): array
    {
        $snapshot = $this->snapshotDeUsuario($user, $snapshotId);
        $this->asegurarListo($snapshot);

        $items = MobileBootstrapSnapshotItem::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('cliente_id', '>', $afterClienteId)
            ->orderBy('cliente_id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items = $items->take($limit);
        }

        return [
            'snapshot_id' => $snapshot->id,
            'total_items' => $snapshot->total_items,
            'data' => $items->map(fn (MobileBootstrapSnapshotItem $item) => $this->payloadComoArreglo($item->payload))->values()->all(),
            'has_more' => $hasMore,
            'last_cliente_id' => $items->last()?->cliente_id,
        ];
    }

    /**
     * @return array{cursor: int, scope_version: string, snapshot_id: string}
     */
    public function completar(
        User $user,
        MobileDevice $device,
        string $snapshotId,
        int $itemsCount,
        ?int $maxClienteId
    ): array {
        $snapshot = $this->snapshotDeUsuario($user, $snapshotId);
        $this->asegurarListo($snapshot);

        $scopeActual = $this->scopeVersion->compute($user);
        if ($snapshot->scope_version !== $scopeActual) {
            throw new MobileSyncException(409, [
                'code' => 'scope_changed',
                'action' => 'bootstrap',
                'scope_version' => $scopeActual,
            ]);
        }

        if ((int) $snapshot->total_items !== $itemsCount
            || (int) ($snapshot->max_cliente_id ?? 0) !== (int) ($maxClienteId ?? 0)
        ) {
            throw new MobileSyncException(422, [
                'code' => 'bootstrap_mismatch',
                'expected_items' => $snapshot->total_items,
                'expected_max_cliente_id' => $snapshot->max_cliente_id,
                'received_items' => $itemsCount,
                'received_max_cliente_id' => $maxClienteId,
            ]);
        }

        $snapshot->update(['status' => MobileBootstrapSnapshot::STATUS_COMPLETED]);

        MobileUserSyncState::query()->updateOrCreate(
            ['mobile_device_id' => $device->id],
            [
                'scope_version' => $scopeActual,
                'cursor_seq' => $snapshot->publish_seq_at_start,
                'bootstrap_snapshot_id' => $snapshot->id,
                'last_sync_at' => now(),
            ]
        );

        return [
            'cursor' => $snapshot->publish_seq_at_start,
            'scope_version' => $scopeActual,
            'snapshot_id' => $snapshot->id,
        ];
    }

    public function invalidarDelUsuario(User $user): void
    {
        MobileBootstrapSnapshot::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                MobileBootstrapSnapshot::STATUS_BUILDING,
                MobileBootstrapSnapshot::STATUS_READY,
            ])
            ->update(['status' => MobileBootstrapSnapshot::STATUS_EXPIRED]);
    }

    private function snapshotDeUsuario(User $user, string $snapshotId): MobileBootstrapSnapshot
    {
        $snapshot = MobileBootstrapSnapshot::query()
            ->where('id', $snapshotId)
            ->where('user_id', $user->id)
            ->first();

        if (! $snapshot) {
            throw new MobileSyncException(404, [
                'code' => 'snapshot_not_found',
                'action' => 'bootstrap',
            ]);
        }

        return $snapshot;
    }

    private function asegurarListo(MobileBootstrapSnapshot $snapshot): void
    {
        if ($snapshot->status === MobileBootstrapSnapshot::STATUS_EXPIRED
            || $snapshot->expires_at->isPast()
        ) {
            if ($snapshot->status !== MobileBootstrapSnapshot::STATUS_EXPIRED) {
                $snapshot->update(['status' => MobileBootstrapSnapshot::STATUS_EXPIRED]);
            }

            throw new MobileSyncException(410, [
                'code' => 'snapshot_expired',
                'action' => 'bootstrap',
            ]);
        }

        if ($snapshot->status !== MobileBootstrapSnapshot::STATUS_READY
            && $snapshot->status !== MobileBootstrapSnapshot::STATUS_COMPLETED
        ) {
            throw new MobileSyncException(409, [
                'code' => 'snapshot_not_ready',
                'status' => $snapshot->status,
            ]);
        }
    }

    /**
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    private function payloadComoArreglo($payload): array
    {
        while (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        return is_array($payload) ? $payload : [];
    }
}
