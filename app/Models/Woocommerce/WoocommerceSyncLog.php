<?php

namespace App\Models\Woocommerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WoocommerceSyncLog extends Model
{
    protected $table = 'woocommerce_sync_logs';

    protected $fillable = [
        'tipo',
        'total_productos',
        'procesados',
        'estado',
        'mensaje_error',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(WoocommerceSyncDetail::class, 'sync_log_id');
    }

    public static function activo(): ?self
    {
        $log = static::where(function ($query) {
            $query->whereIn('estado', ['pendiente', 'en_proceso'])
                ->orWhere(function ($q) {
                    $q->whereIn('estado', ['interrumpido', 'error'])
                        ->whereColumn('procesados', '<', 'total_productos');
                });
        })->latest()->first();

        if (!$log) {
            return null;
        }

        static::marcarZombieSiAplica($log);
        $log = $log->fresh();

        if (!in_array($log->estado, ['pendiente', 'en_proceso', 'interrumpido', 'error'], true)) {
            return null;
        }

        if (in_array($log->estado, ['interrumpido', 'error'], true) && $log->procesados >= $log->total_productos) {
            return null;
        }

        return $log;
    }

    public static function marcarZombieSiAplica(self $log): void
    {
        if ($log->estado !== 'en_proceso') {
            return;
        }

        if ($log->updated_at && $log->updated_at->lt(now()->subMinutes(15))) {
            $log->update([
                'estado' => 'interrumpido',
                'mensaje_error' => 'El proceso dejó de responder (posible timeout del worker). Usa Continuar para seguir en un nuevo job.',
            ]);
        }
    }

    public function estaEstancado(int $minutos = 2): bool
    {
        if ($this->estado !== 'en_proceso') {
            return false;
        }

        return $this->updated_at && $this->updated_at->lt(now()->subMinutes($minutos));
    }

    public function puedeContinuar(): bool
    {
        if ($this->procesados >= $this->total_productos) {
            return false;
        }

        return in_array($this->estado, ['interrumpido', 'error'], true)
            || $this->estaEstancado();
    }

    public static function fantasmas()
    {
        return static::whereIn('estado', ['pendiente', 'en_proceso', 'interrumpido', 'error'])
            ->orderByDesc('id')
            ->get();
    }

    public function esFantasma(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_proceso', 'interrumpido', 'error'], true);
    }
}
