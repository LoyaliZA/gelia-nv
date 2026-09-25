<?php

namespace App\Services\Mobile;

use App\Models\Cliente;
use App\Models\MobileDevice;
use App\Models\MobileSyncPublication;
use App\Models\MobileUserSyncState;
use App\Models\User;

class MobileSyncChangesService
{
    public function __construct(
        protected MobileSyncPublicationService $publicaciones,
        protected MobileClienteSerializerService $serializer,
        protected MobileScopeVersionService $scopeVersion,
        protected MobileClienteAlcanceService $alcance
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function head(User $user): array
    {
        return [
            'max_seq' => $this->publicaciones->maxSeq(),
            'scope_version' => $this->scopeVersion->compute($user),
            'authorized_total' => $this->authorizedTotal($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(User $user, MobileDevice $device, int $cursor, int $limit): array
    {
        $maxSeq = $this->publicaciones->maxSeq();
        $minSeq = $this->publicaciones->minSeq();

        if ($cursor > 0 && $minSeq > 0 && $cursor < ($minSeq - 1)) {
            throw new MobileSyncException(409, [
                'code' => 'cursor_expired',
                'action' => 'bootstrap',
                'min_seq' => $minSeq,
            ]);
        }

        $limit = max(1, min($limit, (int) config('mobile.changes_page_size', 200)));

        $ventana = MobileSyncPublication::query()
            ->where('seq', '>', $cursor)
            ->orderBy('seq')
            ->limit($limit)
            ->get();

        $scopeVersion = $this->scopeVersion->compute($user);

        if ($ventana->isEmpty()) {
            $this->actualizarEstado($device, $scopeVersion);

            return [
                'scope_version' => $scopeVersion,
                'first_seq' => null,
                'last_seq' => null,
                'next_cursor' => $cursor,
                'has_more' => false,
                'authorized_total' => $this->authorizedTotal($user),
                'events' => [],
            ];
        }

        $firstSeq = (int) $ventana->first()->seq;
        $lastSeq = (int) $ventana->last()->seq;

        $clientes = Cliente::withTrashed()
            ->with(['listaDescuento', 'vendedor', 'tipo'])
            ->whereIn('id', $ventana->pluck('aggregate_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $events = [];
        foreach ($ventana as $publicacion) {
            if (! $publicacion->incluyeUsuario($user->id)) {
                continue;
            }

            $events[] = $this->mapearEvento($publicacion, $user, $clientes);
        }

        $this->actualizarEstado($device, $scopeVersion);

        return [
            'scope_version' => $scopeVersion,
            'first_seq' => $firstSeq,
            'last_seq' => $lastSeq,
            'next_cursor' => $lastSeq,
            'has_more' => $ventana->count() === $limit && $lastSeq < $maxSeq,
            'authorized_total' => $this->authorizedTotal($user),
            'events' => $events,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cliente>  $clientes
     * @return array<string, mixed>
     */
    private function mapearEvento(MobileSyncPublication $publicacion, User $user, $clientes): array
    {
        $evento = [
            'seq' => (int) $publicacion->seq,
            'operation' => $publicacion->operation,
            'aggregate_type' => $publicacion->aggregate_type,
            'aggregate_id' => (int) $publicacion->aggregate_id,
            'scope_before' => $publicacion->scope_before,
            'scope_after' => $publicacion->scope_after,
        ];

        $enGrant = in_array($user->id, array_map('intval', $publicacion->grant_user_ids ?? []), true);
        $enRevoke = in_array($user->id, array_map('intval', $publicacion->revoke_user_ids ?? []), true);

        if ($enRevoke && ! $enGrant) {
            $evento['operation'] = $publicacion->operation === 'deleted' ? 'deleted' : 'revoked';

            return $evento;
        }

        $cliente = $clientes->get($publicacion->aggregate_id);
        if ($cliente && ! $cliente->trashed()) {
            $evento['data'] = $this->serializer->serializar($cliente, $user);
        }

        return $evento;
    }

    private function authorizedTotal(User $user): int
    {
        return $this->alcance->queryPara($user)->count();
    }

    private function actualizarEstado(MobileDevice $device, string $scopeVersion): void
    {
        MobileUserSyncState::query()->updateOrCreate(
            ['mobile_device_id' => $device->id],
            [
                'scope_version' => $scopeVersion,
                'last_sync_at' => now(),
            ]
        );
    }
}
