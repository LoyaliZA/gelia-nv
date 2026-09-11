<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeImageImportItem extends Model
{
    protected $table = 'tiendanube_image_import_items';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_REQUIERE_SELECCION = 'requiere_seleccion';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDO = 'omitido';

    protected $fillable = [
        'import_id',
        'filename',
        'relative_path',
        'sku',
        'resolucion_estado',
        'candidatos_json',
        'position',
        'producto_id',
        'excluido',
        'estado',
        'motivo',
        'mensaje',
        'imagen_tn_id',
        'operacion_id',
        'claim_token',
        'claim_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'producto_id' => 'integer',
            'imagen_tn_id' => 'integer',
            'excluido' => 'boolean',
            'candidatos_json' => 'array',
            'claim_expires_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(TiendanubeImageImport::class, 'import_id');
    }
}
