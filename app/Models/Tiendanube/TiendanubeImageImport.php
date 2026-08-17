<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubeImageImport extends Model
{
    protected $table = 'tiendanube_image_imports';

    protected $fillable = [
        'user_id',
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
    ];

    protected function casts(): array
    {
        return [
            'reemplazar_primera' => 'boolean',
            'convertir_webp' => 'boolean',
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
            return $this->estado === 'completado' ? 100 : 0;
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

        $nombreInvalido = (int) ($counts['nombre_invalido'] ?? 0);
        $skuNoEncontrado = (int) ($counts['sku_no_encontrado'] ?? 0);
        $archivoGrande = (int) ($counts['archivo_grande'] ?? 0);
        $errorCarga = (int) ($counts['error_carga'] ?? 0);

        return [
            'matched' => $this->items()->where('estado', 'ok')->count()
                + $this->items()->where('estado', 'pendiente')->count(),
            'omitidos' => $this->items()->where('estado', 'omitido')->count(),
            'errores' => $this->items()->where('estado', 'error')->count(),
            'nombre_invalido' => $nombreInvalido,
            'sku_no_encontrado' => $skuNoEncontrado,
            'archivo_grande' => $archivoGrande,
            'error_carga' => $errorCarga,
        ];
    }

    public static function activo(): ?self
    {
        $import = static::whereIn('estado', ['pendiente', 'en_proceso'])->latest()->first();
        if (! $import) {
            return null;
        }

        if ($import->estado === 'en_proceso' && $import->updated_at && $import->updated_at->lt(now()->subMinutes(30))) {
            $import->update([
                'estado' => 'error',
                'mensaje_error' => 'La importación dejó de responder (posible timeout del worker).',
            ]);

            return null;
        }

        return $import;
    }
}
