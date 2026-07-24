<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;

class TiendanubeSyncLog extends Model
{
    protected $table = 'tiendanube_sync_logs';

    protected $fillable = [
        'tipo',
        'estado',
        'total_categorias',
        'total_productos',
        'procesados_categorias',
        'procesados_productos',
        'mensaje_error',
    ];

    public static function activo(): ?self
    {
        $log = static::whereIn('estado', ['pendiente', 'en_proceso'])->latest()->first();

        if (! $log) {
            return null;
        }

        if ($log->estado === 'en_proceso' && $log->updated_at && $log->updated_at->lt(now()->subMinutes(15))) {
            $log->update([
                'estado' => 'error',
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
