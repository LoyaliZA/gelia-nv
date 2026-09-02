<?php

namespace App\Models\PuntoVenta;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Sucursal;
use Database\Factories\PuntoVenta\ResguardoPdvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResguardoPdv extends Model
{
    use HasFactory;

    protected static function newFactory(): ResguardoPdvFactory
    {
        return ResguardoPdvFactory::new();
    }

    public const ESTADO_PENDIENTE_RECEPCION = 'pendiente_recepcion';

    public const ESTADO_EN_CUSTODIA = 'en_custodia';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_DEVUELTO = 'devuelto';

    protected $table = 'pdv_resguardos';

    protected $fillable = [
        'pedido_bma_id',
        'cliente_id',
        'sucursal_id',
        'almacen_id',
        'estado',
        'cantidad_bultos_esperada',
        'salida_cedis_at',
        'recepcion_fisica_at',
        'entrega_completada_at',
        'devolucion_confirmada_at',
        'vencido_repuesto_at',
        'entrega_bloqueada',
        'snapshot_folio',
        'snapshot_cliente_nombre',
        'snapshot_json',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_bultos_esperada' => 'integer',
            'salida_cedis_at' => 'datetime',
            'recepcion_fisica_at' => 'datetime',
            'entrega_completada_at' => 'datetime',
            'devolucion_confirmada_at' => 'datetime',
            'vencido_repuesto_at' => 'datetime',
            'entrega_bloqueada' => 'boolean',
            'snapshot_json' => 'array',
            'version' => 'integer',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function bultos(): HasMany
    {
        return $this->hasMany(ResguardoPdvBulto::class, 'resguardo_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ResguardoPdvEvento::class, 'resguardo_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(ResguardoPdvEvidencia::class, 'resguardo_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(ResguardoPdvEntrega::class, 'resguardo_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(ResguardoPdvIncidencia::class, 'resguardo_id');
    }
}
