<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoFrecuenciaPago extends Model
{
    public const INMEDIATO = 1;

    public const DIARIO = 2;

    public const SEMANAL = 3;

    public const QUINCENAL = 4;

    public const PERSONALIZADO = 5;

    protected $table = 'contabilidad_catalogo_frecuencia_pago';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function plataformas(): HasMany
    {
        return $this->hasMany(PlataformaPago::class, 'frecuencia_pago_id');
    }
}
