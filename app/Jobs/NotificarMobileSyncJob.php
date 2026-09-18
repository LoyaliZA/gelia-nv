<?php

namespace App\Jobs;

use App\Events\MobileSyncDisponibleEvent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class NotificarMobileSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId
    ) {}

    public function handle(): void
    {
        $payload = Cache::pull("mobile_sync_notify:{$this->userId}");
        Cache::forget("mobile_sync_notify_lock:{$this->userId}");

        if (! is_array($payload)) {
            return;
        }

        if (! User::query()->whereKey($this->userId)->exists()) {
            return;
        }

        event(new MobileSyncDisponibleEvent(
            $this->userId,
            (bool) ($payload['requires_bootstrap'] ?? false),
            (int) ($payload['max_seq'] ?? 0)
        ));
    }
}
