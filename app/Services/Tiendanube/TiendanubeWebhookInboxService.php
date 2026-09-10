<?php

namespace App\Services\Tiendanube;

use App\Jobs\Tiendanube\ProcessTiendanubeWebhook;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TiendanubeWebhookInboxService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistReceived(array $payload, string $payloadHash): TiendanubeWebhookDelivery
    {
        $event = isset($payload['event']) && is_string($payload['event']) ? $payload['event'] : null;
        $resourceId = array_key_exists('id', $payload) ? (string) $payload['id'] : null;
        $storeId = isset($payload['store_id']) ? (int) $payload['store_id'] : null;

        return TiendanubeWebhookDelivery::query()->create([
            'store_id' => $storeId,
            'event' => $event,
            'resource_id' => $resourceId,
            'payload' => $payload,
            'payload_hash' => $payloadHash,
            'hmac_valid' => true,
            'status' => TiendanubeWebhookDelivery::STATUS_RECEIVED,
            'attempts' => 0,
        ]);
    }

    public function tryDispatch(TiendanubeWebhookDelivery $delivery): bool
    {
        $ops = app(TiendanubeOperacionTiendaService::class);
        if ($ops->hayCatalogoSyncActivo($delivery->store_id ? (int) $delivery->store_id : null)) {
            TiendanubeWebhookDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', TiendanubeWebhookDelivery::STATUS_RECEIVED)
                ->update([
                    'status' => TiendanubeWebhookDelivery::STATUS_DEFERRED,
                    'deferred_reason' => 'catalogo_sync',
                    'updated_at' => now(),
                ]);
            $delivery->refresh();

            return false;
        }

        try {
            ProcessTiendanubeWebhook::dispatch($delivery->id);
        } catch (\Throwable $e) {
            Log::warning('Tiendanube webhook: no se pudo despachar a la cola.', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        TiendanubeWebhookDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', TiendanubeWebhookDelivery::STATUS_RECEIVED)
            ->update(['status' => TiendanubeWebhookDelivery::STATUS_QUEUED]);

        $delivery->refresh();

        return true;
    }

    public function tryAcquire(TiendanubeWebhookDelivery $delivery, string $token): bool
    {
        $now = now();
        $leaseSeconds = max(1, (int) config('tiendanube.webhook_lease_seconds', 300));
        $maxAttempts = max(1, (int) config('tiendanube.webhook_max_attempts', 10));

        $affected = TiendanubeWebhookDelivery::query()
            ->whereKey($delivery->id)
            ->where('attempts', '<', $maxAttempts)
            ->where(function ($query) use ($now) {
                $query->whereIn('status', [
                    TiendanubeWebhookDelivery::STATUS_RECEIVED,
                    TiendanubeWebhookDelivery::STATUS_QUEUED,
                    TiendanubeWebhookDelivery::STATUS_RETRY_PENDING,
                ])->orWhere(function ($inner) use ($now) {
                    $inner->where('status', TiendanubeWebhookDelivery::STATUS_PROCESSING)
                        ->whereNotNull('lease_expires_at')
                        ->where('lease_expires_at', '<', $now);
                })->orWhere(function ($inner) {
                    $inner->where('status', TiendanubeWebhookDelivery::STATUS_FAILED);
                });
            })
            ->update([
                'status' => TiendanubeWebhookDelivery::STATUS_PROCESSING,
                'lease_token' => $token,
                'lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                'processing_started_at' => $now,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            return false;
        }

        $delivery->refresh();

        return $delivery->lease_token === $token;
    }

    public function markProcessed(TiendanubeWebhookDelivery $delivery, string $token): bool
    {
        $now = now();

        $affected = TiendanubeWebhookDelivery::query()
            ->whereKey($delivery->id)
            ->where('lease_token', $token)
            ->update([
                'status' => TiendanubeWebhookDelivery::STATUS_PROCESSED,
                'error' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'processed_at' => $now,
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            return false;
        }

        $delivery->refresh();

        return true;
    }

    public function markIgnored(TiendanubeWebhookDelivery $delivery, string $token, ?string $reason = null): bool
    {
        $now = now();

        $affected = TiendanubeWebhookDelivery::query()
            ->whereKey($delivery->id)
            ->where('lease_token', $token)
            ->update([
                'status' => TiendanubeWebhookDelivery::STATUS_IGNORED,
                'error' => $reason,
                'lease_token' => null,
                'lease_expires_at' => null,
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            return false;
        }

        $delivery->refresh();

        return true;
    }

    public function failOrRetry(TiendanubeWebhookDelivery $delivery, string $token, string $error): void
    {
        $maxAttempts = max(1, (int) config('tiendanube.webhook_max_attempts', 10));
        $delivery->refresh();

        if ((int) $delivery->attempts >= $maxAttempts) {
            $this->markFailed($delivery, $token, $error);

            return;
        }

        $this->releaseToRetry($delivery, $token, $error);
    }

    public function markFailed(TiendanubeWebhookDelivery $delivery, string $token, string $error): bool
    {
        $now = now();

        $affected = TiendanubeWebhookDelivery::query()
            ->whereKey($delivery->id)
            ->where('lease_token', $token)
            ->update([
                'status' => TiendanubeWebhookDelivery::STATUS_FAILED,
                'error' => $this->resumirError($error),
                'lease_token' => null,
                'lease_expires_at' => null,
                'failed_at' => $now,
                'next_attempt_at' => null,
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            return false;
        }

        $delivery->refresh();

        return true;
    }

    public function releaseToRetry(TiendanubeWebhookDelivery $delivery, string $token, string $error): bool
    {
        $now = now();
        $base = max(1, (int) config('tiendanube.webhook_backoff_seconds', 60));
        $attempts = max(1, (int) $delivery->attempts);
        $delay = $base * (2 ** min($attempts - 1, 10));

        $affected = TiendanubeWebhookDelivery::query()
            ->whereKey($delivery->id)
            ->where('lease_token', $token)
            ->update([
                'status' => TiendanubeWebhookDelivery::STATUS_RETRY_PENDING,
                'error' => $this->resumirError($error),
                'lease_token' => null,
                'lease_expires_at' => null,
                'next_attempt_at' => $now->copy()->addSeconds($delay),
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            return false;
        }

        $delivery->refresh();

        return true;
    }

    public function scheduleManualRetry(TiendanubeWebhookDelivery $delivery, int $userId): void
    {
        if (! $delivery->puedeReintentar()) {
            throw new RuntimeException('Esta entrega no admite reintento.');
        }

        $now = now();

        $affected = TiendanubeWebhookDelivery::query()
            ->whereKey($delivery->id)
            ->whereIn('status', [
                TiendanubeWebhookDelivery::STATUS_RECEIVED,
                TiendanubeWebhookDelivery::STATUS_QUEUED,
                TiendanubeWebhookDelivery::STATUS_RETRY_PENDING,
                TiendanubeWebhookDelivery::STATUS_FAILED,
            ])
            ->update([
                'status' => TiendanubeWebhookDelivery::STATUS_QUEUED,
                'attempts' => 0,
                'error' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'next_attempt_at' => null,
                'retried_by_user_id' => $userId,
                'retried_at' => $now,
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            throw new RuntimeException('No se pudo reencolar la entrega.');
        }

        $delivery->refresh();

        if (! $this->tryDispatch($delivery) && $delivery->status === TiendanubeWebhookDelivery::STATUS_QUEUED) {
            TiendanubeWebhookDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', TiendanubeWebhookDelivery::STATUS_QUEUED)
                ->update(['status' => TiendanubeWebhookDelivery::STATUS_RECEIVED]);
            $delivery->refresh();
        }
    }

    /**
     * @return list<int>
     */
    public function idsElegiblesParaRecuperar(): array
    {
        $limit = max(1, (int) config('tiendanube.webhook_recover_limit', 50));
        $maxAttempts = max(1, (int) config('tiendanube.webhook_max_attempts', 10));
        $now = now();

        return TiendanubeWebhookDelivery::query()
            ->where('attempts', '<', $maxAttempts)
            ->where(function ($query) use ($now) {
                $query->whereIn('status', [
                    TiendanubeWebhookDelivery::STATUS_RECEIVED,
                    TiendanubeWebhookDelivery::STATUS_QUEUED,
                ])->orWhere(function ($inner) use ($now) {
                    $inner->where('status', TiendanubeWebhookDelivery::STATUS_RETRY_PENDING)
                        ->where(function ($next) use ($now) {
                            $next->whereNull('next_attempt_at')
                                ->orWhere('next_attempt_at', '<=', $now);
                        });
                })->orWhere(function ($inner) use ($now) {
                    $inner->where('status', TiendanubeWebhookDelivery::STATUS_PROCESSING)
                        ->whereNotNull('lease_expires_at')
                        ->where('lease_expires_at', '<', $now);
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    public function recuperarPendientes(): int
    {
        $recuperadas = 0;

        foreach ($this->idsElegiblesParaRecuperar() as $id) {
            try {
                ProcessTiendanubeWebhook::dispatch($id);
                $recuperadas++;
            } catch (\Throwable $e) {
                Log::warning('Tiendanube webhook: recuperador no pudo despachar.', [
                    'delivery_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $recuperadas;
    }

    public function liberarEntregasDiferidas(?int $storeId = null): int
    {
        $query = TiendanubeWebhookDelivery::query()
            ->where('status', TiendanubeWebhookDelivery::STATUS_DEFERRED)
            ->orderBy('id');

        if ($storeId) {
            $query->where(function ($inner) use ($storeId) {
                $inner->where('store_id', $storeId)->orWhereNull('store_id');
            });
        }

        $liberadas = 0;

        foreach ($query->pluck('id') as $id) {
            $affected = TiendanubeWebhookDelivery::query()
                ->whereKey($id)
                ->where('status', TiendanubeWebhookDelivery::STATUS_DEFERRED)
                ->update([
                    'status' => TiendanubeWebhookDelivery::STATUS_RECEIVED,
                    'deferred_reason' => null,
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                continue;
            }

            $delivery = TiendanubeWebhookDelivery::query()->find($id);
            if ($delivery) {
                $this->tryDispatch($delivery);
                $liberadas++;
            }
        }

        return $liberadas;
    }

    private function resumirError(string $error): string
    {
        $error = trim($error);

        return mb_substr($error === '' ? 'Error desconocido al procesar webhook.' : $error, 0, 500);
    }
}
