<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioLoteEvento extends Model
{
    public const UPDATED_AT = null;

    public const TIPO_LOTE_CREADO = 'lote_creado';

    public const TIPO_SIMULACION_INICIADA = 'simulacion_iniciada';

    public const TIPO_SIMULACION_COMPLETADA = 'simulacion_completada';

    public const TIPO_SIMULACION_FALLIDA = 'simulacion_fallida';

    public const TIPO_ITEM_EDITADO = 'item_editado';

    public const TIPO_ITEM_EXCLUIDO = 'item_excluido';

    public const TIPO_ITEM_REINCLUIDO = 'item_reincluido';

    public const TIPO_REVISION_APROBADA = 'revision_aprobada';

    public const TIPO_REVISION_CANCELADA = 'revision_cancelada';

    public const TIPO_CSV_GENERADO = 'csv_generado';

    public const TIPO_CSV_DESCARGADO = 'csv_descargado';

    public const TIPO_CSV_IMPORTACION_DECLARADA = 'csv_importacion_declarada';

    public const TIPO_CONCILIACION_REGISTRADA = 'conciliacion_registrada';

    public const TIPO_COMPENSACION_SOLICITADA = 'compensacion_solicitada';

    public const TIPO_COMPENSACION_CREADA = 'compensacion_creada';

    protected $table = 'tiendanube_precio_lote_eventos';

    protected $fillable = [
        'lote_id',
        'revision_id',
        'tipo',
        'actor_id',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'lote_id' => 'string',
            'revision_id' => 'integer',
            'actor_id' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLote::class, 'lote_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
