<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medida';

    protected $fillable = [
        'nombre',
        'simbolo',
        'dimension',
        'factor_base',
        'unidad_base_id',
        'decimales',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'factor_base' => 'decimal:8',
            'estado' => 'boolean',
        ];
    }

    public function unidadBase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'unidad_base_id');
    }

    public function derivadas(): HasMany
    {
        return $this->hasMany(self::class, 'unidad_base_id');
    }
}
