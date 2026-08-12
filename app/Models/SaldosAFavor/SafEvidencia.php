<?php

namespace App\Models\SaldosAFavor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SafEvidencia extends Model
{
    protected $table = 'saf_evidencias';

    protected $fillable = [
        'saf_credito_id',
        'saf_movimiento_id',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'subido_por_id',
    ];

    protected $appends = ['url'];

    public function credito(): BelongsTo
    {
        return $this->belongsTo(SafCredito::class, 'saf_credito_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(SafMovimiento::class, 'saf_movimiento_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->ruta_archivo
            ? Storage::disk('public')->url($this->ruta_archivo)
            : null;
    }
}
