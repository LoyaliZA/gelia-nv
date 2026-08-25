<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaCaratula extends Model
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_GENERADA = 'GENERADA';

    public const ESTADO_COLOCADA = 'COLOCADA';

    public const ESTADO_INVALIDADA = 'INVALIDADA';

    public const COBRO_PAGADO = 'PAGADO';

    public const COBRO_POR_COBRAR = 'POR_COBRAR';

    protected $table = 'pedido_bma_caratulas';

    protected $fillable = [
        'pedido_bma_tarea_preparacion_id',
        'pedido_bma_id',
        'version',
        'destinatario_nombre',
        'destinatario_telefono',
        'municipio_destino',
        'direccion_referencia',
        'catalogo_paqueteria_id',
        'modalidad_cobro',
        'documento_identificacion_id',
        'documento_remision_id',
        'ruta_pdf',
        'hash_sha256',
        'estado',
        'generada_por_id',
        'generada_at',
        'colocada_por_id',
        'colocada_at',
        'motivo_regeneracion',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'generada_at' => 'datetime',
            'colocada_at' => 'datetime',
        ];
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaPreparacion::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function paqueteria(): BelongsTo
    {
        return $this->belongsTo(CatalogoPaqueteriaPedido::class, 'catalogo_paqueteria_id');
    }

    public function documentoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaDocumento::class, 'documento_identificacion_id');
    }

    public function documentoRemision(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaDocumento::class, 'documento_remision_id');
    }

    public function generadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generada_por_id');
    }

    public function colocadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'colocada_por_id');
    }

    public function esVigente(): bool
    {
        return in_array($this->estado, [self::ESTADO_GENERADA, self::ESTADO_COLOCADA], true)
            && filled($this->ruta_pdf);
    }
}
