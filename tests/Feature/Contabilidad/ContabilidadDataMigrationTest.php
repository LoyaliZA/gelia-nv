<?php

namespace Tests\Feature\Contabilidad;

use App\Models\Contabilidad\CatalogoEstatusPago;
use App\Models\Contabilidad\LotePago;
use App\Models\Contabilidad\Pedido;
use App\Models\Contabilidad\PedidoLinea;
use App\Models\Contabilidad\PlataformaPago;
use Database\Seeders\ContabilidadCatalogosSeeder;
use Database\Seeders\ContabilidadLegacyDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContabilidadDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    private array $legacyMeta;

    protected function setUp(): void
    {
        parent::setUp();

        $payload = json_decode(
            File::get(database_path('data/contabilidad_legacy.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->legacyMeta = $payload['meta'];
    }

    public function test_legacy_data_seeder_carga_conteos_y_relaciones(): void
    {
        $this->seed(ContabilidadCatalogosSeeder::class);
        $this->seed(ContabilidadLegacyDataSeeder::class);

        $this->assertSame($this->legacyMeta['plataformas'], PlataformaPago::count());
        $this->assertSame($this->legacyMeta['lotes'], LotePago::count());
        $this->assertSame($this->legacyMeta['pedidos'], Pedido::count());
        $this->assertSame($this->legacyMeta['lineas'], PedidoLinea::count());

        $sumaVentas = (float) Pedido::query()->sum('venta_total');
        $this->assertEqualsWithDelta(
            (float) $this->legacyMeta['venta_total_sum'],
            $sumaVentas,
            0.01
        );

        $transferidosSinLote = Pedido::query()
            ->where('estatus_pago_id', CatalogoEstatusPago::TRANSFERIDO)
            ->whereNull('lote_pago_id')
            ->count();

        $this->assertSame(0, $transferidosSinLote);

        $lineasHuerfanas = PedidoLinea::query()
            ->whereDoesntHave('pedido')
            ->count();

        $this->assertSame(0, $lineasHuerfanas);

        $pedido = Pedido::query()->with(['lineas', 'plataformaPago', 'lotePago'])->first();
        $this->assertNotNull($pedido);
        $this->assertNotNull($pedido->plataformaPago);
        $this->assertGreaterThan(0, $pedido->lineas->count());
    }

    public function test_desglose_comision_aproxima_comision_plataforma(): void
    {
        $this->seed(ContabilidadCatalogosSeeder::class);
        $this->seed(ContabilidadLegacyDataSeeder::class);

        $desajustes = Pedido::query()
            ->get()
            ->filter(function (Pedido $pedido) {
                $calculado = round((float) $pedido->comision_base + (float) $pedido->comision_iva, 2);
                $almacenado = round((float) $pedido->comision_plataforma, 2);

                return abs($calculado - $almacenado) > 0.01;
            })
            ->count();

        $this->assertSame(0, $desajustes, 'Hay pedidos cuyo desglose de comisión no coincide con comision_plataforma.');
    }
}
