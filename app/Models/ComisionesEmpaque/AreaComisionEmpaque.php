<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_areas';

    protected $fillable = ['codigo', 'nombre', 'activo', 'sistema', 'observaciones'];

    protected $casts = ['activo' => 'boolean', 'sistema' => 'boolean'];

    public function tarifas(): HasMany
    {
        return $this->hasMany(TarifaComisionEmpaque::class, 'area_id');
    }

    public function repartos(): HasMany
    {
        return $this->hasMany(RepartoComisionEmpaque::class, 'area_id');
    }
}
