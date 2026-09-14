<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioImportItem extends Model
{
    public const ESTADO_VALIDO = 'valido';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_EXCLUIDO = 'excluido';

    public const ESTADO_CONFIRMADO = 'confirmado';

    protected $table = 'tiendanube_precio_import_items';

    protected $fillable = [
        'import_id',
        'fila',
        'sku',
        'variante_id',
        'producto_id',
        'destino_tipo',
        'lista_id',
        'valor_raw',
        'valor_decimal',
        'moneda',
        'estado',
        'motivo',
        'valor_anterior',
        'moneda_anterior',
        'seleccionado',
        'candidatos_json',
        'mensaje',
    ];

    protected function casts(): array
    {
        return [
            'fila' => 'integer',
            'variante_id' => 'integer',
            'producto_id' => 'integer',
            'lista_id' => 'integer',
            'valor_decimal' => 'decimal:2',
            'valor_anterior' => 'decimal:2',
            'seleccionado' => 'boolean',
            'candidatos_json' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioImport::class, 'import_id');
    }
}
