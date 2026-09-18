<?php

namespace App\Services\Mobile;

use RuntimeException;

class MobileSyncException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $status,
        public array $payload
    ) {
        parent::__construct($payload['code'] ?? 'mobile_sync_error', $status);
    }
}
