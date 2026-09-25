<?php

namespace App\Jobs\WooCommerce;

use App\Models\Woocommerce\WoocommerceProduct;
use App\Traits\InteractsWithWooCommerceApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OcultarProductosWooCommerceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithWooCommerceApi;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array<int, int|string>  $productIds
     */
    public function __construct(
        public array $productIds
    ) {}

    public function handle(): void
    {
        $ids = array_values($this->productIds);
        $productos = WoocommerceProduct::whereIn('id', $ids)->get()->keyBy('id');

        $cursor = 0;
        $total = count($ids);
        $lote = null;

        while ($cursor < $total) {
            $prod = $productos->get((int) $ids[$cursor]);
            if (! $prod) {
                $cursor++;
                continue;
            }

            $grupo = ($prod->tipo === 'variation' && $prod->parent_id)
                ? 'variation:'.$prod->parent_id
                : 'simple';

            if ($lote === null) {
                $lote = [
                    'grupo' => $grupo,
                    'parent_id' => $prod->parent_id,
                    'items' => [['id' => $prod->id, 'status' => 'draft']],
                ];
                $cursor++;
                if (count($lote['items']) >= self::LOTE_MAXIMO) {
                    break;
                }
                continue;
            }

            if ($lote['grupo'] !== $grupo || count($lote['items']) >= self::LOTE_MAXIMO) {
                break;
            }

            $lote['items'][] = ['id' => $prod->id, 'status' => 'draft'];
            $cursor++;
            if (count($lote['items']) >= self::LOTE_MAXIMO) {
                break;
            }
        }

        if ($lote !== null) {
            $this->enviarLote($lote);
        }

        $restantes = array_slice($ids, $cursor);
        if ($restantes !== []) {
            self::dispatch(array_values($restantes))
                ->delay(now()->addSeconds($this->segundosPausaEntrePeticiones()));
        }
    }

    private function enviarLote(array $lote): void
    {
        $baseUrl = $this->getWooBaseUrl().'/wp-json/wc/v3/products';
        $url = $lote['grupo'] === 'simple'
            ? "{$baseUrl}/batch"
            : "{$baseUrl}/{$lote['parent_id']}/variations/batch";

        $response = $this->getWooClient()->post($url, ['update' => $lote['items']]);
        $this->afirmarRespuestaWoo($response);
    }
}
