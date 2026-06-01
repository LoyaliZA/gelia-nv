<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoBono extends Model
{
    protected $table = 'catalogo_bonos';

    protected $fillable = [
        'nombre',
        'codigo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function puestos(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoPuesto::class, 'catalogo_puesto_bonos', 'catalogo_bono_id', 'catalogo_puesto_id');
    }

    public function colaboradores(): BelongsToMany
    {
        return $this->belongsToMany(RhColaborador::class, 'rh_colaborador_bonos', 'catalogo_bono_id', 'rh_colaborador_id')
            ->withPivot('monto')
            ->withTimestamps();
    }

    public function reglasIncidencia(): HasMany
    {
        return $this->hasMany(CatalogoReglaIncidencia::class, 'catalogo_bono_id');
    }
}
