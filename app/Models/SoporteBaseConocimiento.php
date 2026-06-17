<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoporteBaseConocimiento extends Model
{
    use HasFactory;

    protected $table = 'soporte_base_conocimientos';

    protected $fillable = [
        'titulo',
        'contenido',
        'modulo_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(SoporteCatalogoModulo::class, 'modulo_id');
    }
}
