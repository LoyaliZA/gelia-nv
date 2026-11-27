<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Models\Departamento;
use App\Models\Sucursal;
use App\Services\ControlPedidos\Nacional\Multienvio\ConfirmarMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\GuardarBorradorMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\RegistrarDecisionMultienvio;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\ControlPedidos\Concerns\EscenarioMultienvioNacional;
use Tests\TestCase;

/** Capa HTTP del Multienvío nacional: lectura, decisión, borrador y confirmación. */
class MultienvioNacionalHttpTest extends TestCase
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
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'MEV-PR', 'nombre_visual' => 'Pesaje respondido', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }

    private function url(PedidoBma $p, string $sufijo = ''): string
    {
        return '/control-pedidos/'.$p->id.'/multienvio'.$sufijo;
    }

    private function envios(array $cajas, array $montos = [600, 500, 400], array $folios = ['F-A', 'F-B', 'F-C']): array
    {
        $envios = [];
        foreach ($cajas as $i => $caja) {
            $envios[] = ['uuid_operativo' => $caja->uuid_operativo, 'folio_remision' => $folios[$i], 'total_mercancia' => $montos[$i], 'contenido' => 'Caja '.($i + 1)];
        }

        return $envios;
    }

    public function test_lectura_devuelve_estado_y_miembros(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $this->confirmar($grupo);

        $r = $this->actingAs($this->ventas)->getJson($this->url($origen))->assertOk();

        $r->assertJsonPath('evaluacion.estado', 'activo')
            ->assertJsonPath('grupo.estado', 'activo')
            ->assertJsonPath('grupo.decision', 'activar')
            ->assertJsonPath('grupo.pedido_origen_id', $origen->id)
            ->assertJsonCount(3, 'envios')
            ->assertJsonPath('envios.0.orden', 1)
            ->assertJsonPath('envios.0.folio_remision', 'F-A')
            ->assertJsonPath('envios.0.estado_pago', 'sin_pago')
            ->assertJsonPath('parcial', false)
            ->assertJsonPath('totales.mercancia', 1500)
            ->assertJsonPath('progreso.pagos_validados', '0/3');
        $this->assertNotEmpty($r->json('envios.1.caja_uuid'));

        // Desde cualquier miembro se obtiene el mismo grupo.
        $miembro = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->where('multienvio_orden', 3)->firstOrFail();
        $this->actingAs($this->ventas)->getJson($this->url($miembro))->assertOk()
            ->assertJsonPath('grupo.id', $grupo->id)->assertJsonCount(3, 'envios');
    }

    public function test_estado_de_pago_derivado_y_totales_pagados(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $this->confirmar($grupo);
        $m2 = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->where('multienvio_orden', 2)->firstOrFail();
        $m3 = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->where('multienvio_orden', 3)->firstOrFail();
        PedidoBmaPago::query()->forceCreate(['pedido_bma_id' => $m2->id, 'numero_exhibicion' => 1, 'monto' => 200, 'forma_pago' => 'transferencia']);
        PedidoBmaPago::query()->forceCreate(['pedido_bma_id' => $m3->id, 'numero_exhibicion' => 1, 'monto' => 400, 'forma_pago' => 'transferencia']);
        $m3->forceFill(['pago_validado_at' => now()])->save();

        $r = $this->actingAs($this->ventas)->getJson($this->url($origen))->assertOk();

        $r->assertJsonPath('envios.1.estado_pago', 'capturado')
            ->assertJsonPath('envios.2.estado_pago', 'validado')
            ->assertJsonPath('totales.pagado', 600)
            ->assertJsonPath('progreso.pagos_validados', '1/3');
    }

    public function test_lectura_sin_n_mas_uno(): void
    {
        $consultas = [];
        foreach ([2, 4] as $n) {
            $pedido = $this->pedidoNacional();
            $cajas = [];
            for ($i = 0; $i < $n; $i++) {
                $cajas[] = $this->caja($pedido, 10 + $i);
            }
            $pedido->forceFill(['numero_cajas' => $n, 'total_mercancia' => 1200, 'total_a_cobrar' => 1200])->save();
            $pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'pedidos_bma/soporte.pdf', 'nombre_original' => 'soporte.pdf', 'activo' => true]);
            $grupo = app(RegistrarDecisionMultienvio::class)->ejecutar($pedido, $this->ventas->id, 'activar');
            $montos = $n === 2 ? [700, 500] : [300, 300, 300, 300];
            app(GuardarBorradorMultienvio::class)->ejecutar($grupo, $this->ventas->id, $this->envios($cajas, $montos, ["Q$n-A", "Q$n-B", "Q$n-C", "Q$n-D"]));
            $this->assertSame('activo', app(ConfirmarMultienvio::class)->ejecutar($grupo->fresh(), $this->ventas->id, $grupo->fresh()->revision_consulta)->estado);

            $this->actingAs($this->ventas)->getJson($this->url($pedido))->assertOk(); // calienta permisos y caches
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->ventas)->getJson($this->url($pedido))->assertOk()->assertJsonCount($n, 'envios');
            $consultas[$n] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        $this->assertSame($consultas[2], $consultas[4], 'Las consultas dependen de N: '.json_encode($consultas));
    }

    public function test_vendedor_propietario_decide_guarda_borrador_y_confirma(): void
    {
        $pedido = $this->pedidoNacional();
        $cajas = [$this->caja($pedido, 10), $this->caja($pedido, 12), $this->caja($pedido, 14)];
        $pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => 'pedidos_bma/soporte.pdf', 'nombre_original' => 'soporte.pdf', 'activo' => true]);
        $revision = $this->actingAs($this->ventas)->getJson($this->url($pedido))->assertOk()
            ->assertJsonPath('evaluacion.elegible', true)->assertJsonPath('grupo', null)->json('evaluacion.revision');

        $this->postJson($this->url($pedido, '/decision'), ['decision' => 'activar', 'revision' => $revision])
            ->assertOk()->assertJsonPath('grupo.estado', 'configurando')->assertJsonPath('grupo.decision', 'activar');

        $this->putJson($this->url($pedido, '/borrador'), ['envios' => $this->envios($cajas)])
            ->assertOk()->assertJsonPath('grupo.borrador.0.folio_remision', 'F-A');

        $this->postJson($this->url($pedido, '/confirmar'), ['revision' => $revision])
            ->assertOk()->assertJsonPath('grupo.estado', 'activo')->assertJsonCount(3, 'envios');
    }

    public function test_decision_sin_seguro(): void
    {
        $pedido = $this->pedidoNacional();
        $this->caja($pedido, 10);
        $this->caja($pedido, 12);

        $revision = app(\App\Services\ControlPedidos\Nacional\Multienvio\EvaluarMultienvioNacional::class)->ejecutar($pedido)['revision'];

        $this->actingAs($this->ventas)->postJson($this->url($pedido, '/decision'), ['decision' => 'sin_seguro', 'revision' => $revision])
            ->assertOk()->assertJsonPath('grupo.estado', 'estandar_sin_seguro')->assertJsonPath('grupo.decision', 'sin_seguro');
        $this->postJson($this->url($pedido, '/decision'), ['decision' => 'otra', 'revision' => $revision])->assertStatus(422)->assertJsonValidationErrors('decision');
    }

    public function test_decision_y_confirmar_exigen_revision(): void
    {
        [$origen] = $this->escenarioConBorrador();

        $this->actingAs($this->ventas)->postJson($this->url($origen, '/decision'), ['decision' => 'sin_seguro'])
            ->assertStatus(422)->assertJsonValidationErrors('revision');
        $this->postJson($this->url($origen, '/confirmar'))
            ->assertStatus(422)->assertJsonValidationErrors('revision');
    }

    public function test_otro_vendedor_recibe_403_en_cada_ruta(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        $otro = User::factory()->create();
        $otro->givePermissionTo('control_pedidos.crear');
        $this->actingAs($otro);

        $this->getJson($this->url($origen))->assertForbidden();
        $this->postJson($this->url($origen, '/decision'), ['decision' => 'sin_seguro'])->assertForbidden();
        $this->putJson($this->url($origen, '/borrador'), ['envios' => $this->envios($cajas)])->assertForbidden();
        $this->postJson($this->url($origen, '/confirmar'))->assertForbidden();
        $this->assertSame('configurando', $grupo->fresh()->estado);
    }

    public function test_cedis_ve_solo_lo_que_su_alcance_permite_y_no_muta(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $this->confirmar($grupo);
        $visible = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->where('multienvio_orden', 2)->firstOrFail();
        $enCedis = CatalogoEstatusPedido::query()->create([
            'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS, 'codigo_interno' => 'MEV-CEDIS', 'nombre_visual' => 'En CEDIS',
            'color_hex' => '#111111', 'orden' => 2, 'activo' => true,
        ]);
        $visible->forceFill(['catalogo_estatus_pedido_id' => $enCedis->id])->save();

        $cedis = User::factory()->create();
        $cedis->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis', 'web'));
        $cedis->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web'));
        $this->actingAs($cedis);

        $r = $this->getJson($this->url($visible))->assertOk();
        $r->assertJsonCount(1, 'envios')
            ->assertJsonPath('envios.0.id', $visible->id)
            ->assertJsonPath('parcial', true)
            ->assertJsonPath('totales.mercancia', 500)
            ->assertJsonPath('progreso.pagos_validados', '0/1')
            ->assertJsonPath('grupo.borrador', null)
            ->assertJsonPath('grupo.pedido_origen_id', null)
            ->assertJsonPath('grupo.uuid', null);

        // El grupo no concede permiso: el origen no está en su alcance.
        $this->getJson($this->url($origen))->assertForbidden();
        $this->postJson($this->url($visible, '/confirmar'))->assertForbidden();
        $this->postJson($this->url($visible, '/decision'), ['decision' => 'sin_seguro'])->assertForbidden();
    }

    public function test_confirmar_dos_veces_devuelve_200_y_un_solo_grupo(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $this->actingAs($this->ventas);

        $this->postJson($this->url($origen, '/confirmar'), ['revision' => $grupo->revision_consulta])->assertOk();
        $this->postJson($this->url($origen, '/confirmar'), ['revision' => $grupo->revision_consulta])
            ->assertOk()->assertJsonPath('grupo.estado', 'activo')->assertJsonCount(3, 'envios');

        $this->assertSame(1, MultienvioNacional::query()->count());
        $this->assertSame(3, PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->count());
    }

    public function test_error_de_suma_devuelve_422_con_campo(): void
    {
        [$origen, $grupo, $cajas] = $this->escenario();
        $this->actingAs($this->ventas)->putJson($this->url($origen, '/borrador'), ['envios' => $this->envios($cajas, [600, 500, 399])])->assertOk();

        $this->postJson($this->url($origen, '/confirmar'), ['revision' => $grupo->revision_consulta])
            ->assertStatus(422)->assertJsonValidationErrors('total_mercancia');
        $this->assertSame('configurando', $grupo->fresh()->estado);
    }

    public function test_folio_repetido_devuelve_422_en_folio_remision(): void
    {
        [$origen, , $cajas] = $this->escenario();

        $this->actingAs($this->ventas)->putJson($this->url($origen, '/borrador'), ['envios' => $this->envios($cajas, [600, 500, 400], ['F-A', 'F-A', 'F-C'])])
            ->assertStatus(422)->assertJsonValidationErrors('folio_remision');
    }

    public function test_revision_obsoleta_devuelve_422_en_revision(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();

        $r = $this->actingAs($this->ventas)->postJson($this->url($origen, '/confirmar'), ['revision' => 'huella-vieja'])
            ->assertStatus(422)->assertJsonValidationErrors('revision');
        $this->assertStringContainsString('vuelve a revisarla', $r->json('errors.revision.0'));
        $this->assertSame('configurando', $grupo->fresh()->estado);
    }

    public function test_entrada_mal_formada_devuelve_422_en_envios(): void
    {
        [$origen, , $cajas] = $this->escenario();

        $this->actingAs($this->ventas)->putJson($this->url($origen, '/borrador'), ['envios' => [['uuid_operativo' => $cajas[0]->uuid_operativo, 'total_mercancia' => -5]]])
            ->assertStatus(422)->assertJsonValidationErrors('envios.0.total_mercancia');
        $this->putJson($this->url($origen, '/borrador'), ['envios' => [
            ['uuid_operativo' => $cajas[0]->uuid_operativo, 'total_mercancia' => 5],
            ['uuid_operativo' => $cajas[0]->uuid_operativo, 'total_mercancia' => 5],
        ]])->assertStatus(422)->assertJsonValidationErrors('envios');
        $this->putJson($this->url($origen, '/borrador'), ['envios' => [['uuid_operativo' => 'ajena', 'total_mercancia' => 5]]])
            ->assertStatus(422)->assertJsonValidationErrors('multienvio');
        $this->putJson($this->url($origen, '/borrador'), [])->assertStatus(422)->assertJsonValidationErrors('envios');
    }

    public function test_regularizacion_pendiente_responde_200_con_la_proyeccion(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        PedidoBmaPago::query()->forceCreate(['pedido_bma_id' => $origen->id, 'numero_exhibicion' => 1, 'monto' => 100, 'forma_pago' => 'transferencia']);

        $this->actingAs($this->ventas)->postJson($this->url($origen, '/confirmar'), ['revision' => $grupo->revision_consulta])
            ->assertOk()
            ->assertJsonPath('grupo.estado', 'regularizacion_pendiente')
            ->assertJsonPath('evaluacion.estado', 'regularizacion_pendiente');
    }

    public function test_sin_permiso_de_crear_no_muta(): void
    {
        [$origen] = $this->escenarioConBorrador();
        $sin = User::factory()->create();

        $this->actingAs($sin)->postJson($this->url($origen, '/confirmar'))->assertForbidden();
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
        [$origen, $grupo] = $this->escenarioConBorrador();
        $this->confirmar($grupo);
        $miembros = [];
        foreach (PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->orderBy('multienvio_orden')->get() as $m) {
            $m->forceFill(['catalogo_estatus_pedido_id' => $this->fase($fases[$m->multienvio_orden - 1])->id])->save();
            $miembros[$m->multienvio_orden] = $m->fresh();
        }

        return $miembros;
    }

    private function usuario(array $permisos, ?int $departamentoId = null): User
    {
        $u = User::factory()->create(['departamento_id' => $departamentoId]);
        foreach ($permisos as $permiso) {
            $u->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    private function assertNoMuta(PedidoBma $p): void
    {
        $this->postJson($this->url($p, '/decision'), ['decision' => 'sin_seguro'])->assertForbidden();
        $this->putJson($this->url($p, '/borrador'), ['envios' => []])->assertForbidden();
        $this->postJson($this->url($p, '/confirmar'))->assertForbidden();
    }

    public function test_quien_ve_el_origen_pero_no_todos_los_miembros_no_recibe_datos_de_los_ocultos(): void
    {
        $m = $this->confirmadoConFases(['EN_CEDIS', 'EN_CEDIS', 'PESAJE_RESPONDIDO']);
        $oculto = PedidoBma::query()->findOrFail($m[3]->id);
        $uuidOculto = $oculto->cajas()->first()->uuid_operativo;
        $cedis = $this->usuario(['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos']);

        $r = $this->actingAs($cedis)->getJson($this->url($m[1]))->assertOk();
        $r->assertJsonCount(2, 'envios')->assertJsonPath('parcial', true)->assertJsonPath('grupo.borrador', null);
        $cuerpo = $r->getContent();
        foreach (['F-C', $uuidOculto, $oculto->folio, 'Caja 3'] as $dato) {
            $this->assertStringNotContainsString($dato, $cuerpo, "Se filtró un dato del envío oculto: $dato");
        }
    }

    public function test_el_borrador_solo_lo_ve_la_propietaria_mientras_se_configura(): void
    {
        [$origen] = $this->escenarioConBorrador();
        $origen->forceFill(['catalogo_estatus_pedido_id' => $this->fase('EN_CEDIS')->id])->save();
        $cedis = $this->usuario(['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos']);

        $this->actingAs($this->ventas)->getJson($this->url($origen))->assertOk()->assertJsonPath('grupo.borrador.0.folio_remision', 'F-A');
        $r = $this->actingAs($cedis)->getJson($this->url($origen))->assertOk()->assertJsonPath('grupo.borrador', null);
        $this->assertStringNotContainsString('F-B', $r->getContent());
    }

    public function test_regularizacion_no_se_muestra_a_quien_no_es_propietaria(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        PedidoBmaPago::query()->forceCreate(['pedido_bma_id' => $origen->id, 'numero_exhibicion' => 1, 'monto' => 100, 'forma_pago' => 'transferencia']);
        $this->actingAs($this->ventas)->postJson($this->url($origen, '/confirmar'), ['revision' => $grupo->revision_consulta])->assertOk();
        $origen->forceFill(['catalogo_estatus_pedido_id' => $this->fase('EN_CEDIS')->id])->save();
        $cedis = $this->usuario(['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos']);

        $this->actingAs($this->ventas)->getJson($this->url($origen))->assertOk()->assertJsonPath('grupo.regularizacion', fn ($v) => $v !== null);
        $this->actingAs($cedis)->getJson($this->url($origen))->assertOk()->assertJsonPath('grupo.regularizacion', null);
    }

    /** Con `auditar`, el listado BMA ya incluye a los usuarios de su departamento (idsVendedoresVisibles): ve todos los envíos del departamento, ninguno fuera de él. */
    public function test_auxiliar_ve_los_envios_de_su_departamento_y_no_los_de_otro_y_no_muta(): void
    {
        $depto = Departamento::query()->create(['nombre' => 'Ventas MEV', 'activo' => true]);
        $this->ventas->forceFill(['departamento_id' => $depto->id])->save();
        $m = $this->confirmadoConFases(['EN_CEDIS', 'PENDIENTE_AUXILIAR', 'PESAJE_RESPONDIDO']);
        $auxiliar = $this->usuario(['control_pedidos.auditar'], $depto->id);

        $this->actingAs($auxiliar)->getJson($this->url($m[1]))->assertOk()
            ->assertJsonCount(3, 'envios')->assertJsonPath('parcial', false);
        $this->assertNoMuta($m[1]);

        $otroDepto = Departamento::query()->create(['nombre' => 'Otro MEV', 'activo' => true]);
        $ajeno = $this->usuario(['control_pedidos.auditar'], $otroDepto->id);
        $this->actingAs($ajeno)->getJson($this->url($m[1]))->assertForbidden();
    }

    public function test_delegado_ve_solo_envios_en_sus_fases_y_no_muta(): void
    {
        $m = $this->confirmadoConFases(['EN_CEDIS', 'PENDIENTE_DE_GUIA', 'PESAJE_RESPONDIDO']);
        $delegado = $this->usuario(['control_pedidos.delegado']);

        $this->actingAs($delegado)->getJson($this->url($m[2]))->assertOk()
            ->assertJsonCount(2, 'envios')->assertJsonPath('parcial', true);
        $this->getJson($this->url($m[3]))->assertForbidden();
        $this->assertNoMuta($m[2]);
    }

    public function test_cedis_con_alcance_por_departamento(): void
    {
        $depto = Departamento::query()->create(['nombre' => 'Ventas CEDIS MEV', 'activo' => true]);
        $this->ventas->forceFill(['departamento_id' => $depto->id])->save();
        $m = $this->confirmadoConFases(['EN_CEDIS', 'PENDIENTE_DE_ENVIO', 'PESAJE_RESPONDIDO']);
        $propio = $this->usuario(['control_pedidos.cedis'], $depto->id);
        $otroDepto = Departamento::query()->create(['nombre' => 'Otro CEDIS MEV', 'activo' => true]);
        $ajeno = $this->usuario(['control_pedidos.cedis'], $otroDepto->id);

        $this->actingAs($propio)->getJson($this->url($m[1]))->assertOk()->assertJsonCount(2, 'envios')->assertJsonPath('parcial', true);
        $this->getJson($this->url($m[3]))->assertForbidden();
        $this->assertNoMuta($m[1]);

        $this->actingAs($ajeno)->getJson($this->url($m[1]))->assertForbidden();
        $this->assertNoMuta($m[1]);
    }

    public function test_miembros_no_nacionales_con_la_misma_firma_se_evaluan_uno_a_uno(): void
    {
        $sucursalA = Sucursal::factory()->create(['activo' => true]);
        $sucursalB = Sucursal::factory()->create(['activo' => true]);
        $json = ['modalidad_entrega' => 'tienda', 'cobertura_tienda' => ['tipo' => 'transferencia']];
        $a = $this->pedidoNacional(['solicitud_preparacion_json' => $json, 'sucursal_destino_id' => $sucursalA->id]);
        $b = $this->pedidoNacional(['solicitud_preparacion_json' => $json, 'sucursal_destino_id' => $sucursalB->id]);
        $grupo = MultienvioNacional::query()->forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'pedido_origen_id' => $a->id, 'revision_consulta' => 'x', 'estado' => 'activo',
        ]);
        $a->forceFill(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 1])->save();
        $b->forceFill(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 2])->save();

        $tiendaA = $this->usuario(['control_pedidos.tienda.ver']);
        $tiendaA->concederAccesoSucursal($sucursalA, true);

        $this->actingAs($tiendaA)->getJson($this->url($a))->assertOk()
            ->assertJsonCount(1, 'envios')->assertJsonPath('envios.0.id', $a->id)->assertJsonPath('parcial', true);
        $this->getJson($this->url($b))->assertForbidden();
    }

    public function test_espejo_movil_existe_y_responde_igual(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $this->confirmar($grupo);
        $device = \App\Models\MobileDevice::query()->create(['user_id' => $this->ventas->id, 'device_uuid' => (string) \Illuminate\Support\Str::uuid(), 'last_seen_at' => now()]);
        $plano = $this->ventas->createToken('mobile:'.$device->device_uuid, ['mobile'])->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($plano)->getJson('/api/v1/mobile/control-pedidos/'.$origen->id.'/multienvio')
            ->assertOk()->assertJsonPath('grupo.estado', 'activo')->assertJsonCount(3, 'envios');
        $this->withToken($plano)->postJson('/api/v1/mobile/control-pedidos/'.$origen->id.'/multienvio/confirmar', ['revision' => $grupo->fresh()->revision_consulta])
            ->assertOk()->assertJsonPath('grupo.estado', 'activo');
    }
}
