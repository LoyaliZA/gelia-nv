<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeLectura extends Model
{
    public $timestamps = false;

    protected $table = 'mensaje_lecturas';

    protected $fillable = [
        'mensaje_id',
        'user_id',
        'leido_at',
    ];

    protected function casts(): array
    {
        return [
            'leido_at' => 'datetime',
        ];
    }

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(Mensaje::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
