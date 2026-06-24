<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoEstatusPago extends Model
{
    public const PENDIENTE = 1;

    public const TRANSFERIDO = 2;

    protected $table = 'contabilidad_catalogo_estatus_pago';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'estatus_pago_id');
    }
}
