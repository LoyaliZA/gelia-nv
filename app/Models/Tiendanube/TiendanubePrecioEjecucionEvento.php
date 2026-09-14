<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioEjecucionEvento extends Model
{
    public const UPDATED_AT = null;

    public const TIPO_INICIADA = 'iniciada';

    public const TIPO_ITEM_CONFIRMADO = 'item_confirmado';

    public const TIPO_ITEM_CONFLICTO = 'item_conflicto';

    public const TIPO_ITEM_INCIERTO = 'item_incierto';

    public const TIPO_ITEM_FALLIDO = 'item_fallido';

    public const TIPO_ITEM_COINCIDENCIA = 'item_coincidencia_previa';

    public const TIPO_CANCELADA = 'cancelada';

    public const TIPO_SUSPENDIDA = 'suspendida';

    public const TIPO_COMPLETADA = 'completada';

    public const TIPO_REINTENTO = 'reintento';

    protected $table = 'tiendanube_precio_ejecucion_eventos';

    protected $fillable = [
        'ejecucion_id',
        'item_id',
        'tipo',
        'actor_id',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ejecucion_id' => 'string',
            'item_id' => 'integer',
            'actor_id' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioEjecucion::class, 'ejecucion_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
