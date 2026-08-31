<?php

namespace App\Models\Reportes;

use App\Models\User;
use App\Services\Reportes\PagosPedidos\EstimarExportacionReportePagosPedidosService;
use App\Support\Reportes\EncabezadoReportePagosPedidosPdf;
use App\Support\Reportes\ReportePagosPedidosProgreso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportePagosPedidosExportacion extends Model
{
    public const ESTADO_PENDING = 'pending';

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
        'tipo_reporte',
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
        if (($this->tipo_reporte ?? 'pedido') === 'vouchers') {
            return match ($this->formato) {
                'csv_resumen' => 'CSV vouchers',
                default => 'PDF vouchers validados',
            };
        }

        return match ($this->formato) {
            'csv_resumen' => 'CSV resumen',
            'csv_detalle' => 'CSV por exhibición',
            default => 'PDF administrativo',
        };
    }

    public function tipoReporteLabel(): string
    {
        return ($this->tipo_reporte ?? 'pedido') === 'vouchers'
            ? 'Vouchers validados'
            : 'Pagos por pedido';
    }

    /** @return array{criterio: string, desde: ?string, hasta: ?string}|null */
    public function periodoResumido(): ?array
    {
        $filtros = $this->filtros;
        if (! is_array($filtros)) {
            return null;
        }

        $tipo = EncabezadoReportePagosPedidosPdf::tipoFechaPublico($filtros);
        $map = match ($tipo) {
            'Fecha del pedido' => ['fecha_pedido_desde', 'fecha_pedido_hasta'],
            'Fecha reportada' => ['fecha_reportada_desde', 'fecha_reportada_hasta'],
            'Fecha del pago' => ['fecha_pago_desde', 'fecha_pago_hasta'],
            default => ['fecha_validacion_desde', 'fecha_validacion_hasta'],
        };

        return [
            'criterio' => $tipo,
            'desde' => $filtros[$map[0]] ?? null,
            'hasta' => $filtros[$map[1]] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function paraApi(): array
    {
        $estado = $this->estado;
        if ($estado === self::ESTADO_COMPLETED && $this->estaExpirado()) {
            $estado = self::ESTADO_EXPIRED;
        }

        $periodo = $this->periodoResumido();

        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'tipo_reporte' => $this->tipo_reporte ?? 'pedido',
            'tipo_reporte_label' => $this->tipoReporteLabel(),
            'formato' => $this->formato,
            'formato_label' => $this->formatoEtiqueta(),
            'periodo' => $periodo,
            'criterio_fecha' => $periodo['criterio'] ?? null,
            'estado' => $estado,
            'estado_label' => match ($estado) {
                self::ESTADO_PENDING => 'En cola',
                self::ESTADO_PROCESSING => 'Procesando',
                self::ESTADO_COMPLETED => 'Listo',
                self::ESTADO_FAILED => 'Fallido',
                self::ESTADO_CANCELLED => 'Cancelado',
                self::ESTADO_EXPIRED => 'Expirado',
                default => $estado,
            },
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
            'solicitado_por' => $this->user?->name,
        ];
    }
}
