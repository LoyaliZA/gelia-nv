<?php

namespace App\Models\ComisionesEmpaque;

use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadLineaComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_unidad_lineas';

    protected $fillable = ['unidad_id', 'catalogo_tipo_caja_id', 'cantidad', 'es_bolsa'];

    protected $casts = ['cantidad' => 'integer', 'es_bolsa' => 'boolean'];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoCajaPedido::class, 'catalogo_tipo_caja_id');
    }
}
