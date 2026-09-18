<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileBootstrapSnapshotItem extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'cliente_id';

    protected $fillable = [
        'snapshot_id',
        'cliente_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'cliente_id' => 'integer',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(MobileBootstrapSnapshot::class, 'snapshot_id');
    }
}
