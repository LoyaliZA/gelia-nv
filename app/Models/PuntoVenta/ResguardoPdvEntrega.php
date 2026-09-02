<?php

namespace App\Models\PuntoVenta;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResguardoPdvEntrega extends Model
{
    public const RELACION_TITULAR = 'titular';

    public const RELACION_TERCERO = 'tercero';

    protected $table = 'pdv_resguardo_entregas';

    protected $fillable = [
        'resguardo_id',
        'pedido_bma_id',
        'relacion',
        'nombre_quien_retira',
        'entregado_por_id',
        'entregado_at',
        'incidencia_autorizada_id',
        'snapshot_json',
        'idempotency_key',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'entregado_at' => 'datetime',
            'snapshot_json' => 'array',
            'version' => 'integer',
        ];
    }

    public function resguardo(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdv::class, 'resguardo_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por_id');
    }

    public function incidenciaAutorizada(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvIncidencia::class, 'incidencia_autorizada_id');
    }

    public function bultos(): BelongsToMany
    {
        return $this->belongsToMany(
            ResguardoPdvBulto::class,
            'pdv_resguardo_entrega_bultos',
            'entrega_id',
            'bulto_id'
        )->withTimestamps();
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(ResguardoPdvEvidencia::class, 'entrega_id');
    }
}
