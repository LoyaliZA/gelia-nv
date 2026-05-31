<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MensajeAdjunto extends Model
{
    protected $table = 'mensaje_adjuntos';

    protected $fillable = [
        'mensaje_id',
        'ruta',
        'thumbnail_ruta',
        'nombre_original',
        'mime',
        'tamano',
        'duracion_seg',
        'metadata',
        'contenido_indexado',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'tamano' => 'integer',
            'duracion_seg' => 'integer',
        ];
    }

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(Mensaje::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }

    public function thumbnailUrl(): ?string
    {
        if (!$this->thumbnail_ruta) {
            return null;
        }

        return Storage::disk('public')->url($this->thumbnail_ruta);
    }
}
