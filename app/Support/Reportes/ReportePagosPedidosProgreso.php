<?php

namespace App\Support\Reportes;

use App\Models\Reportes\ReportePagosPedidosExportacion;
use Illuminate\Support\Facades\Cache;

/** Progreso en caché + registro persistente para exportaciones de pagos de pedidos. */
final class ReportePagosPedidosProgreso
{
    public const ETAPA_PREPARANDO = 'preparando_datos';

    public const ETAPA_TOTALES = 'calculando_totales';

    public const ETAPA_VOUCHERS = 'procesando_vouchers';

    public const ETAPA_PDF = 'construyendo_pdf';

    public const ETAPA_FINALIZANDO = 'finalizando_archivo';

    /** @var array<string, array{label: string, weight: int, cancelable: bool}> */
    private const ETAPAS = [
        self::ETAPA_PREPARANDO => ['label' => 'Preparando datos', 'weight' => 8, 'cancelable' => true],
        self::ETAPA_TOTALES => ['label' => 'Calculando totales', 'weight' => 17, 'cancelable' => true],
        self::ETAPA_VOUCHERS => ['label' => 'Procesando vouchers', 'weight' => 35, 'cancelable' => true],
        self::ETAPA_PDF => ['label' => 'Construyendo PDF', 'weight' => 35, 'cancelable' => false],
        self::ETAPA_FINALIZANDO => ['label' => 'Finalizando archivo', 'weight' => 5, 'cancelable' => false],
    ];

    public function __construct(private readonly string $jobId) {}

    public static function cacheKey(string $jobId): string
    {
        return "reporte_pagos_pedidos_{$jobId}";
    }

    public static function cancelKey(string $jobId): string
    {
        return "reporte_pagos_pedidos_cancel_{$jobId}";
    }

    public static function iniciar(string $jobId): self
    {
        $progreso = new self($jobId);
        $progreso->escribir([
            'progress' => 0,
            'status' => 'processing',
            'etapa' => self::ETAPA_PREPARANDO,
            'etapa_label' => self::ETAPAS[self::ETAPA_PREPARANDO]['label'],
            'registros_procesados' => 0,
            'registros_total' => 0,
            'started_at' => now()->toIso8601String(),
            'cancelable' => true,
            'file_path' => null,
            'error' => null,
        ]);

        return $progreso;
    }

    /** @return array<string, mixed>|null */
    public static function leer(string $jobId): ?array
    {
        $cache = Cache::get(self::cacheKey($jobId));
        $export = ReportePagosPedidosExportacion::query()->find($jobId);

        if ($export) {
            $api = $export->fresh()->paraApi();

            return array_merge($api, $cache ?? [], [
                'status' => $cache['status'] ?? $api['estado'],
                'file_path' => $export->ruta_archivo,
            ]);
        }

        return $cache;
    }

    public function marcarTotalRegistros(int $total): void
    {
        $actual = self::leer($this->jobId) ?? [];
        $actual['registros_total'] = max(0, $total);
        $this->escribir($actual);
    }

    public function avanzar(string $etapa, int $registrosProcesados, int $registrosEtapa, int $registrosEtapaTotal): void
    {
        $this->assertNoCancelado();

        $meta = self::ETAPAS[$etapa] ?? self::ETAPAS[self::ETAPA_PREPARANDO];
        $ratioEtapa = $registrosEtapaTotal > 0
            ? min(1, $registrosEtapa / $registrosEtapaTotal)
            : 1;

        $progress = $this->calcularPorcentaje($etapa, $ratioEtapa);

        $this->escribir([
            'progress' => $progress,
            'status' => 'processing',
            'etapa' => $etapa,
            'etapa_label' => $meta['label'],
            'registros_procesados' => max(0, $registrosProcesados),
            'cancelable' => $meta['cancelable'],
        ]);
    }

    /** @param  array<string, mixed>  $meta */
    public function completar(string $filePath, array $meta = []): void
    {
        $actual = self::leer($this->jobId) ?? [];
        $this->escribir(array_merge($actual, [
            'progress' => 100,
            'status' => 'completed',
            'etapa' => self::ETAPA_FINALIZANDO,
            'etapa_label' => self::ETAPAS[self::ETAPA_FINALIZANDO]['label'],
            'cancelable' => false,
            'file_path' => $filePath,
            'error' => null,
            'nombre_archivo' => $meta['nombre_archivo'] ?? null,
            'tamano_bytes' => $meta['tamano_bytes'] ?? null,
            'num_paginas' => $meta['num_paginas'] ?? null,
            'num_registros' => $meta['num_registros'] ?? null,
        ]));
    }

