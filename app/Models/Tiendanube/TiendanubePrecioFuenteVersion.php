<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioFuenteVersion extends Model
{
    public const TIPO_COSTO_LOCAL = 'costo_local';

    public const TIPO_LISTA_REFERENCIA = 'lista_referencia';

    public const TIPO_COSTO_REMOTO_ACTUAL = 'costo_remoto_actual';

    public const TIPO_PRECIO_NORMAL_ACTUAL = 'precio_normal_actual';

    public const TIPO_PRECIO_PROMOCIONAL_ACTUAL = 'precio_promocional_actual';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_ARCHIVO = 'archivo';

    protected $table = 'tiendanube_precio_fuente_versiones';

    protected $fillable = [
        'store_id',
        'tipo',
        'lista_id',
        'fuente_clave',
        'variante_id',
        'producto_id',
        'version',
        'moneda',
        'valor_decimal',
        'fecha',
        'user_id',
        'origen',
        'motivo',
        'import_id',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'lista_id' => 'integer',
            'variante_id' => 'integer',
            'producto_id' => 'integer',
            'version' => 'integer',
            'valor_decimal' => 'decimal:2',
            'fecha' => 'datetime',
            'user_id' => 'integer',
            'import_id' => 'integer',
        ];
    }

    public static function claveFuente(string $tipo, ?int $listaId): string
    {
        if ($tipo === self::TIPO_LISTA_REFERENCIA) {
            return 'lista:'.(int) $listaId;
        }

        return self::TIPO_COSTO_LOCAL;
    }

    public function lista(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLista::class, 'lista_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function valorDecimalString(): string
    {
        return (string) $this->valor_decimal;
    }
}
