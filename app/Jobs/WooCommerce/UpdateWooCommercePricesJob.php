<?php

namespace App\Jobs\WooCommerce;

use App\Models\User;
use App\Models\Woocommerce\WoocommerceConfiguracion;
use App\Models\Woocommerce\WoocommerceMargin;
use App\Models\Woocommerce\WoocommerceProduct;
use App\Services\WooCommerce\WooCommercePreciosService;
use App\Models\Woocommerce\WoocommerceSyncDetail;
use App\Models\Woocommerce\WoocommerceSyncLog;
use App\Notifications\WooCommerceSyncCompletada;
use App\Notifications\WooCommerceSyncFallida;
use App\Traits\InteractsWithWooCommerceApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Throwable;

class UpdateWooCommercePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithWooCommerceApi;

    public int $timeout = 600;

    public int $tries = 1;

    /** SKUs por job encadenado (legacy procesaba en bloques; cada job debe caber en el timeout del worker). */
    private const BATCH_SIZE = 50;

    /** Ítems máximos por petición batch a WooCommerce dentro de un job. */
    private const WOO_API_CHUNK_SIZE = 25;

    // #region agent log
    private function debugLog(string $hypothesisId, string $location, string $message, array $data = [], string $runId = 'pre-fix'): void
    {
        @file_put_contents(
            base_path('.cursor/debug-d46fce.log'),
            json_encode([
                'sessionId' => 'd46fce',
                'runId' => $runId,
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );
    }
    // #endregion

    public function __construct(
        protected int $syncLogId,
        protected int $offset = 0
    ) {}

    public function handle(): void
    {
        // #region agent log
        $jobStartedAt = microtime(true);
        $this->debugLog('H4', 'UpdateWooCommercePricesJob:handle:start', 'Job batch started', [
            'syncLogId' => $this->syncLogId,
            'offset' => $this->offset,
            'batchSize' => self::BATCH_SIZE,
            'wooApiChunkSize' => self::WOO_API_CHUNK_SIZE,
            'jobTimeoutSec' => $this->timeout,
            'workerTimeoutHintSec' => 630,
        ]);
        // #endregion

        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (!$log || $log->estado === 'cancelado') {
            return;
        }

        if ($log->estado === 'pendiente') {
            $log->update(['estado' => 'en_proceso']);
        }

        $preciosWizerp = $log->payload ?? [];
        if (empty($preciosWizerp)) {
            $log->update(['estado' => 'error', 'mensaje_error' => 'No hay datos de precios para procesar.']);

            return;
        }

        // #region agent log
        $this->debugLog('H5', 'UpdateWooCommercePricesJob:handle:payload', 'Payload loaded', [
            'payloadSkuCount' => count($preciosWizerp),
            'payloadBytesApprox' => strlen(json_encode($preciosWizerp)),
        ]);
        // #endregion

        try {
            $preciosService = app(WooCommercePreciosService::class);
            $iva = $preciosService->obtenerIva();
            $margenes = WoocommerceMargin::orderBy('precio_min')->get();

            $allSkus = array_values(array_keys($preciosWizerp));
            $batchSkus = array_slice($allSkus, $this->offset, self::BATCH_SIZE);

            if (empty($batchSkus)) {
                $this->finalizarSiCompleto($log);

                return;
            }

            if ($log->fresh()->estado === 'cancelado') {
                return;
            }

            $productosPorSku = WoocommerceProduct::whereIn('sku', $batchSkus)
                ->get()
                ->keyBy('sku');

            $loteSimples = [];
            $loteVariaciones = [];
            $procesadosEnLote = 0;
            // #region agent log
            $loopStartedAt = microtime(true);
            // #endregion

            foreach ($batchSkus as $sku) {
                $precioBase = $preciosWizerp[$sku];
                $prod = $productosPorSku->get($sku);

                if (!$prod) {
                    $this->registrarDetalleAuditoria(
                        $log->id,
                        $sku,
                        null,
                        null,
                        null,
                        null,
                        'error',
                        'SKU no encontrado en catálogo local.'
                    );
                    $procesadosEnLote++;
                    continue;
                }

                $normal = $preciosService->calcular($precioBase, 'normal', $margenes, $iva);
                $rebaja = $preciosService->calcular($precioBase, 'rebaja', $margenes, $iva);

                $anteriorNormal = $prod->precio_normal;
                $anteriorRebajado = $prod->precio_rebajado;

                if (empty($normal) || $normal <= 0) {
                    $this->registrarDetalleAuditoria($log->id, $sku, $anteriorNormal, $anteriorRebajado, $normal, $rebaja, 'error', 'Precio calculado inválido.');
                    $procesadosEnLote++;
                    continue;
                }

                if ($prod->precio_normal == $normal && $prod->precio_rebajado == $rebaja) {
                    $this->registrarDetalleAuditoria($log->id, $sku, $anteriorNormal, $anteriorRebajado, $normal, $rebaja, 'exito', 'Omitido: Sin cambios.');
                    $procesadosEnLote++;
                    continue;
                }

                $payload = [
                    'id' => $prod->id,
                    'regular_price' => (string) $normal,
                    'sale_price' => (string) $rebaja,
                ];

                if ($prod->tipo === 'variation') {
                    $loteVariaciones[$prod->parent_id][] = $payload;
                } else {
                    $loteSimples[] = $payload;
                }

                $this->registrarDetalleAuditoria($log->id, $sku, $anteriorNormal, $anteriorRebajado, $normal, $rebaja, 'exito', 'Enviado en lote a Woo');
                $prod->update(['precio_normal' => $normal, 'precio_rebajado' => $rebaja]);
                $procesadosEnLote++;
            }

            // #region agent log
            $loopMs = (int) round((microtime(true) - $loopStartedAt) * 1000);
            $this->debugLog('H2', 'UpdateWooCommercePricesJob:handle:loop', 'Batch loop finished', [
                'batchSkuCount' => count($batchSkus),
                'procesadosEnLote' => $procesadosEnLote,
                'simpleBatchCount' => count($loteSimples),
                'variationParentCount' => count($loteVariaciones),
                'variationItemsCount' => array_sum(array_map('count', $loteVariaciones)),
                'loopDurationMs' => $loopMs,
            ]);
            // #endregion

            // #region agent log
            $apiStartedAt = microtime(true);
            // #endregion
            $this->enviarLotesWooCommerce($loteSimples, $loteVariaciones);
            // #region agent log
            $this->debugLog('H1', 'UpdateWooCommercePricesJob:handle:api', 'Woo API batches finished', [
                'apiDurationMs' => (int) round((microtime(true) - $apiStartedAt) * 1000),
                'estimatedApiCalls' => $this->contarPeticionesApi($loteSimples, $loteVariaciones),
            ]);
            // #endregion

            $nuevoSliceOffset = $this->offset + $procesadosEnLote;
            $log->update(['procesados' => min(
                (int) $log->procesados + $procesadosEnLote,
                $log->total_productos
            )]);

            if ($log->fresh()->estado === 'cancelado') {
                return;
            }

            if ($nuevoSliceOffset < count($allSkus)) {
                // #region agent log
                $this->debugLog('H4', 'UpdateWooCommercePricesJob:handle:chain', 'Chaining next batch', [
                    'nextOffset' => $nuevoSliceOffset,
                    'totalSkus' => count($allSkus),
                    'totalDurationMs' => (int) round((microtime(true) - $jobStartedAt) * 1000),
                ]);
                // #endregion
                self::dispatch($this->syncLogId, $nuevoSliceOffset);

                return;
            }

            $this->finalizarSiCompleto($log);
            // #region agent log
            $this->debugLog('H4', 'UpdateWooCommercePricesJob:handle:complete', 'Job finished all batches', [
                'totalDurationMs' => (int) round((microtime(true) - $jobStartedAt) * 1000),
            ]);
            // #endregion
        } catch (\Exception $e) {
            // #region agent log
            $this->debugLog('H1', 'UpdateWooCommercePricesJob:handle:error', 'Job failed with exception', [
                'error' => $e->getMessage(),
                'totalDurationMs' => (int) round((microtime(true) - $jobStartedAt) * 1000),
            ]);
            // #endregion
            $log->update(['estado' => 'error', 'mensaje_error' => $e->getMessage()]);
            $this->enviarNotificaciones($log->fresh());
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (!$log || in_array($log->estado, ['completado', 'cancelado'])) {
            return;
        }

        $mensaje = $exception?->getMessage() ?? 'El proceso fue interrumpido (timeout o error del worker).';
        $log->update([
            'estado' => 'interrumpido',
            'mensaje_error' => $mensaje,
        ]);
    }

    private function finalizarSiCompleto(WoocommerceSyncLog $log): void
    {
        if ($log->fresh()->estado === 'cancelado') {
            return;
        }

        $log->update(['estado' => 'completado']);
        $this->enviarNotificaciones($log->fresh());
    }

    private function enviarLotesWooCommerce(array $simples, array $variaciones): void
    {
        $baseUrl = $this->getWooBaseUrl() . '/wp-json/wc/v3/products';

        foreach (array_chunk($simples, self::WOO_API_CHUNK_SIZE) as $chunkSimples) {
            $this->ejecutarPeticionBatch("{$baseUrl}/batch", $chunkSimples);
        }

        foreach ($variaciones as $parentId => $variacionesHijas) {
            $url = "{$baseUrl}/{$parentId}/variations/batch";
            foreach (array_chunk($variacionesHijas, self::WOO_API_CHUNK_SIZE) as $chunkVariaciones) {
                $this->ejecutarPeticionBatch($url, $chunkVariaciones);
            }
        }
    }

    private function contarPeticionesApi(array $simples, array $variaciones): int
    {
        $calls = (int) ceil(count($simples) / self::WOO_API_CHUNK_SIZE);

        foreach ($variaciones as $variacionesHijas) {
            $calls += (int) ceil(count($variacionesHijas) / self::WOO_API_CHUNK_SIZE);
        }

        return $calls;
    }

    private function ejecutarPeticionBatch(string $url, array $datosUpdate): void
    {
        // #region agent log
        $startedAt = microtime(true);
        @file_put_contents(
            base_path('.cursor/debug-d46fce.log'),
            json_encode([
                'sessionId' => 'd46fce',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H3',
                'location' => 'UpdateWooCommercePricesJob:ejecutarPeticionBatch:start',
                'message' => 'Woo batch HTTP request starting',
                'data' => [
                    'urlTail' => substr($url, -40),
                    'itemCount' => count($datosUpdate),
                    'httpTimeoutSec' => 60,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );
        // #endregion

        $response = $this->getWooClient('GeliaSystem-SyncBot/1.0')
            ->post($url, ['update' => $datosUpdate]);

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-d46fce.log'),
            json_encode([
                'sessionId' => 'd46fce',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H3',
                'location' => 'UpdateWooCommercePricesJob:ejecutarPeticionBatch:end',
                'message' => 'Woo batch HTTP request finished',
                'data' => [
                    'urlTail' => substr($url, -40),
                    'itemCount' => count($datosUpdate),
                    'httpStatus' => $response->status(),
                    'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );
        // #endregion

        if (!$response->successful()) {
            throw new \Exception('Error en la API de WooCommerce: ' . $response->body());
        }

        $this->aplicarLatenciaSegura();
    }

    private function registrarDetalleAuditoria(
        int $logId,
        string $sku,
        ?float $anteriorNormal,
        ?float $anteriorRebajado,
        ?float $normal,
        ?float $rebajado,
        string $estado,
        string $mensaje
    ): void {
        WoocommerceSyncDetail::create([
            'sync_log_id' => $logId,
            'sku' => $sku,
            'precio_anterior_normal' => $anteriorNormal,
            'precio_nuevo_normal' => $normal,
            'precio_anterior_rebajado' => $anteriorRebajado,
            'precio_nuevo_rebajado' => $rebajado,
            'estado' => $estado,
            'mensaje' => $mensaje,
        ]);
    }

    private function enviarNotificaciones(WoocommerceSyncLog $log): void
    {
        $config = WoocommerceConfiguracion::obtener();
        $userIds = $config->notified_users ?? [];
        $usuarios = User::whereIn('id', $userIds)->get();

        if ($log->estado === 'completado') {
            $detalles = WoocommerceSyncDetail::where('sync_log_id', $log->id)->get();
            $storagePath = 'woocommerce/auditoria-' . $log->id . '-' . time() . '.csv';
            $tempPath = tempnam(sys_get_temp_dir(), 'woo_auditoria_');

            (new FastExcel($detalles))->export($tempPath, function ($detalle) {
                return [
                    'SKU' => $detalle->sku,
                    'Normal Anterior' => $detalle->precio_anterior_normal !== null ? '$' . $detalle->precio_anterior_normal : '---',
                    'Normal Nuevo' => '$' . $detalle->precio_nuevo_normal,
                    'Rebaja Anterior' => $detalle->precio_anterior_rebajado !== null ? '$' . $detalle->precio_anterior_rebajado : '---',
                    'Rebaja Nueva' => '$' . $detalle->precio_nuevo_rebajado,
                    'Estado' => strtoupper($detalle->estado),
                    'Mensaje' => $detalle->mensaje,
                ];
            });

            Storage::disk('local')->put($storagePath, file_get_contents($tempPath));
            unlink($tempPath);

            $csvAbsolutePath = Storage::disk('local')->path($storagePath);

            if ($usuarios->isNotEmpty()) {
                Notification::send($usuarios, new WooCommerceSyncCompletada($log, $csvAbsolutePath));
            }
        } elseif (in_array($log->estado, ['error', 'interrumpido'])) {
            if ($usuarios->isNotEmpty()) {
                Notification::send($usuarios, new WooCommerceSyncFallida($log));
            }
        }
    }
}
