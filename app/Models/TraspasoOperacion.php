<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraspasoOperacion extends Model
{
    protected $table = 'traspaso_operaciones';

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lineas' => 'array'];
    }
}
