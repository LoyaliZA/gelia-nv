<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePrecioLote extends Model
{
    use HasUuids;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_SIMULADO = 'simulado';

    public const ESTADO_APROBADO = 'aprobado';

    public const ESTADO_CANCELADO = 'cancelado';

    public const ORIGEN_CALCULO = 'calculo';

    public const ORIGEN_RESTAURACION = 'restauracion';

    protected $table = 'tiendanube_precio_lotes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'user_id',
        'config_generation',
        'api_version',
        'motor_contract_version',
        'moneda',
        'selection_id',
        'selection_version',
        'selection_generacion',
        'selection_modo',
        'selection_filtros',
        'estado',
        'origen',
        'lote_origen_id',
        'motivo_restauracion',
        'revision_actual_numero',
        'fecha_lectura',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'user_id' => 'integer',
            'config_generation' => 'integer',
            'selection_version' => 'integer',
            'selection_generacion' => 'integer',
            'selection_filtros' => 'array',
            'revision_actual_numero' => 'integer',
            'fecha_lectura' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(TiendanubePrecioLoteRevision::class, 'lote_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(TiendanubePrecioLoteEvento::class, 'lote_id');
    }

    public function loteOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'lote_origen_id');
    }

    public function compensaciones(): HasMany
    {
        return $this->hasMany(self::class, 'lote_origen_id');
    }

    public function ejecuciones(): HasMany
    {
        return $this->hasMany(TiendanubePrecioEjecucion::class, 'lote_id');
    }

    public function csvArtefactos(): HasMany
    {
        return $this->hasMany(TiendanubePrecioCsvArtefacto::class, 'lote_id');
    }

    public function conciliaciones(): HasMany
    {
        return $this->hasMany(TiendanubePrecioConciliacion::class, 'lote_id');
    }

    public function revisionActual(): ?TiendanubePrecioLoteRevision
    {
        return $this->revisiones()
            ->where('numero', $this->revision_actual_numero)
            ->first();
    }
}