    public function fallar(string $error): void
    {
        $actual = self::leer($this->jobId) ?? [];
        $this->escribir(array_merge($actual, [
            'progress' => 0,
            'status' => 'failed',
            'cancelable' => false,
            'error' => $error,
        ]));
    }

    public function cancelado(): void
    {
        $actual = self::leer($this->jobId) ?? [];
        $this->escribir(array_merge($actual, [
            'progress' => 0,
            'status' => 'cancelled',
            'cancelable' => false,
            'error' => 'Generación cancelada por el usuario.',
        ]));
        Cache::forget(self::cancelKey($this->jobId));
    }

    public function debeCancelar(): bool
    {
        return (bool) Cache::get(self::cancelKey($this->jobId), false);
    }

    public function assertNoCancelado(): void
    {
        if ($this->debeCancelar()) {
            throw new \App\Exceptions\ReportePagosPedidosCanceladoException('Generación cancelada.');
        }
    }

    public static function solicitarCancelacion(string $jobId): bool
    {
        $estado = self::leer($jobId);
        if (! $estado || ($estado['status'] ?? '') !== 'processing' || empty($estado['cancelable'])) {
            return false;
        }

        Cache::put(self::cancelKey($jobId), true, now()->addHours(24));

        return true;
    }

    /** @return array<string, mixed> */
    public static function desdeModelo(ReportePagosPedidosExportacion $export): array
    {
        $api = $export->paraApi();

        return array_merge($api, [
            'status' => $api['estado'],
            'file_path' => $export->ruta_archivo,
        ]);
    }

    private function calcularPorcentaje(string $etapa, float $ratioEtapa): int
    {
        $base = 0;
        foreach (self::ETAPAS as $key => $meta) {
            if ($key === $etapa) {
                return min(99, (int) round($base + ($meta['weight'] * $ratioEtapa)));
            }
            $base += $meta['weight'];
        }

        return min(99, (int) round($base + ($ratioEtapa * 5)));
    }

    /** @param  array<string, mixed>  $parcial */
    private function escribir(array $parcial): void
    {
        $actual = Cache::get(self::cacheKey($this->jobId)) ?? [];
        $merged = array_merge($actual, $parcial);
        Cache::put(self::cacheKey($this->jobId), $merged, now()->addHours(24));

        $export = ReportePagosPedidosExportacion::query()->find($this->jobId);
        if (! $export) {
            return;
        }

        $updates = [];
        if (isset($merged['progress'])) {
            $updates['progress'] = (int) $merged['progress'];
        }
        if (isset($merged['status'])) {
            $updates['estado'] = (string) $merged['status'];
        }
        if (array_key_exists('etapa', $merged)) {
            $updates['etapa'] = $merged['etapa'];
        }
        if (array_key_exists('etapa_label', $merged)) {
            $updates['etapa_label'] = $merged['etapa_label'];
        }
        if (isset($merged['registros_procesados'])) {
            $updates['registros_procesados'] = (int) $merged['registros_procesados'];
        }
        if (isset($merged['registros_total'])) {
            $updates['registros_total'] = (int) $merged['registros_total'];
        }
        if (! empty($merged['file_path'])) {
            $updates['ruta_archivo'] = $merged['file_path'];
        }
        if (! empty($merged['nombre_archivo'])) {
            $updates['nombre_archivo'] = $merged['nombre_archivo'];
        }
        if (isset($merged['tamano_bytes'])) {
            $updates['tamano_bytes'] = (int) $merged['tamano_bytes'];
        }
        if (array_key_exists('num_paginas', $merged)) {
            $updates['num_paginas'] = $merged['num_paginas'];
        }
        if (array_key_exists('num_registros', $merged)) {
            $updates['num_registros'] = $merged['num_registros'];
        }
        if (array_key_exists('error', $merged)) {
            $updates['error'] = $merged['error'];
        }
        if (($merged['status'] ?? '') === 'completed') {
            $updates['completed_at'] = now();
        }

        if ($updates !== []) {
            $export->update($updates);
        }
    }
}
