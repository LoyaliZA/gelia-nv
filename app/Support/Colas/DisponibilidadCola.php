<?php

namespace App\Support\Colas;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DisponibilidadCola
{
    public const HEARTBEAT_KEY = 'gelia.queue.heartbeat';

    public const FRESCURA_SEGUNDOS = 45;

    public function puedeEncolar(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }
        if (config('queue.default') === 'sync') {
            return false;
        }
        $latido = Cache::get(self::HEARTBEAT_KEY);
        if ($latido === null) {
            return false;
        }

        return Carbon::parse($latido)->greaterThan(now()->subSeconds(self::FRESCURA_SEGUNDOS));
    }
}
