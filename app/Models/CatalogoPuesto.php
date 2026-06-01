<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoPuesto extends Model
{
    protected $table = 'catalogo_puestos';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(RhColaborador::class, 'catalogo_puesto_id');
    }

    public function bonos(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoBono::class, 'catalogo_puesto_bonos', 'catalogo_puesto_id', 'catalogo_bono_id');
    }
}
