<?php

namespace App\Models\Escalonamiento;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalonamientoImportacion extends Model
{
    protected $table = 'escalonamiento_importaciones';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'user_id',
        'nombre_archivo',
        'tipo_documento',
        'hash',
        'ruta',
        'estado',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoPeriodo::class, 'escalonamiento_periodo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function filas(): HasMany
    {
        return $this->hasMany(EscalonamientoImportacionFila::class, 'escalonamiento_importacion_id');
    }
}
