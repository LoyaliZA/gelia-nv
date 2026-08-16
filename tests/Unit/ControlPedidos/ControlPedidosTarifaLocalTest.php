<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Services\ControlPedidos\ResuelveDatosPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ControlPedidosTarifaLocalTest extends TestCase
{
    use RefreshDatabase;

    public function test_tarifa_fija_local(): void
    {
        $paq = new CatalogoPaqueteriaPedido([
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
            'tarifa_monto' => 85.5,
        ]);

        $this->assertSame(85.5, $paq->calcularCostoEnvio(null));
        $this->assertSame(85.5, $paq->calcularCostoEnvio(10.0));
    }

    public function test_tarifa_por_peso_kg(): void
    {
        $paq = new CatalogoPaqueteriaPedido([
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_POR_PESO,
            'tarifa_monto' => 25,
            'tarifa_unidad_peso' => CatalogoPaqueteriaPedido::UNIDAD_KG,
            'tarifa_paso_peso' => 1,
        ]);

        // 2.3 kg → ceil(2.3/1)=3 → 75
        $this->assertSame(75.0, $paq->calcularCostoEnvio(2.3));
        $this->assertNull($paq->calcularCostoEnvio(null));
    }

    public function test_tarifa_por_peso_gramos(): void
    {
        $paq = new CatalogoPaqueteriaPedido([
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_POR_PESO,
            'tarifa_monto' => 10,
            'tarifa_unidad_peso' => CatalogoPaqueteriaPedido::UNIDAD_G,
            'tarifa_paso_peso' => 500,
        ]);

        // 1.2 kg = 1200 g → ceil(1200/500)=3 → 30
        $this->assertSame(30.0, $paq->calcularCostoEnvio(1.2));
    }

    public function test_comercial_ignora_tarifa(): void
    {
        $paq = new CatalogoPaqueteriaPedido([
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
            'tarifa_monto' => 99,
        ]);

        $this->assertNull($paq->calcularCostoEnvio(5.0));
    }

    public function test_envio_por_cobrar_anula_costo_en_atributos(): void
    {
        $this->seedPaqueteriaLocalFija();

        $resolver = new class
        {
            use ResuelveDatosPedidoBma;

            public function pub(array $datos): array
            {
                return $this->atributosPedidoBase($datos);
            }
        };

        $attrs = $resolver->pub([
            'total_mercancia' => 100,
            'costo_envio' => 50,
            'envio_por_cobrar' => true,
            'cliente_proporciona_guia' => false,
            'catalogo_paqueteria_id' => DB::table('catalogo_paqueterias_pedido')->value('id'),
        ]);

        $this->assertNull($attrs['costo_envio']);
        $this->assertTrue($attrs['envio_por_cobrar']);
    }

    public function test_guia_cliente_anula_costo_y_por_cobrar(): void
    {
        $resolver = new class
        {
            use ResuelveDatosPedidoBma;

            public function pub(array $datos): array
            {
                return $this->atributosPedidoBase($datos);
            }
        };

        $attrs = $resolver->pub([
            'total_mercancia' => 100,
            'costo_envio' => 50,
            'envio_por_cobrar' => true,
            'cliente_proporciona_guia' => true,
            'aplica_seguro' => true,
        ]);

        $this->assertNull($attrs['costo_envio']);
        $this->assertFalse($attrs['aplica_seguro']);
        $this->assertFalse($attrs['envio_por_cobrar']);
        $this->assertSame(0.0, (float) $attrs['costo_seguro']);
    }

    public function test_tarifa_local_rellena_costo_vacio(): void
    {
        $id = $this->seedPaqueteriaLocalFija(40);

        $resolver = new class
        {
            use ResuelveDatosPedidoBma;

            public function pub(array $datos): array
            {
                return $this->atributosPedidoBase($datos);
            }
        };

        $attrs = $resolver->pub([
            'total_mercancia' => 100,
            'costo_envio' => '',
            'catalogo_paqueteria_id' => $id,
            'envio_por_cobrar' => false,
            'cliente_proporciona_guia' => false,
        ]);

        $this->assertSame(40.0, (float) $attrs['costo_envio']);
    }

    public function test_override_manual_se_respeta(): void
    {
        $id = $this->seedPaqueteriaLocalFija(40);

        $resolver = new class
        {
            use ResuelveDatosPedidoBma;

            public function pub(array $datos): array
            {
                return $this->atributosPedidoBase($datos);
            }
        };

        $attrs = $resolver->pub([
            'total_mercancia' => 100,
            'costo_envio' => 12.5,
            'catalogo_paqueteria_id' => $id,
            'envio_por_cobrar' => false,
            'cliente_proporciona_guia' => false,
        ]);

        $this->assertSame(12.5, (float) $attrs['costo_envio']);
    }

    private function seedPaqueteriaLocalFija(float $monto = 85): int
    {
        $now = now();

        return (int) DB::table('catalogo_paqueterias_pedido')->insertGetId([
            'nombre' => 'TAXI TEST '.uniqid(),
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'permite_costo_diferido' => true,
            'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
            'tarifa_monto' => $monto,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
