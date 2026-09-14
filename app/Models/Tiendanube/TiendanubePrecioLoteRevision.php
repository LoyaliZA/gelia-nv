<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TiendanubePrecioLoteRevision extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_SIMULADO = 'simulado';

    public const ESTADO_APROBADO = 'aprobado';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $table = 'tiendanube_precio_lote_revisiones';

    protected $fillable = [
        'lote_id',
        'numero',
        'estado',
        'checksum',
        'huella_conflicto_remoto',
        'regla_id',
        'regla_version_id',
        'definicion',
        'resumen',
        'aprobado_por',
        'aprobado_at',
    ];

    protected function casts(): array
    {
        return [
            'lote_id' => 'string',
            'numero' => 'integer',
            'regla_id' => 'integer',
            'regla_version_id' => 'integer',
            'definicion' => 'array',
            'resumen' => 'array',
            'aprobado_por' => 'integer',
            'aprobado_at' => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLote::class, 'lote_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TiendanubePrecioLoteItem::class, 'revision_id');
    }

    public function simulacion(): HasOne
    {
        return $this->hasOne(TiendanubePrecioLoteSimulacion::class, 'revision_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
