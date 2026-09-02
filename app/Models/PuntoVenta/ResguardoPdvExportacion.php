<?php

namespace App\Models\PuntoVenta;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResguardoPdvExportacion extends Model
{
    public const TIPO_LISTADO = 'listado';

    public const TIPO_AUDITORIA = 'auditoria';

    public const ESTADO_PENDING = 'pending';

    public const ESTADO_PROCESSING = 'processing';

    public const ESTADO_COMPLETED = 'completed';

    public const ESTADO_FAILED = 'failed';

    public const ESTADO_EXPIRED = 'expired';

    protected $table = 'pdv_resguardo_exportaciones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'resguardo_id',
        'titulo',
        'tipo',
        'estado',
        'nombre_archivo',
        'ruta_archivo',
        'tamano_bytes',
        'num_registros',
        'filtros',
        'error',
        'expira_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filtros' => 'array',
        'expira_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resguardo(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdv::class, 'resguardo_id');
    }

    public function estaExpirado(): bool
    {
        return $this->expira_at !== null && $this->expira_at->isPast();
    }

    /** @return array<string, mixed> */
    public function paraApi(): array
    {
        $estado = $this->estado;
        if ($estado === self::ESTADO_COMPLETED && $this->estaExpirado()) {
            $estado = self::ESTADO_EXPIRED;
        }

        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'tipo' => $this->tipo,
            'estado' => $estado,
            'estado_label' => match ($estado) {
                self::ESTADO_PENDING => 'En cola',
                self::ESTADO_PROCESSING => 'Procesando',
                self::ESTADO_COMPLETED => 'Listo',
                self::ESTADO_FAILED => 'Fallido',
                self::ESTADO_EXPIRED => 'Expirado',
                default => $estado,
            },
            'num_registros' => $this->num_registros,
            'nombre_archivo' => $this->nombre_archivo,
            'tamano_bytes' => $this->tamano_bytes,
            'expira_at' => $this->expira_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'error' => $this->error,
            'puede_descargar' => $estado === self::ESTADO_COMPLETED && ! $this->estaExpirado(),
            'descarga_url' => $estado === self::ESTADO_COMPLETED && ! $this->estaExpirado()
                ? route('punto_venta.resguardos.exportaciones.descargar', ['exportacion' => $this->id], false)
                : null,
        ];
    }
}
