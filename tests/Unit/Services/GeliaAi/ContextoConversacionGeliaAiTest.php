<?php

namespace Tests\Unit\Services\GeliaAi;

use App\Services\GeliaAi\ContextoConversacionGeliaAi;
use Tests\TestCase;

class ContextoConversacionGeliaAiTest extends TestCase
{
    private ContextoConversacionGeliaAi $contexto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contexto = new ContextoConversacionGeliaAi;
    }

    public function test_producto_a_en_cedis_y_luego_b_global_sin_resultados_limpia_referencias(): void
    {
        $ctx = $this->productoAEnCedis();

        $ctx = $this->buscar($ctx, ['q' => 'PRODUCTO B'], $this->resultado('PRODUCTO B', []));

        $this->assertSame([], $ctx['productos']);
        $this->assertSame('ninguna', $ctx['coincidencia']);
        $this->assertNull($ctx['almacen']);
        $this->assertSame(['q' => 'PRODUCTO B'], $ctx['filtros']);
        $this->assertSame('sin_resultados', $ctx['consulta']['estado']);
        $this->assertStringNotContainsString('MSKY100', (string) $this->contexto->paraModelo($ctx));
        $this->assertStringNotContainsString('CEDIS', (string) $this->contexto->paraModelo($ctx));
    }

    public function test_consulta_global_exitosa_retira_almacen_anterior(): void
    {
        $ctx = $this->productoAEnCedis();

        $ctx = $this->buscar($ctx, ['q' => 'MSKY100'], $this->resultado('MSKY100', [['sku' => 'MSKY100', 'n' => 'Mandarin Sky']]));

        $this->assertNull($ctx['almacen']);
        $this->assertSame(['q' => 'MSKY100'], $ctx['filtros']);
        $this->assertSame('MSKY100', $ctx['productos'][0]['sku']);
        $this->assertSame('con_resultados', $ctx['consulta']['estado']);
    }

    public function test_consulta_nueva_con_coincidencias_reemplaza_productos(): void
    {
        $ctx = $this->productoAEnCedis();

        $ctx = $this->buscar(
            $ctx,
            ['q' => 'oud', 'almacen' => 'TIENDA1'],
            $this->resultado('oud', [['sku' => 'OUD1', 'n' => 'Oud Uno'], ['sku' => 'OUD2', 'n' => 'Oud Dos']], 'parcial', ['codigo' => 'TIENDA1', 'nombre' => 'Tienda Uno']),
        );

        $this->assertSame(['OUD1', 'OUD2'], array_column($ctx['productos'], 'sku'));
        $this->assertSame('parcial', $ctx['coincidencia']);
        $this->assertSame('TIENDA1', $ctx['almacen']['codigo']);
        $this->assertSame(['q' => 'oud', 'almacen' => 'TIENDA1'], $ctx['filtros']);
    }

    public function test_error_no_presenta_referencias_antiguas_como_resultado_actual(): void
    {
        $ctx = $this->productoAEnCedis();

        $ctx = $this->buscar($ctx, ['q' => 'PRODUCTO B', 'almacen' => 'NORTE'], [
            'ok' => false,
            'error_code' => 'almacen_no_encontrado',
            'error' => 'No encontré ese almacén entre los almacenes activos.',
        ]);

        $this->assertNull($ctx['coincidencia']);
        $this->assertSame(['estado' => 'error', 'error_code' => 'almacen_no_encontrado', 'q' => 'PRODUCTO B', 'almacen' => 'NORTE'], $ctx['consulta']);
        // Las referencias previas se conservan como antecedente, marcadas por el error de la última consulta.
        $this->assertSame('MSKY100', $ctx['productos'][0]['sku']);
        $estado = (string) $this->contexto->paraModelo($ctx);
        $this->assertStringContainsString('"ultima_consulta_inventario":{"estado":"error"', $estado);
        $this->assertStringNotContainsString('"coincidencia":"exacta"', $estado);
    }

    public function test_referencias_de_inventario_solo_acompanan_si_la_tarea_es_inventario(): void
    {
        $ctx = $this->productoAEnCedis();
        $this->assertStringContainsString('MSKY100', (string) $this->contexto->paraModelo($ctx));

        $ctx = $this->contexto->aplicarResultado($ctx, 'consultar_ayuda_modulo', ['modulo' => 'listados'], ['ok' => true, 'modulo' => 'listados']);

        $estado = (string) $this->contexto->paraModelo($ctx);
        $this->assertStringContainsString('ayuda:listados', $estado);
        $this->assertStringNotContainsString('MSKY100', $estado);
        $this->assertStringNotContainsString('CEDIS', $estado);
    }

    public function test_almacen_heredable_solo_en_continuaciones_validas(): void
    {
        $ctx = $this->productoAEnCedis();

        $this->assertSame('CEDIS', $this->contexto->almacenHeredable($ctx, '¿Y cuántos MSKY100 están apartados?', 'MSKY100'));
        $this->assertSame('CEDIS', $this->contexto->almacenHeredable($ctx, '¿Y del ABC200 cuántos hay ahí?', 'ABC200'));
        $this->assertNull($this->contexto->almacenHeredable($ctx, '¿Cuántos ABC200 hay?', 'ABC200'));
        $this->assertNull($this->contexto->almacenHeredable($ctx, '¿Cuántos MSKY100 hay en total?', 'MSKY100'));
        $this->assertNull($this->contexto->almacenHeredable($ctx, '¿Cuántos MSKY100 hay en todos los almacenes?', 'MSKY100'));

        $ayuda = $this->contexto->aplicarResultado($ctx, 'consultar_ayuda_modulo', [], ['ok' => true, 'modulo' => 'listados']);
        $this->assertNull($this->contexto->almacenHeredable($ayuda, '¿Y cuántos MSKY100 están apartados?', 'MSKY100'));
    }

    /** @return array<string, mixed> */
    private function productoAEnCedis(): array
    {
        return $this->buscar(
            $this->contexto->normalizar(null),
            ['q' => 'MSKY100', 'almacen' => 'CEDIS'],
            $this->resultado('MSKY100', [['sku' => 'MSKY100', 'n' => 'Mandarin Sky']], 'exacta', ['codigo' => 'CEDIS', 'nombre' => 'CEDIS Central']),
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $res
     * @return array<string, mixed>
     */
    private function buscar(array $ctx, array $args, array $res): array
    {
        return $this->contexto->aplicarResultado($ctx, 'buscar_producto_inventario', $args, $res);
    }

    /**
     * @param  list<array{sku: string, n: string}>  $items
     * @param  array{codigo: string, nombre: string}|null  $almacen
     * @return array<string, mixed>
     */
    private function resultado(string $q, array $items, string $coincidencia = 'exacta', ?array $almacen = null): array
    {
        return [
            'ok' => true,
            'q' => $q,
            'coincidencia' => $items === [] ? 'ninguna' : $coincidencia,
            'n' => count($items),
            'almacen_filtro' => $almacen,
            'items' => $items,
        ];
    }
}
