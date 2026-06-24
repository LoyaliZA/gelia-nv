<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoTransaccion extends Model
{
    public const VENTA = 1;

    public const CONTRACARGO = 2;

    public const REEMBOLSO = 3;

    protected $table = 'contabilidad_catalogo_tipos_transaccion';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'tipo_transaccion_id');
    }
}
