<?php

namespace App\Models\SaldosAFavor;

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafComprobanteCaja extends Model
{
    public const ESTADO_GENERADO = 'generado';
    public const ESTADO_PENDIENTE_FIRMA = 'pendiente_firma';
    public const ESTADO_FIRMADO_PENDIENTE_REVISION = 'firmado_pendiente_revision';
    public const ESTADO_REVISADO = 'revisado';
    public const ESTADO_INCIDENCIA = 'incidencia';
    public const ESTADO_CANCELADO = 'cancelado';
    public const ESTADO_REIMPRESO = 'reimpreso';

    protected $table = 'saf_comprobantes_caja';

    protected $fillable = [
        'folio',
        'cliente_id',
        'saf_cuenta_id',
        'referencia_venta',
        'sucursal',
        'caja',
        'saldo_anterior',
        'monto_aplicado',
        'saldo_restante',
        'creditos_detalle',
        'estado',
        'perfil_impresion',
        'generado_por_id',
        'departamento_id',
        'logo_key',
        'firmado_at',
        'aplicado_at',
        'revisado_at',
        'revisado_por_id',
        'ruta_evidencia_firmada',
        'es_reimpresion',
    ];

    protected $casts = [
        'saldo_anterior' => 'decimal:2',
        'monto_aplicado' => 'decimal:2',
        'saldo_restante' => 'decimal:2',
        'creditos_detalle' => 'array',
        'firmado_at' => 'datetime',
        'aplicado_at' => 'datetime',
        'revisado_at' => 'datetime',
        'es_reimpresion' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(SafCuenta::class, 'saf_cuenta_id');
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function reimpresiones(): HasMany
    {
        return $this->hasMany(SafComprobanteReimpresion::class, 'saf_comprobante_caja_id');
    }
}
