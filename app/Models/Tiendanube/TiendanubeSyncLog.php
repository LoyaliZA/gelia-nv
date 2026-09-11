<?php

namespace App\Models\Tiendanube;

use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use Illuminate\Database\Eloquent\Model;

class TiendanubeSyncLog extends Model
{
    protected $table = 'tiendanube_sync_logs';

    protected $fillable = [
        'tipo',
        'estado',
        'store_id',
        'config_generation',
        'fase',
        'pagina_categorias',
        'pagina_productos',
        'total_categorias',
        'total_productos',
        'procesados_categorias',
        'procesados_productos',
        'eliminados_productos',
        'eliminados_categorias',
        'candidatos_categorias',
        'candidatos_productos',
        'pendientes_confirmacion',
        'confirmar_depuracion_masiva',
        'mensaje_error',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'config_generation' => 'integer',
            'confirmar_depuracion_masiva' => 'boolean',
        ];
    }

    public static function activo(): ?self
    {
        $log = static::whereIn('estado', ['pendiente', 'en_proceso'])->latest()->first();

        if (! $log) {
            return null;
        }

        $ops = app(TiendanubeOperacionTiendaService::class);
        if ($ops->hayCatalogoSyncActivo($log->store_id ? (int) $log->store_id : null)) {
            return $log;
        }

        $staleMinutes = max(1, (int) config('tiendanube.sync_stale_minutes', 15));
        if ($log->updated_at && $log->updated_at->lt(now()->subMinutes($staleMinutes))) {
            $log->update([
                'estado' => 'error',
                'fase' => 'error',
                'mensaje_error' => 'El proceso dejó de responder (posible timeout del worker).',
            ]);

            return null;
        }

        return $log;
    }

    public function progresoPorcentaje(): int
    {
        $total = $this->total_categorias + $this->total_productos;
        if ($total <= 0) {
            return $this->estado === 'completado' ? 100 : 0;
        }

        $hecho = $this->procesados_categorias + $this->procesados_productos;

        return (int) min(100, round(($hecho / $total) * 100));
    }
}
