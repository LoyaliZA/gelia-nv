<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioConciliacion extends Model
{
    public const EVIDENCIA_LECTURA_API = 'lectura_api';

    public const EVIDENCIA_EXPORT_DECLARADO = 'export_declarado';

    public const EVIDENCIA_MANUAL_DECLARADO = 'manual_declarado';

    public const RESULTADO_COINCIDE = 'coincide';

    public const RESULTADO_DIFIERE = 'difiere';

    public const RESULTADO_NO_RESTAURABLE = 'no_restaurable';

    protected $table = 'tiendanube_precio_conciliaciones';

    protected $fillable = [
        'lote_id',
        'revision_id',
        'item_id',
        'variante_id',
        'campo',
        'valor_operacion',
        'valor_remoto',
        'valor_export',
        'evidencia_tipo',
        'resultado',
        'explicacion',
        'actor_id',
        'evidencia_at',
    ];

    protected function casts(): array
    {
        return [
            'lote_id' => 'string',
            'revision_id' => 'integer',
            'item_id' => 'integer',
            'variante_id' => 'integer',
            'actor_id' => 'integer',
            'evidencia_at' => 'datetime',
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
