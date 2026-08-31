<?php

namespace Tests\Unit\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\Reportes\PagosPedidos\AplicarFiltrosReporteVouchersValidadosQuery;
use App\Services\Reportes\PagosPedidos\ExportarReporteVouchersValidadosCsvService;
use App\Support\Reportes\ExhibicionVouchersValidadosMapper;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ExportarReporteVouchersValidadosCsvTest extends TestCase
{
    public function test_csv_usa_montos_numericos_sin_formato_decorativo(): void
    {
        Storage::fake('local');

        $usuario = Mockery::mock(User::class);
        $usuario->shouldReceive('can')->with('reportes.pagos_pedidos.ver_evidencias')->andReturn(false);

        $cierre = new PedidoBmaCierrePago([
            'folio_snapshot' => 'G-100',
            'folio_remision_snapshot' => 'R-1',
            'saf_aplicado' => 150.5,
            'validado_at' => now(),
        ]);
        $item = new PedidoBmaCierrePagoItem([
            'id' => 99,
            'pedido_bma_pago_id' => 10,
            'numero_exhibicion' => 1,
            'monto_snapshot' => 1234.5,
            'forma_pago_snapshot' => 'transferencia',
            'banco_snapshot' => 'BBVA',
            'referencia_snapshot' => 'REF-1',
            'fecha_pago_snapshot' => now(),
            'capturado_at_snapshot' => now(),
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
            'ruta_archivo_snapshot' => 'pagos/test/voucher.jpg',
        ]);
        $item->setRelation('cierre', $cierre);

        $filtros = Mockery::mock(AplicarFiltrosReporteVouchersValidadosQuery::class);
        $filtros->shouldReceive('posiblesDuplicados')->andReturn([]);
        $filtros->shouldReceive('itemsVisibles')->andReturn([$item]);

        $service = new ExportarReporteVouchersValidadosCsvService(
            $filtros,
            new ExhibicionVouchersValidadosMapper(),
        );
        $archivo = $service->guardar($usuario, ['tipo_reporte' => 'vouchers']);

        $contenido = Storage::disk('local')->get($archivo['path']);
        $this->assertStringContainsString('Monto', $contenido);
        $this->assertStringContainsString('1234.5', $contenido);
        $this->assertStringNotContainsString('1,234', $contenido);
        $this->assertStringContainsString('150.50', $contenido);
    }
}
