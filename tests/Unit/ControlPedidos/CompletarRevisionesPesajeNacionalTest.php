<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Support\ControlPedidos\Nacional\CompletarRevisionesPesajeNacional;
use Tests\TestCase;

class CompletarRevisionesPesajeNacionalTest extends TestCase
{
    public function test_todo_ok_completa_uuids_como_bueno(): void
    {
        $idA = '11111111-1111-4111-8111-111111111111';
        $idB = '22222222-2222-4222-8222-222222222222';
        $resultado = CompletarRevisionesPesajeNacional::completar([
            ['id' => $idA, 'sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 2],
            ['id' => $idB, 'sku' => 'SKU-B', 'descripcion' => 'Pieza B', 'cantidad' => 1],
        ], [], true);

        $this->assertCount(2, $resultado);
        $this->assertSame($idA, $resultado[0]['client_uuid']);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_BUENO, $resultado[0]['estado_fisico']);
        $this->assertSame(2, $resultado[0]['cantidad_encontrada']);
        $this->assertSame($idB, $resultado[1]['client_uuid']);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_BUENO, $resultado[1]['estado_fisico']);
    }

    public function test_un_faltante_deja_el_resto_bueno(): void
    {
        $idA = '11111111-1111-4111-8111-111111111111';
        $idB = '22222222-2222-4222-8222-222222222222';
        $resultado = CompletarRevisionesPesajeNacional::completar([
            ['id' => $idA, 'sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1],
            ['id' => $idB, 'sku' => 'SKU-B', 'descripcion' => 'Pieza B', 'cantidad' => 1],
        ], [[
            'sku' => 'SKU-A',
            'descripcion' => 'Pieza A',
            'condicion' => 'faltante',
            'cantidad' => 1,
        ]], false);

        $porId = collect($resultado)->keyBy('client_uuid');
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA, $porId[$idA]['estado_fisico']);
        $this->assertSame(0, $porId[$idA]['cantidad_encontrada']);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_BUENO, $porId[$idB]['estado_fisico']);
        $this->assertSame(1, $porId[$idB]['cantidad_encontrada']);
    }

    public function test_detalle_mapea_a_regular_y_sku_ajeno_es_revision_extra(): void
    {
        $idA = '11111111-1111-4111-8111-111111111111';
        $resultado = CompletarRevisionesPesajeNacional::completar([
            ['id' => $idA, 'sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1],
        ], [[
            'sku' => 'SKU-A',
            'descripcion' => 'Pieza A',
            'condicion' => 'detalle',
            'detalle' => 'Tapa rota',
            'unica_pieza' => true,
        ], [
            'sku' => 'SKU-X',
            'descripcion' => 'No pedida',
            'condicion' => 'faltante',
        ]], false);

        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_REGULAR, $resultado[0]['estado_fisico']);
        $this->assertSame('Tapa rota', $resultado[0]['comentario']);
        $this->assertTrue($resultado[0]['unica_pieza']);
        $this->assertSame('SKU-X', $resultado[1]['sku']);
        $this->assertSame(PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA, $resultado[1]['estado_fisico']);
        $this->assertFalse($resultado[1]['pieza_solicitada']);
    }

    public function test_confirmacion_con_excepciones_falla(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La confirmación sin excepciones no admite piezas afectadas.');
        CompletarRevisionesPesajeNacional::completar([
            ['id' => '11111111-1111-4111-8111-111111111111', 'sku' => 'A', 'cantidad' => 1],
        ], [['condicion' => 'faltante', 'descripcion' => 'A']], true);
    }
}
