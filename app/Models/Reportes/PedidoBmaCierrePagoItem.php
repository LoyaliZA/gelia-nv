<?php

namespace App\Models\Reportes;

use App\Models\CatalogoBanco;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaCierrePagoItem extends Model
{
    protected $table = 'pedido_bma_cierre_pago_items';

    protected $fillable = [
        'pedido_bma_cierre_pago_id',
        'pedido_bma_pago_id',
        'numero_exhibicion',
        'monto_snapshot',
        'forma_pago_snapshot',
        'catalogo_banco_id',
        'banco_snapshot',
        'referencia_snapshot',
        'fecha_pago_snapshot',
        'estado_revision_snapshot',
        'activo_para_cobertura_snapshot',
        'nombre_archivo_snapshot',
        'mime_type_snapshot',
        'tamano_bytes_snapshot',
        'ruta_archivo_snapshot',
        'reemplaza_pago_id',
        'capturado_por_id',
        'capturado_at_snapshot',
        'revisado_por_id',
        'revisado_at_snapshot',
        'motivo_rechazo_snapshot',
        'admin_estado',
        'admin_confirmado_por_id',
        'admin_confirmado_at',
        'admin_error_comentario',
        'admin_error_evidencia_ruta',
        'admin_error_evidencia_nombre',
        'admin_error_reportado_por_id',
        'admin_error_reportado_at',
    ];

    protected $casts = [
        'numero_exhibicion' => 'integer',
        'monto_snapshot' => 'decimal:2',
        'fecha_pago_snapshot' => 'datetime',
        'activo_para_cobertura_snapshot' => 'boolean',
        'tamano_bytes_snapshot' => 'integer',
        'capturado_at_snapshot' => 'datetime',
        'revisado_at_snapshot' => 'datetime',
        'admin_confirmado_at' => 'datetime',
        'admin_error_reportado_at' => 'datetime',
    ];

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaCierrePago::class, 'pedido_bma_cierre_pago_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaPago::class, 'pedido_bma_pago_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function capturadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturado_por_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function reemplazaPago(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaPago::class, 'reemplaza_pago_id');
    }

    public function adminConfirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_confirmado_por_id');
    }

    public function adminErrorReportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_error_reportado_por_id');
    }
}
