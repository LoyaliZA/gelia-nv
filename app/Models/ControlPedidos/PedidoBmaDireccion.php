<?php

namespace App\Models\ControlPedidos;

use App\Models\ClienteDireccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaDireccion extends Model
{
    public const ORIGEN_NORMALIZADO = 'normalizado';

    public const ORIGEN_LEGACY = 'legacy';

    public const ORIGEN_MANUAL = 'manual';

    protected $table = 'pedido_bma_direcciones';

    protected $fillable = [
        'pedido_bma_id',
        'cliente_direccion_id',
        'version_snapshot',
        'es_vigente',
        'motivo_cambio',
        'cambiado_por',
        'cambiado_en',
        'numero_direccion',
        'etiqueta',
        'tipo_direccion',
        'nombre_destinatario',
        'telefono_destinatario',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'municipio',
        'ciudad',
        'estado',
        'pais',
        'referencias',
        'indicaciones_entrega',
        'domicilio_legacy',
        'origen',
    ];

    protected $casts = [
        'es_vigente' => 'boolean',
        'version_snapshot' => 'integer',
        'numero_direccion' => 'integer',
        'cambiado_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function clienteDireccion(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccion::class, 'cliente_direccion_id');
    }

    public function cambiadoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por');
    }
}
