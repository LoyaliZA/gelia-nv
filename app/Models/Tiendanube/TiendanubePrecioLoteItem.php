<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioLoteItem extends Model
{
    public const ESTADO_SIN_CAMBIO = 'sin_cambio';

    public const ESTADO_CON_CAMBIO = 'con_cambio';

    public const ESTADO_BLOQUEADA = 'bloqueada';

    public const ESTADO_EXCLUIDA = 'excluida';

    public const ESTADO_ERROR = 'error';

    protected $table = 'tiendanube_precio_lote_items';

    protected $fillable = [
        'revision_id',
        'producto_id',
        'variante_id',
        'producto_nombre',
        'variante_sku',
        'variante_atributos',
        'imagen_url',
        'espejo_existe',
        'valores_anteriores',
        'fuentes_snapshot',
        'resultado_calculado',
        'ajustes_manuales',
        'resultado_final',
        'validaciones',
        'errores',
        'estado_fila',
        'excluido',
        'exclusion_motivo',
    ];

    protected function casts(): array
    {
        return [
            'revision_id' => 'integer',
            'producto_id' => 'integer',
            'variante_id' => 'integer',
            'variante_atributos' => 'array',
            'espejo_existe' => 'boolean',
            'valores_anteriores' => 'array',
            'fuentes_snapshot' => 'array',
            'resultado_calculado' => 'array',
            'ajustes_manuales' => 'array',
            'resultado_final' => 'array',
            'validaciones' => 'array',
            'errores' => 'array',
            'excluido' => 'boolean',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLoteRevision::class, 'revision_id');
    }
}
