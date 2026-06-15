<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\WoocommerceProduct;
use App\Models\WoocommerceMargin;
use App\Models\WoocommerceConfig;
use App\Models\WoocommerceSyncLog;
use App\Models\WoocommerceSyncDetail;
use App\Mail\WooSyncExitoMail;
use App\Mail\WooSyncFalloMail;
use Rap2hpoutre\FastExcel\FastExcel;

// Importación del Trait
use App\Traits\InteractsWithWooCommerceApi;

class UpdateWooCommercePricesJob implements ShouldQueue
{
    // Implementación del Trait en la clase
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithWooCommerceApi;

    protected $syncLogId;
    protected $preciosWizerp;
    
    public function __construct($syncLogId, $preciosWizerp)
    {
        $this->syncLogId = $syncLogId;
        $this->preciosWizerp = $preciosWizerp;
    }

    public function handle()
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (!$log) return;

        $log->update(['estado' => 'en_proceso']);

        $iva = (float)(WoocommerceConfig::where('llave', 'iva')->value('valor') ?? 1.16);
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();

        $skusProcesados = WoocommerceSyncDetail::where('sync_log_id', $this->syncLogId)->pluck('sku')->toArray();
        $index = count($skusProcesados);

        WoocommerceProduct::chunk(100, function ($productosLocales) use ($log, &$index, $margenes, $iva, $skusProcesados) {
            if ($log->fresh()->estado === 'cancelado') return false;

            $loteSimples = [];
            $loteVariaciones = [];

            foreach ($productosLocales as $prod) {
                $sku = $prod->sku;

                if (!isset($this->preciosWizerp[$sku]) || in_array($sku, $skusProcesados)) {
                    continue;
                }

                $precioBase = $this->preciosWizerp[$sku];
                $normal = $this->calcularPrecioFinal($precioBase, 'normal', $margenes, $iva);
                $rebaja = $this->calcularPrecioFinal($precioBase, 'rebaja', $margenes, $iva);

                if (empty($normal) || $normal <= 0) {
                    $this->registrarDetalleAuditoria($log->id, $sku, $prod, $normal, $rebaja, 'error', 'Precio calculado inválido.');
                    continue;
                }

                if ($prod->precio_normal == $normal && $prod->precio_rebajado == $rebaja) {
                    $this->registrarDetalleAuditoria($log->id, $sku, $prod, $normal, $rebaja, 'exito', 'Omitido: Sin cambios.');
                    $index++;
                    continue;
                }

                $payload = [
                    'id' => $prod->id,
                    'regular_price' => (string) $normal,
                    'sale_price' => (string) $rebaja
                ];

                if ($prod->tipo === 'variation') {
                    $loteVariaciones[$prod->parent_id][] = $payload;
                } else {
                    $loteSimples[] = $payload;
                }

                $prod->update(['precio_normal' => $normal, 'precio_rebajado' => $rebaja]);
                $this->registrarDetalleAuditoria($log->id, $sku, $prod, $normal, $rebaja, 'exito', 'Enviado en lote a Woo');
                $index++;
            }

            $this->enviarLotesWooCommerce($loteSimples, $loteVariaciones);
            $log->update(['procesados' => $index]);
        });

        if ($log->fresh()->estado !== 'cancelado') {
            $log->update(['estado' => 'completado']);
        }

        $this->enviarNotificaciones($log->fresh());
    }

    /**
     * Módulo de envío estructurado a WooCommerce.
     */
    private function enviarLotesWooCommerce(array $simples, array $variaciones): void
    {
        $baseUrl = config('services.woocommerce.url') . '/wp-json/wc/v3/products';

        if (!empty($simples)) {
            $this->ejecutarPeticionBatch("{$baseUrl}/batch", $simples);
        }

        if (!empty($variaciones)) {
            foreach ($variaciones as $parentId => $variacionesHijas) {
                $url = "{$baseUrl}/{$parentId}/variations/batch";
                $this->ejecutarPeticionBatch($url, $variacionesHijas);
            }
        }
    }

    /**
     * Ejecuta la petición HTTP con prevención de bloqueos.
     */
    private function ejecutarPeticionBatch(string $url, array $datosUpdate): void
    {
        // Uso del cliente HTTP del Trait.
        $response = $this->getWooClient('GeliaSystem-SyncBot/1.0')
                         ->post($url, ['update' => $datosUpdate]);

        if (!$response->successful()) {
            throw new \Exception('Error en la API de WooCommerce: ' . $response->body());
        }
        
        $this->aplicarLatenciaSegura(); 
    }

    // Funciones internas mantenidas sin cambios
    private function calcularPrecioFinal($base, $tipo, $margenes, $iva)
    {
        $multiplicador = 1.0;
        foreach ($margenes as $m) {
            if ($base >= $m->precio_min && $base <= $m->precio_max) {
                $multiplicador = ($tipo === 'rebaja') ? $m->multiplicador_rebaja : $m->multiplicador_normal;
                break;
            }
        }
        return round(($base * $multiplicador) / $iva, 2);
    }

    private function registrarDetalleAuditoria($logId, $sku, $prod, $normal, $rebaja, $estado, $mensaje)
    {
        WoocommerceSyncDetail::create([
            'sync_log_id' => $logId,
            'sku' => $sku,
            'precio_anterior_normal' => $prod ? $prod->precio_normal : null,
            'precio_nuevo_normal' => $normal,
            'precio_anterior_rebajado' => $prod ? $prod->precio_rebajado : null,
            'precio_nuevo_rebajado' => $rebaja,
            'estado' => $estado,
            'mensaje' => $mensaje
        ]);
    }

    private function enviarNotificaciones($log)
    {
        $adminEmail = WoocommerceConfig::where('llave', 'admin_email')->value('valor') ?? 'tu_correo_admin@dominio.com';
        
        if ($log->estado === 'completado') {
            $notifyStr = WoocommerceConfig::where('llave', 'notify_emails')->value('valor');
            $destinatarios = $notifyStr ? array_filter(array_map('trim', explode(',', $notifyStr))) : [];
            $destinatarios[] = $adminEmail; 
            $destinatarios = array_unique($destinatarios);

            $detalles = \App\Models\WoocommerceSyncDetail::where('sync_log_id', $log->id)->get();
            $tempPath = tempnam(sys_get_temp_dir(), 'woo_auditoria_');
            
            (new FastExcel($detalles))->export($tempPath, function ($detalle) {
                return [
                    'SKU' => $detalle->sku,
                    'Normal Anterior' => $detalle->precio_anterior_normal ? '$' . $detalle->precio_anterior_normal : '---',
                    'Normal Nuevo' => '$' . $detalle->precio_nuevo_normal,
                    'Rebaja Anterior' => $detalle->precio_anterior_rebajado ? '$' . $detalle->precio_anterior_rebajado : '---',
                    'Rebaja Nueva' => '$' . $detalle->precio_nuevo_rebajado,
                    'Estado' => strtoupper($detalle->estado),
                    'Mensaje' => $detalle->mensaje,
                ];
            });

            Mail::to($destinatarios)->send(new WooSyncExitoMail($log, $tempPath));
            unlink($tempPath); 

        } else {
            Mail::to($adminEmail)->send(new WooSyncFalloMail($log));
        }
    }
}