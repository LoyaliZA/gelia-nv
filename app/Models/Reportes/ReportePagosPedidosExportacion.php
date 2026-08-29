<?php

namespace App\Models\Reportes;

use App\Models\User;
use App\Services\Reportes\PagosPedidos\EstimarExportacionReportePagosPedidosService;
use App\Support\Reportes\ReportePagosPedidosProgreso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportePagosPedidosExportacion extends Model
{
    public const ESTADO_PROCESSING = 'processing';

    public const ESTADO_COMPLETED = 'completed';

    public const ESTADO_FAILED = 'failed';

    public const ESTADO_CANCELLED = 'cancelled';

    public const ESTADO_EXPIRED = 'expired';

    protected $table = 'reporte_pagos_pedidos_exportaciones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'titulo',
        'formato',
        'estado',
        'progress',
        'etapa',
        'etapa_label',
        'registros_procesados',
        'registros_total',
        'nombre_archivo',
        'ruta_archivo',
        'tamano_bytes',
        'num_paginas',
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

    public function estaExpirado(): bool
    {
        return $this->expira_at !== null && $this->expira_at->isPast();
    }

    public function tamanoEtiqueta(): ?string
    {
        if ($this->tamano_bytes === null) {
            return null;
        }

        return EstimarExportacionReportePagosPedidosService::formatearTamano((int) $this->tamano_bytes);
    }

    public function formatoEtiqueta(): string
    {
        return match ($this->formato) {
            'csv_resumen' => 'CSV resumen',
            'csv_detalle' => 'CSV por exhibición',
            default => 'PDF administrativo',
        };
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
            'formato' => $this->formato,
            'formato_label' => $this->formatoEtiqueta(),
            'estado' => $estado,
            'progress' => $this->progress,
            'etapa' => $this->etapa,
            'etapa_label' => $this->etapa_label,
            'registros_procesados' => $this->registros_procesados,
            'registros_total' => $this->registros_total,
            'nombre_archivo' => $this->nombre_archivo,
            'tamano_bytes' => $this->tamano_bytes,
            'tamano_etiqueta' => $this->tamanoEtiqueta(),
            'num_paginas' => $this->num_paginas,
            'num_registros' => $this->num_registros,
            'expira_at' => $this->expira_at?->toIso8601String(),
            'expira_etiqueta' => $this->expira_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm'),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'creado_etiqueta' => $this->created_at?->timezone(config('app.timezone'))->format('H:i'),
            'creado_fecha' => $this->created_at?->timezone(config('app.timezone'))->locale('es')->isoFormat('D MMM'),
            'error' => $this->error,
            'cancelable' => $estado === self::ESTADO_PROCESSING
                && in_array($this->etapa, [
                    ReportePagosPedidosProgreso::ETAPA_PREPARANDO,
                    ReportePagosPedidosProgreso::ETAPA_TOTALES,
                    ReportePagosPedidosProgreso::ETAPA_VOUCHERS,
                ], true),
            'puede_descargar' => $estado === self::ESTADO_COMPLETED && ! $this->estaExpirado(),
            'puede_reintentar' => in_array($estado, [self::ESTADO_FAILED, self::ESTADO_CANCELLED, self::ESTADO_EXPIRED], true),
        ];
    }
}
