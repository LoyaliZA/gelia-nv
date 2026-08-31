<?php

namespace Tests\Unit\Reportes;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Services\Reportes\PagosPedidos\AplicarFiltrosReportePagosPedidosQuery;
use App\Services\Reportes\PagosPedidos\AplicarFiltrosReporteVouchersValidadosQuery;
use App\Services\Reportes\PagosPedidos\CalcularMetricasReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\CalcularMetricasReporteVouchersValidadosService;
use App\Services\Reportes\PagosPedidos\EstimarExportacionReportePagosPedidosService;
use Mockery;
use Tests\TestCase;

class EstimarExportacionReportePagosPedidosTest extends TestCase
{
    public function test_estima_vouchers_y_marca_pesado_con_calidad_alta(): void
    {
        $usuario = Mockery::mock(User::class);

        $metricasPedido = Mockery::mock(CalcularMetricasReportePagosPedidosService::class);
        $metricasVouchers = Mockery::mock(CalcularMetricasReporteVouchersValidadosService::class);
        $filtrosPedido = Mockery::mock(AplicarFiltrosReportePagosPedidosQuery::class);
        $filtrosVouchers = Mockery::mock(AplicarFiltrosReporteVouchersValidadosQuery::class);

        $metricasVouchers->shouldReceive('ejecutar')
            ->once()
            ->with($usuario, Mockery::type('array'))
            ->andReturn([
                'exhibiciones_visibles' => 45,
                'pedidos_relacionados' => 12,
            ]);

        $items = array_map(fn () => new PedidoBmaCierrePagoItem([
            'ruta_archivo_snapshot' => 'evidencias/test.jpg',
        ]), array_fill(0, 35, null));

        $filtrosVouchers->shouldReceive('itemsVisibles')
            ->once()
            ->andReturn($items);

        $service = new EstimarExportacionReportePagosPedidosService(
            $metricasPedido,
            $metricasVouchers,
            $filtrosPedido,
            $filtrosVouchers,
        );

        $resultado = $service->ejecutar($usuario, [
            'tipo_reporte' => 'vouchers',
            'formato' => 'pdf',
            'calidad_imagen' => 'alta',
            'incluir_vouchers' => true,
        ]);

        $this->assertSame('vouchers', $resultado['tipo_reporte']);
        $this->assertSame(45, $resultado['exhibiciones']);
        $this->assertSame(35, $resultado['vouchers']);
        $this->assertTrue($resultado['pesado']);
        $this->assertGreaterThan(1_000_000, $resultado['tamano_bytes']);
    }

    public function test_estima_pedido_sin_marcar_pesado_en_alcance_pequeno(): void
    {
        config([
            'reportes_pagos.exportacion.pesado_pedidos' => 80,
            'reportes_pagos.exportacion.pesado_exhibiciones' => 200,
            'reportes_pagos.exportacion.pesado_vouchers' => 150,
            'reportes_pagos.exportacion.pesado_bytes' => 15_000_000,
        ]);

        $usuario = Mockery::mock(User::class);

        $metricasPedido = Mockery::mock(CalcularMetricasReportePagosPedidosService::class);
        $metricasVouchers = Mockery::mock(CalcularMetricasReporteVouchersValidadosService::class);
        $filtrosPedido = Mockery::mock(AplicarFiltrosReportePagosPedidosQuery::class);
        $filtrosVouchers = Mockery::mock(AplicarFiltrosReporteVouchersValidadosQuery::class);

        $metricasPedido->shouldReceive('ejecutar')
            ->once()
            ->andReturn([
                'pedidos_validados' => 5,
                'exhibiciones_incluidas' => 8,
            ]);

        $metricasVouchers->shouldNotReceive('ejecutar');

        $service = new EstimarExportacionReportePagosPedidosService(
            $metricasPedido,
            $metricasVouchers,
            $filtrosPedido,
            $filtrosVouchers,
        );

        $resultado = $service->ejecutar($usuario, [
            'tipo_reporte' => 'pedido',
            'formato' => 'csv_resumen',
            'incluir_vouchers' => false,
        ]);

        $this->assertSame('pedido', $resultado['tipo_reporte']);
        $this->assertSame(5, $resultado['pedidos']);
        $this->assertFalse($resultado['pesado']);
    }
}
