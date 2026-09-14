<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePrecioCsvPerfil extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_VALIDADO = 'validado';

    protected $table = 'tiendanube_precio_csv_perfiles';

    protected $fillable = [
        'store_id',
        'version',
        'estado',
        'encabezados_canonicos',
        'delimiter',
        'decimal_sep',
        'encoding',
        'presets',
        'preset_default',
        'plantilla_path',
        'plantilla_fecha',
        'contract_version',
        'validado_por',
        'validado_at',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'version' => 'integer',
            'encabezados_canonicos' => 'array',
            'presets' => 'array',
            'plantilla_fecha' => 'datetime',
            'validado_por' => 'integer',
            'validado_at' => 'datetime',
        ];
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function artefactos(): HasMany
    {
        return $this->hasMany(TiendanubePrecioCsvArtefacto::class, 'perfil_id');
    }

    public function estaValidado(): bool
    {
        return $this->estado === self::ESTADO_VALIDADO;
    }
}
