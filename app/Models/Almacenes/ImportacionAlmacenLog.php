<?php

namespace App\Models\Almacenes;

use App\Models\Almacen;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionAlmacenLog extends Model
{
    protected $table = 'importaciones_almacen_logs';

    protected $fillable = [
        'user_id',
        'tipo',
        'almacen_id',
        'archivo_ruta',
        'archivo_normalizado',
        'mapping',
        'total_filas',
        'procesados',
        'importados',
        'actualizados',
        'omitidos',
        'estado',
        'mensaje_error',
        'reporte_errores_token',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function errores(): HasMany
    {
        return $this->hasMany(ImportacionAlmacenError::class, 'importacion_almacen_log_id');
    }

    public static function activo(): ?self
    {
        $log = static::where(function ($query) {
            $query->whereIn('estado', ['pendiente', 'en_proceso'])
                ->orWhere(function ($q) {
                    $q->whereIn('estado', ['interrumpido', 'error'])
                        ->whereColumn('procesados', '<', 'total_filas');
                });
        })->latest()->first();

        if (! $log) {
            return null;
        }

        static::marcarZombieSiAplica($log);
        $log = $log->fresh();

        if (! in_array($log->estado, ['pendiente', 'en_proceso', 'interrumpido', 'error'], true)) {
            return null;
        }

        if (in_array($log->estado, ['interrumpido', 'error'], true) && $log->procesados >= $log->total_filas) {
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
        if ($this->procesados >= $this->total_filas) {
            return false;
        }

        return in_array($this->estado, ['interrumpido', 'error'], true)
            || $this->estaEstancado();
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            'productos' => 'Importación de productos',
            'inventarios' => 'Importación de inventario',
            'costos' => 'Importación de costos',
            default => 'Importación de almacén',
        };
    }

    public function resumenParaUi(): array
    {
        return [
            'importados' => $this->importados,
            'actualizados' => $this->actualizados,
            'omitidos' => $this->omitidos,
            'errores_detalle' => $this->errores()
                ->orderBy('fila')
                ->limit(50)
                ->get()
                ->map(fn (ImportacionAlmacenError $e) => "Fila {$e->fila}: {$e->mensaje}")
                ->all(),
            'reporte_url' => $this->reporte_errores_token
                ? route('almacenes.importaciones.reporte_errores', ['token' => $this->reporte_errores_token])
                : null,
        ];
    }
}
