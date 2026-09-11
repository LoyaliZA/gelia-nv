<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubeImageImport extends Model
{
    protected $table = 'tiendanube_image_imports';

    public const ESTADO_VALIDANDO = 'validando';

    public const ESTADO_REQUIERE_REVISION = 'requiere_revision';

    public const ESTADO_LISTA = 'lista';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_PROCESO = 'en_proceso';

    public const ESTADO_COMPLETADO = 'completado';

    public const ESTADO_COMPLETADO_CON_INCIDENCIAS = 'completado_con_incidencias';

    public const ESTADO_ERROR = 'error';

    public const ESTADOS_ACTIVOS = [
        self::ESTADO_VALIDANDO,
        self::ESTADO_REQUIERE_REVISION,
        self::ESTADO_LISTA,
        self::ESTADO_PROCESANDO,
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_PROCESO,
    ];

    public const ESTADOS_EJECUTABLES = [
        self::ESTADO_LISTA,
        self::ESTADO_PROCESANDO,
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_PROCESO,
    ];

    public const ESTADOS_TERMINALES = [
        self::ESTADO_COMPLETADO,
        self::ESTADO_COMPLETADO_CON_INCIDENCIAS,
        self::ESTADO_ERROR,
    ];

    public const ESTADOS_STALE = [
        self::ESTADO_PROCESANDO,
        self::ESTADO_EN_PROCESO,
        self::ESTADO_VALIDANDO,
        self::ESTADO_PENDIENTE,
    ];

    protected $fillable = [
        'user_id',
        'store_id',
        'config_generation',
        'estado',
        'total_archivos',
        'procesados',
        'exitosos',
        'fallidos',
        'zip_path',
        'extract_path',
        'mensaje_error',
        'reemplazar_primera',
        'convertir_webp',
        'modo_1280',
        'confirmado_at',
    ];

    protected function casts(): array
    {
        return [
            'reemplazar_primera' => 'boolean',
            'convertir_webp' => 'boolean',
            'confirmado_at' => 'datetime',
            'store_id' => 'integer',
            'config_generation' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TiendanubeImageImportItem::class, 'import_id');
    }

    public function progresoPorcentaje(): int
    {
        if ($this->total_archivos <= 0) {
            return in_array($this->estado, self::ESTADOS_TERMINALES, true) ? 100 : 0;
        }

        return (int) min(100, round(($this->procesados / $this->total_archivos) * 100));
    }

    /**
     * @return array{
     *     matched: int,
     *     omitidos: int,
     *     errores: int,
     *     nombre_invalido: int,
     *     sku_no_encontrado: int,
     *     sku_ambiguo: int,
     *     archivo_grande: int,
     *     error_carga: int
     * }
     */
    public function resumenMotivos(): array
    {
        $counts = $this->items()
            ->selectRaw('motivo, COUNT(*) as total')
            ->whereNotNull('motivo')
            ->groupBy('motivo')
            ->pluck('total', 'motivo');

        return [
            'matched' => $this->items()->where('estado', 'ok')->count()
                + $this->items()->where('estado', 'pendiente')->count()
                + $this->items()->where('estado', 'requiere_seleccion')->count(),
            'omitidos' => $this->items()->where('estado', 'omitido')->count(),
            'errores' => $this->items()->where('estado', 'error')->count(),
            'nombre_invalido' => (int) ($counts['nombre_invalido'] ?? 0),
            'sku_no_encontrado' => (int) ($counts['sku_no_encontrado'] ?? 0),
            'sku_ambiguo' => (int) ($counts['sku_ambiguo'] ?? 0),
            'archivo_grande' => (int) ($counts['archivo_grande'] ?? 0),
            'error_carga' => (int) ($counts['error_carga'] ?? 0),
        ];
    }

    public static function activo(): ?self
    {
        $import = static::whereIn('estado', self::ESTADOS_ACTIVOS)->latest()->first();
        if (! $import) {
            return null;
        }

        if (
            in_array($import->estado, self::ESTADOS_STALE, true)
            && $import->updated_at
            && $import->updated_at->lt(now()->subMinutes(30))
        ) {
            $import->update([
                'estado' => self::ESTADO_ERROR,
                'mensaje_error' => 'La importación dejó de responder (posible timeout del worker).',
            ]);

            return null;
        }

        return $import;
    }
}
