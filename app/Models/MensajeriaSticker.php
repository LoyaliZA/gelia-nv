<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MensajeriaSticker extends Model
{
    protected $table = 'mensajeria_stickers';

    protected $fillable = [
        'user_id',
        'ruta',
        'mime',
        'tamano',
    ];

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }
}
