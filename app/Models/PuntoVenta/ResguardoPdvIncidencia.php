<?php

namespace App\Models\PuntoVenta;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResguardoPdvIncidencia extends Model
{
    public const TIPO_FOLIO_NO_ENCONTRADO = 'folio_no_encontrado';

    public const TIPO_DANO = 'dano';

    public const TIPO_FALTANTE = 'faltante';

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_AUTORIZADA = 'autorizada';

    public const ESTADO_CERRADA = 'cerrada';

    protected $table = 'pdv_resguardo_incidencias';

    protected $fillable = [
        'resguardo_id',
        'bulto_id',
        'tipo',
        'estado',
        'descripcion',
        'reportado_por_id',
        'reportado_at',
        'autorizado_por_id',
        'autorizado_at',
        'motivo_autorizacion',
        'snapshot_json',
        'idempotency_key',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'reportado_at' => 'datetime',
            'autorizado_at' => 'datetime',
            'snapshot_json' => 'array',
            'version' => 'integer',
        ];
    }

    public function resguardo(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdv::class, 'resguardo_id');
    }

    public function bulto(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvBulto::class, 'bulto_id');
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(ResguardoPdvEvidencia::class, 'incidencia_id');
    }
}
