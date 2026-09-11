<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeProductoImagenOperacion extends Model
{
    use HasUuids;

    public const MODO_AGREGAR = 'agregar';

    public const MODO_REEMPLAZAR_TODAS = 'reemplazar_todas';

    public const ESTADO_PREPARADA = 'preparada';

    public const ESTADO_CARGANDO = 'cargando';

    public const ESTADO_CARGADA = 'cargada';

    public const ESTADO_RETIRANDO_ANTERIORES = 'retirando_anteriores';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_RESULTADO_INCIERTO = 'resultado_incierto';

    public const ESTADO_PENDIENTE_RECONCILIACION = 'pendiente_reconciliacion';

    public const ESTADO_FALLIDA = 'fallida';

    protected $table = 'tiendanube_producto_imagen_operaciones';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tienda_id',
        'producto_id',
        'solicitud_clave',
        'archivo_hash',
        'modo',
        'ids_originales',
        'imagen_nueva_id',
        'estado',
        'eliminaciones',
        'error',
        'intentos',
        'archivo_path',
        'src_url',
        'filename',
        'position',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'ids_originales' => 'array',
            'imagen_nueva_id' => 'integer',
            'eliminaciones' => 'array',
            'intentos' => 'integer',
            'position' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(TiendanubeProducto::class, 'producto_id');
    }

    public function esParcial(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE_RECONCILIACION;
    }

    /**
     * @return array{id: string, estado: string, solicitud_clave: string, parcial: bool, error: ?string}
     */
    public function toApi(): array
    {
        return [
            'id' => (string) $this->id,
            'estado' => $this->estado,
            'solicitud_clave' => $this->solicitud_clave,
            'parcial' => $this->esParcial(),
            'error' => $this->error,
        ];
    }
}
