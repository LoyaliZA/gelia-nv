<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoReexpedicionPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Services\ControlPedidos\AplicarCostoReexpedicion;
use App\Services\ControlPedidos\GestionarReexpedicionPedidoService;
use Tests\TestCase;

class ControlPedidosMejoras28Test extends TestCase
{
    // Sin RefreshDatabase: el entorno Sail apunta a MySQL con datos reales.
    // Los tests de catálogo usan nombres/CP únicos y limpian al final.

    public function test_aplicar_costo_reexpedicion_no_duplica(): void
    {
        $primero = AplicarCostoReexpedicion::siguiente(100, 0, 50);
        $this->assertSame(150.0, $primero['costo_envio']);
        $this->assertSame(50.0, $primero['costo_aplicado']);

        $segundaPasada = AplicarCostoReexpedicion::siguiente($primero['costo_envio'], $primero['costo_aplicado'], 50);
        $this->assertSame(150.0, $segundaPasada['costo_envio']);
        $this->assertSame(50.0, $segundaPasada['costo_aplicado']);

        $sinMatch = AplicarCostoReexpedicion::siguiente(150, 50, 0);
        $this->assertSame(100.0, $sinMatch['costo_envio']);
        $this->assertSame(0.0, $sinMatch['costo_aplicado']);
    }

    public function test_buscar_reexpedicion_por_cp_y_paqueteria(): void
    {
        $paq = CatalogoPaqueteriaPedido::create([
            'nombre' => 'FEDEX_BUSCA_' . uniqid(),
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'activo' => true,
        ]);

        $cp = '6' . substr((string) time(), -4);
        CatalogoReexpedicionPedido::create([
            'codigo_postal' => $cp,
            'paqueteria_id' => $paq->id,
            'costo_adicional' => 85.5,
            'activo' => true,
        ]);

        $match = CatalogoReexpedicionPedido::buscarActivo($cp, $paq->id);
        $this->assertNotNull($match);
        $this->assertEquals(85.5, (float) $match->costo_adicional);

        $this->assertNull(CatalogoReexpedicionPedido::buscarActivo($cp, $paq->id + 999999));
        $this->assertNull(CatalogoReexpedicionPedido::buscarActivo('99999', $paq->id));

        CatalogoReexpedicionPedido::where('codigo_postal', $cp)->delete();
        $paq->delete();
    }

    public function test_tipo_caja_guarda_dimensiones(): void
    {
        $caja = CatalogoTipoCajaPedido::create([
            'nombre' => 'CAJA_DIM_' . uniqid(),
            'peso_volumetrico' => 2.5,
            'largo' => 30,
            'ancho' => 20,
            'alto' => 15,
            'medidas' => '30 x 20 x 15 cm',
            'activo' => true,
        ]);

        $caja->refresh();
        $this->assertEquals(30.0, (float) $caja->largo);
        $this->assertEquals(20.0, (float) $caja->ancho);
        $this->assertEquals(15.0, (float) $caja->alto);
        $caja->delete();
    }

    public function test_guardar_cp_con_varias_paqueterias_y_override(): void
    {
        $fedex = CatalogoPaqueteriaPedido::create([
            'nombre' => 'FEDEX_M28_' . uniqid(),
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'activo' => true,
        ]);
        $dhl = CatalogoPaqueteriaPedido::create([
            'nombre' => 'DHL_M28_' . uniqid(),
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'activo' => true,
        ]);

        $cp = '9' . substr((string) time(), -4);
        $res = app(GestionarReexpedicionPedidoService::class)->guardarPorCodigoPostal($cp, 50, [
            ['paqueteria_id' => $fedex->id],
            ['paqueteria_id' => $dhl->id, 'costo_adicional' => 120],
        ]);

        $this->assertSame(2, $res['creados']);
        $this->assertEquals(50.0, (float) CatalogoReexpedicionPedido::buscarActivo($cp, $fedex->id)->costo_adicional);
        $this->assertEquals(120.0, (float) CatalogoReexpedicionPedido::buscarActivo($cp, $dhl->id)->costo_adicional);

        $res2 = app(GestionarReexpedicionPedidoService::class)->guardarPorCodigoPostal($cp, 55, [
            ['paqueteria_id' => $fedex->id],
        ]);
        $this->assertSame(1, $res2['actualizados']);
        $this->assertEquals(55.0, (float) CatalogoReexpedicionPedido::buscarActivo($cp, $fedex->id)->costo_adicional);

        CatalogoReexpedicionPedido::where('codigo_postal', $cp)->delete();
        $fedex->delete();
        $dhl->delete();
    }

    public function test_importar_csv_reexpedicion(): void
    {
        $sufijo = uniqid();
        $nombreA = 'PAQ_A_' . $sufijo;
        $nombreB = 'PAQ_B_' . $sufijo;
        CatalogoPaqueteriaPedido::create([
            'nombre' => $nombreA,
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'activo' => true,
        ]);
        CatalogoPaqueteriaPedido::create([
            'nombre' => $nombreB,
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'activo' => true,
        ]);

        $cp1 = '8' . substr((string) time(), -4);
        $cp2 = '7' . substr((string) time(), -4);
        $path = tempnam(sys_get_temp_dir(), 'reex');
        file_put_contents($path, "codigo_postal,paqueterias,costo_adicional,activo\n{$cp1},{$nombreA}|{$nombreB},80,1\n{$cp2},{$nombreA},99,1\n");

        $res = app(GestionarReexpedicionPedidoService::class)->importarCsv($path);
        @unlink($path);

        $this->assertSame([], $res['errores']);
        $this->assertSame(3, $res['creados']);
        $this->assertSame(2, CatalogoReexpedicionPedido::where('codigo_postal', $cp1)->count());
        $this->assertEquals(99.0, (float) CatalogoReexpedicionPedido::where('codigo_postal', $cp2)->value('costo_adicional'));

        CatalogoReexpedicionPedido::whereIn('codigo_postal', [$cp1, $cp2])->delete();
        CatalogoPaqueteriaPedido::whereIn('nombre', [$nombreA, $nombreB])->delete();
    }
}
