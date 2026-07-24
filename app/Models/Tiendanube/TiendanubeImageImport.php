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
    ];

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
