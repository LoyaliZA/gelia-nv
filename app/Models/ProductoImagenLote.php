<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoImagenLote extends Model
{
    protected $table = 'producto_imagen_lotes';

    protected $fillable = [
        'uuid',
        'user_id',
        'estado',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(ProductoImagenLoteArchivo::class, 'lote_id')->orderBy('indice');
    }
}
