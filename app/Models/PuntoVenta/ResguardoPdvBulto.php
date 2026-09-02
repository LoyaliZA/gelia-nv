<?php

namespace App\Models\PuntoVenta;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Database\Factories\PuntoVenta\ResguardoPdvBultoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResguardoPdvBulto extends Model
{
    use HasFactory;

    protected static function newFactory(): ResguardoPdvBultoFactory
    {
        return ResguardoPdvBultoFactory::new();
    }

    public const TIPO_CAJA = 'caja';

    public const TIPO_BOLSA = 'bolsa';

    public const ESTADO_ESPERADO = 'esperado';

    public const ESTADO_RECIBIDO = 'recibido';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_DEVUELTO = 'devuelto';

    protected $table = 'pdv_resguardo_bultos';

    protected $fillable = [
        'resguardo_id',
        'pedido_bma_id',
        'folio',
        'codigo_etiqueta',
        'tipo',
        'estado',
        'recepcion_at',
        'recepcion_por_id',
        'entrega_at',
        'devolucion_salida_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'recepcion_at' => 'datetime',
            'entrega_at' => 'datetime',
            'devolucion_salida_at' => 'datetime',
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

    public function recepcionPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recepcion_por_id');
    }

    public function entregas(): BelongsToMany
    {
        return $this->belongsToMany(
            ResguardoPdvEntrega::class,
            'pdv_resguardo_entrega_bultos',
            'bulto_id',
            'entrega_id'
        )->withTimestamps();
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(ResguardoPdvEvidencia::class, 'bulto_id');
    }
}
