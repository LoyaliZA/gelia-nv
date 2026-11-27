<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Cliente;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafCuenta;
use App\Models\User;
use App\Services\ControlPedidos\Nacional\Multienvio\ProyeccionMultienvio;
use App\Support\ControlPedidos\Nacional\LecturaConsultaNacional;
use App\Support\ControlPedidos\Nacional\ResumenGrupoMultienvio;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\ControlPedidos\Concerns\EscenarioMultienvioNacional;
use Tests\TestCase;

/** Datos de grupo y de cajas para la UI: resumen por lote, cajas de origen y rutas de datos de pago. */
class MultienvioNacionalResumenTest extends TestCase
{
    use EscenarioMultienvioNacional;
    use RefreshDatabase;

    private User $ventas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
        Storage::disk('public')->put('pedidos_bma/soporte.pdf', 'PDF');
        $this->ventas = User::factory()->create();
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.ver_listado', 'web'));
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'MEV-PR', 'nombre_visual' => 'Pesaje respondido', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }

    private function fase(string $fase): CatalogoEstatusPedido
    {
        return CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => $fase],
            ['codigo_interno' => 'MEV-'.$fase, 'nombre_visual' => $fase, 'color_hex' => '#111111', 'orden' => 5, 'activo' => true]
        );
    }

    /** @return array<int, PedidoBma> miembros por orden, tras confirmar */
    private function confirmadoConFases(array $fases): array
    {
        static $n = 0;
        $n++;
        [, $grupo, $cajas] = $this->escenario();
        $this->borrador($grupo, $cajas, [600, 500, 400], ["G$n-A", "G$n-B", "G$n-C"]);
        $grupo = $grupo->fresh();
        $this->confirmar($grupo);
        $miembros = [];
        foreach (PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->orderBy('multienvio_orden')->get() as $m) {
            $m->forceFill(['catalogo_estatus_pedido_id' => $this->fase($fases[$m->multienvio_orden - 1])->id])->save();
            $miembros[$m->multienvio_orden] = $m->fresh();
        }

        return $miembros;
    }

    private function usuario(array $permisos): User
    {
        $u = User::factory()->create();
        foreach ($permisos as $permiso) {
            $u->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    private function consultas(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    /** @param  list<int>  $ids */
    private function pagina(array $ids): \Illuminate\Support\Collection
    {
        return PedidoBma::query()->with(['estatus'])->whereIn('id', $ids)->orderBy('id')->get();
    }

    public function test_resumen_por_lote_tiene_consultas_constantes_entre_2_y_6_grupos(): void
    {
        $ids2 = [];
        for ($i = 0; $i < 2; $i++) {
            $ids2 = array_merge($ids2, array_map(fn ($m) => $m->id, $this->confirmadoConFases(['EN_CEDIS', 'PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO'])));
        }
        ResumenGrupoMultienvio::paraPagina($this->pagina($ids2), $this->ventas); // calienta permisos/roles en caché
        $pagina2 = $this->pagina($ids2);
        $con2 = $this->consultas(fn () => ResumenGrupoMultienvio::paraPagina($pagina2, $this->ventas));

        $ids6 = $ids2;
        for ($i = 0; $i < 4; $i++) {
            $ids6 = array_merge($ids6, array_map(fn ($m) => $m->id, $this->confirmadoConFases(['EN_CEDIS', 'PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO'])));
        }
        $pagina = $this->pagina($ids6);
        $con6 = $this->consultas(fn () => ResumenGrupoMultienvio::paraPagina($pagina, $this->ventas));

        $this->assertSame($con2, $con6, 'Las consultas del resumen deben ser constantes por página.');
        $this->assertLessThanOrEqual(10, $con6);
        $pagina->each(function (PedidoBma $p) {
            $mev = $p->getAttribute('multienvio');
            $this->assertNotNull($mev);
            $this->assertSame(3, $mev['n_total']);
            $this->assertFalse($mev['parcial']);
            $this->assertCount(3, $mev['hermanos']);
            $this->assertSame((int) $p->multienvio_orden, $mev['orden']);
            $this->assertSame('activo', $mev['estado']);
        });
    }

    public function test_hermanos_ocultos_no_aparecen_y_marca_parcial(): void
    {
        $m = $this->confirmadoConFases(['EN_CEDIS', 'EN_CEDIS', 'PESAJE_RESPONDIDO']);
        $oculto = $m[3];
        $cedis = $this->usuario(['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos']);

        $pagina = $this->pagina([$m[1]->id, $m[2]->id]);
        ResumenGrupoMultienvio::paraPagina($pagina, $cedis);

        $mev = $pagina->first()->getAttribute('multienvio');
        $this->assertTrue($mev['parcial']);
        $this->assertCount(2, $mev['hermanos']);
        $this->assertSame(2, $mev['n_total'], 'n_total solo cuenta miembros visibles cuando hay ocultos.');
        $json = json_encode($pagina->map->getAttribute('multienvio')->all());
        foreach ([$oculto->folio, $oculto->folio_remision] as $dato) {
            $this->assertStringNotContainsString((string) $dato, $json, "Se filtró un dato del envío oculto: $dato");
        }
    }

    public function test_hermano_trae_solo_datos_ligeros_y_estado_de_hechos(): void
    {
        $m = $this->confirmadoConFases(['EN_CEDIS', 'PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO']);
        $m[2]->forceFill(['numero_rastreo' => 'RAST-1', 'pago_validado_at' => now()])->save();
        $pagina = $this->pagina([$m[1]->id]);
        ResumenGrupoMultienvio::paraPagina($pagina, $this->ventas);

        $hermanos = collect($pagina->first()->getAttribute('multienvio')['hermanos'])->keyBy('orden');
        $this->assertSame(['id', 'orden', 'folio', 'folio_remision', 'estatus_nombre', 'estatus_fase', 'pago_validado', 'tiene_remision', 'tiene_guia'], array_keys($hermanos[2]));
        $this->assertTrue($hermanos[2]['tiene_guia']);
        $this->assertTrue($hermanos[2]['pago_validado']);
        $this->assertFalse($hermanos[3]['tiene_guia']);
        $this->assertFalse($hermanos[3]['pago_validado']);
        $this->assertSame('EN_CEDIS', $hermanos[1]['estatus_fase']);
    }

    public function test_pedido_sin_grupo_no_recibe_atributo(): void
    {
        $p = $this->pedidoNacional();
        $pagina = $this->pagina([$p->id]);
        ResumenGrupoMultienvio::paraPagina($pagina, $this->ventas);

        // `multienvio` también es el nombre de una relación: se inspeccionan los atributos para no disparar su carga perezosa.
        $this->assertArrayNotHasKey('multienvio', $pagina->first()->getAttributes());
    }

    public function test_origen_en_configuracion_recibe_resumen_de_grupo(): void
    {
        [$origen] = $this->escenarioConBorrador();
        $pagina = $this->pagina([$origen->id]);
        ResumenGrupoMultienvio::paraPagina($pagina, $this->ventas);

        $mev = $pagina->first()->getAttributes()['multienvio'];
        $this->assertSame('configurando', $mev['estado']);
        $this->assertSame([], $mev['hermanos']);
    }

    public function test_cajas_origen_solo_para_la_duena_en_configurando_y_sin_cajas_movidas(): void
    {
        [$origen, , $cajas] = $this->escenarioConBorrador();
        $otra = $this->usuario(['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos']);
        $origen->forceFill(['catalogo_estatus_pedido_id' => $this->fase('EN_CEDIS')->id])->save();

        $p = app(ProyeccionMultienvio::class)->de($origen->fresh(), $this->ventas);
        $this->assertCount(3, $p['cajas_origen']);
        $this->assertSame([0, 1, 2], array_column($p['cajas_origen'], 'orden'));
        $this->assertSame(['uuid_operativo', 'orden', 'tipo', 'peso_cobrado_kg', 'largo', 'ancho', 'alto', 'evidencias'], array_keys($p['cajas_origen'][0]));
        $this->assertSame('CAJA #202', $p['cajas_origen'][0]['tipo']);
        $this->assertSame([], $p['cajas_origen'][0]['evidencias']);
        $this->assertCount(1, $p['cajas_origen'][1]['evidencias']);
        $this->assertCount(1, $p['cajas_origen'][2]['evidencias']);
        $this->assertSame(['id', 'url'], array_keys($p['cajas_origen'][1]['evidencias'][0]));
        $this->assertStringContainsString('/documentos/', $p['cajas_origen'][1]['evidencias'][0]['url']);
        $this->assertStringNotContainsString('general.jpg', json_encode($p['cajas_origen']));

        $noDuena = app(ProyeccionMultienvio::class)->de($origen->fresh(), $otra);
        $this->assertArrayHasKey('cajas_origen', $noDuena);
        $this->assertNull($noDuena['cajas_origen']);
        $this->assertStringNotContainsString($cajas[0]->uuid_operativo, json_encode($noDuena));

        // Una caja ya movida a otro pedido no se lista.
        $cajas[2]->forceFill(['pedido_bma_id' => $this->pedidoNacional()->id])->save();
        $this->assertCount(2, app(ProyeccionMultienvio::class)->de($origen->fresh(), $this->ventas)['cajas_origen']);
    }

    public function test_cajas_origen_es_nula_fuera_de_configurando(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $this->confirmar($grupo);

        $this->assertNull(app(ProyeccionMultienvio::class)->de($origen->fresh(), $this->ventas)['cajas_origen']);
    }

    public function test_cajas_resumen_solo_para_la_duena_con_decision_pendiente(): void
    {
        $pedido = $this->pedidoNacional();
        foreach ([10, 12, 14] as $peso) {
            $this->caja($pedido, $peso);
        }
        $otra = $this->usuario(['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos']);
        $pedido->forceFill(['catalogo_estatus_pedido_id' => $this->fase('EN_CEDIS')->id])->save();

        $p = app(ProyeccionMultienvio::class)->de($pedido->fresh(), $this->ventas);
        $this->assertTrue($p['evaluacion']['elegible']);
        $this->assertSame(3, $p['cajas_resumen']['n']);
        $this->assertSame(['CAJA #202'], $p['cajas_resumen']['tipos']);
        $this->assertEquals(36.0, $p['cajas_resumen']['peso_total_kg']);
        $this->assertNull($p['cajas_origen']);

        $this->assertNull(app(ProyeccionMultienvio::class)->de($pedido->fresh(), $otra)['cajas_resumen']);
    }

    public function test_cajas_resumen_nula_si_no_es_elegible(): void
    {
        $pedido = $this->pedidoNacional();
        $this->caja($pedido, 10);

        $p = app(ProyeccionMultienvio::class)->de($pedido->fresh(), $this->ventas);
        $this->assertFalse($p['evaluacion']['elegible']);
        $this->assertNull($p['cajas_resumen']);
    }

    public function test_envios_traen_pagado_pendiente_y_saf_aplicado_en_lote(): void
    {
        $m = $this->confirmadoConFases(['PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO']);
        $this->saf($m[1], 150.50, 'aplicado');
        $this->saf($m[1], 20, 'aplicado');
        $this->saf($m[1], 99, 'liberado');
        $this->saf($m[2], 30, 'reservado');

        $envios = collect(app(ProyeccionMultienvio::class)->de($m[1], $this->ventas)['envios'])->keyBy('orden');
        $this->assertEquals(170.5, $envios[1]['saf_aplicado']);
        $this->assertEquals(0, $envios[2]['saf_aplicado'], 'Una reserva aún no es saldo aplicado.');
        $this->assertSame(0.0, (float) $envios[3]['saf_aplicado']);
        $this->assertSame(0.0, (float) $envios[3]['pagado']);
        $this->assertEquals($envios[3]['total_a_cobrar'], $envios[3]['pendiente']);
        $this->assertArrayHasKey('estado_pago', $envios[1]);
    }

    private function saf(PedidoBma $pedido, float $monto, string $estado): void
    {
        static $credito = null;
        if ($credito === null || ! SafCredito::query()->whereKey($credito->id)->exists()) {
            $listaId = DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Lista MEV '.uniqid(), 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
            $cliente = Cliente::query()->create(['numero_cliente' => (string) random_int(10000, 99999), 'nombre' => 'Cliente MEV', 'lista_actual_id' => $listaId, 'vendedor_id' => $this->ventas->id]);
            $cuenta = SafCuenta::query()->create(['cliente_id' => $cliente->id, 'moneda' => 'MXN']);
            $credito = SafCredito::query()->create([
                'folio' => 'SAF-MEV-'.uniqid(), 'saf_cuenta_id' => $cuenta->id, 'cliente_id' => $cliente->id,
                'monto_original' => 500, 'monto_aplicado' => 0, 'monto_reservado' => 0, 'monto_disponible' => 500,
                'fecha_generacion' => now(), 'fecha_vencimiento' => now()->addYear(),
            ]);
        }
        $pedido->safAplicaciones()->create(['saf_credito_id' => $credito->id, 'monto' => $monto, 'estado' => $estado]);
    }

    public function test_lectura_consulta_nacional_incluye_multienvio_del_propio_pedido(): void
    {
        $m = $this->confirmadoConFases(['PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO']);
        $this->actingAs($this->ventas);

        $lectura = LecturaConsultaNacional::de($m[2]);
        $this->assertSame(2, $lectura['multienvio']['orden']);
        $this->assertSame(3, $lectura['multienvio']['n_total']);
        $this->assertCount(3, $lectura['multienvio']['hermanos']);

        $sin = LecturaConsultaNacional::de($this->pedidoNacional());
        $this->assertNull($sin['multienvio']);
    }

    public function test_listado_ventas_lleva_multienvio_en_los_miembros(): void
    {
        $m = $this->confirmadoConFases(['PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO', 'PESAJE_RESPONDIDO']);
        $suelto = $this->pedidoNacional();

        $r = $this->actingAs($this->ventas)->getJson('/control-pedidos/listado')->assertOk();
        $filas = collect($r->json('pedidos.data'))->keyBy('id');

        foreach ($m as $orden => $miembro) {
            $this->assertSame($orden, $filas[$miembro->id]['multienvio']['orden']);
            $this->assertSame(3, $filas[$miembro->id]['multienvio']['n_total']);
            $this->assertCount(3, $filas[$miembro->id]['multienvio']['hermanos']);
        }
        $this->assertArrayNotHasKey('multienvio', $filas[$suelto->id]);
    }
}
