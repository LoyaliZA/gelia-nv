<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileSyncPublication extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'seq';

    protected $fillable = [
        'batch_id',
        'aggregate_type',
        'aggregate_id',
        'operation',
        'grant_user_ids',
        'revoke_user_ids',
        'scope_before',
        'scope_after',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'grant_user_ids' => 'array',
            'revoke_user_ids' => 'array',
            'scope_before' => 'array',
            'scope_after' => 'array',
            'occurred_at' => 'datetime',
            'aggregate_id' => 'integer',
        ];
    }

    public function incluyeUsuario(int $userId): bool
    {
        return in_array($userId, $this->idsEnteros($this->grant_user_ids), true)
            || in_array($userId, $this->idsEnteros($this->revoke_user_ids), true);
    }

    /**
     * @param  mixed  $ids
     * @return array<int, int>
     */
    private function idsEnteros($ids): array
    {
        return array_values(array_map('intval', $ids ?? []));
    }
}
