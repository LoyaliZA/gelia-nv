<?php

namespace Tests\Unit\Support;

use App\Models\Contabilidad\Pedido;
use App\Models\Contabilidad\PlataformaPago;
use App\Support\ContabilidadReporteAssets;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ContabilidadReporteAssetsTest extends TestCase
{
    public function test_datos_grafica_ingresos_plataforma_agrupa_y_ordena(): void
    {
        $mercadoLibre = new PlataformaPago(['nombre' => 'Mercado Libre']);
        $amazon = new PlataformaPago(['nombre' => 'Amazon']);
        $shopify = new PlataformaPago(['nombre' => 'Shopify']);

        $pedidos = new Collection([
            $this->pedidoConPlataforma($mercadoLibre, 1000),
            $this->pedidoConPlataforma($mercadoLibre, 500),
            $this->pedidoConPlataforma($amazon, 300),
            $this->pedidoConPlataforma($shopify, 100),
        ]);

        $comparacion = ContabilidadReporteAssets::datosGraficaIngresosPlataforma($pedidos);

        $this->assertNotNull($comparacion);
        $this->assertSame(['Mercado Libre', 'Amazon', 'Shopify'], $comparacion['labels']);
        $this->assertSame([1500.0, 300.0, 100.0], $comparacion['values']);
        $this->assertSame(1900.0, $comparacion['total']);
        $this->assertSame([78.9, 15.8, 5.3], $comparacion['porcentajes']);

        $mayor = ContabilidadReporteAssets::datosGraficaIngresosPlataforma($pedidos, 2, 'desc');
        $this->assertSame(['Mercado Libre', 'Amazon'], $mayor['labels']);

        $menor = ContabilidadReporteAssets::datosGraficaIngresosPlataforma($pedidos, 2, 'asc');
        $this->assertSame(['Shopify', 'Amazon'], $menor['labels']);
    }

    public function test_generar_dona_plataformas_png(): void
    {
        $grafica = ContabilidadReporteAssets::datosGraficaIngresosPlataforma(new Collection([
            $this->pedidoConPlataforma(new PlataformaPago(['nombre' => 'Stripe']), 18032),
            $this->pedidoConPlataforma(new PlataformaPago(['nombre' => 'Paypal']), 1167),
        ]));

        $dona = ContabilidadReporteAssets::generarDonaPlataformasPng($grafica ?? []);

        $this->assertNotNull($dona);
        $this->assertNotSame('', $dona['base64']);
        $this->assertCount(2, $dona['segmentos']);
        $this->assertEqualsWithDelta(93.4, $dona['segmentos'][0]['pct'], 0.5);
    }

    public function test_datos_grafica_ingresos_plataforma_retorna_null_sin_ingresos(): void
    {
        $pedidos = new Collection([
            $this->pedidoConPlataforma(new PlataformaPago(['nombre' => 'Sin ventas']), 0),
        ]);

        $this->assertNull(ContabilidadReporteAssets::datosGraficaIngresosPlataforma($pedidos));
    }

    private function pedidoConPlataforma(PlataformaPago $plataforma, float $ventaTotal): Pedido
    {
        $pedido = new Pedido(['venta_total' => $ventaTotal]);
        $pedido->setRelation('plataformaPago', $plataforma);

        return $pedido;
    }
}
