<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAtributoValor extends Model
{
    protected $table = 'producto_atributo_valores';

    protected $fillable = [
        'producto_id',
        'atributo_id',
        'opcion_id',
        'valor_texto',
        'valor_entero',
        'valor_decimal',
        'valor_booleano',
        'valor_fecha',
        'unidad_id',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'valor_decimal' => 'decimal:6',
            'valor_booleano' => 'boolean',
            'valor_fecha' => 'date',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function atributo(): BelongsTo
    {
        return $this->belongsTo(Atributo::class, 'atributo_id');
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(AtributoOpcion::class, 'opcion_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_id');
    }
}
