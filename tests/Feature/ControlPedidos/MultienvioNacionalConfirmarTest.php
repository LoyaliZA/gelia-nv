<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\ControlPedidos\CalcularSeguroPedidoService;
use App\Services\ControlPedidos\GenerarFolioPedidoBmaService;
use App\Services\ControlPedidos\Nacional\Multienvio\ConfirmarMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\EvaluarMultienvioNacional;
use App\Services\ControlPedidos\Nacional\Multienvio\GuardarBorradorMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\MultienvioNacionalException;
use App\Services\ControlPedidos\Nacional\Multienvio\RegistrarDecisionMultienvio;
use App\Services\ControlPedidos\Nacional\VistaPreviaCotizacionNacional;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\ControlPedidos\Concerns\EscenarioMultienvioNacional;
use Tests\TestCase;

class MultienvioNacionalConfirmarTest extends TestCase
{
    use EscenarioMultienvioNacional;
    use RefreshDatabase;

    private User $ventas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        Storage::fake('public');
        Storage::disk('public')->put('pedidos_bma/soporte.pdf', 'PDF');
        $this->ventas = User::factory()->create();
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'MEV-PR', 'nombre_visual' => 'Pesaje respondido', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }

    public function test_confirma_tres_envios_y_reparte_cajas_por_uuid(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();

        $r = $this->confirmar($grupo);

        $this->assertSame(MultienvioNacional::ESTADO_ACTIVO, $r->estado);
        $this->assertSame($this->ventas->id, (int) $r->activado_por_id);
        $this->assertNotNull($r->activado_at);

        $miembros = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->orderBy('multienvio_orden')->get();
        $this->assertCount(3, $miembros);
        $this->assertSame([1, 2, 3], $miembros->pluck('multienvio_orden')->map(fn ($v) => (int) $v)->all());
        $this->assertSame($origen->id, $miembros[0]->id);

        foreach ($miembros as $i => $miembro) {
            $activas = $miembro->cajas()->activas()->get();
            $this->assertCount(1, $activas);
            $this->assertSame($cajas[$i]->id, $activas[0]->id);
            $this->assertSame($cajas[$i]->uuid_operativo, $activas[0]->uuid_operativo);
        }
        $this->assertSame(0, (int) PedidoBmaCaja::query()->find($cajas[1]->id)->orden);

        $centavos = $miembros->sum(fn (PedidoBma $p) => (int) round((float) $p->total_mercancia * 100));
        $this->assertSame(150000, $centavos);
        $this->assertSame(['600.00', '500.00', '400.00'], $miembros->pluck('total_mercancia')->all());
        $this->assertSame(['F-A', 'F-B', 'F-C'], $miembros->pluck('folio_remision')->all());

        foreach ($miembros->slice(1) as $miembro) {
            $this->assertMatchesRegularExpression('/^PBMA-\d{4}-\d{5}$/', $miembro->folio);
            $ref = PedidoBmaReferencia::query()->where('pedido_bma_id', $miembro->id)->where('vigente', true)->first();
            $this->assertSame(PedidoBmaReferencia::TIPO_PEDIDO, $ref->tipo);
            $this->assertSame($miembro->folio_remision, $ref->folio);
            $this->assertSame(1, $miembro->historial()->where('accion', AccionesHistorialPedidoBma::MULTIENVIO_CONFIRMADO)->count());
        }
        $this->assertSame(1, $origen->historial()->where('accion', AccionesHistorialPedidoBma::MULTIENVIO_CONFIRMADO)->count());
    }

    public function test_el_origen_queda_como_envio_1_con_sus_valores(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        $folioGelia = $origen->folio;

        $this->confirmar($grupo);
        $o = $origen->fresh();

        $this->assertSame($folioGelia, $o->folio);
        $this->assertSame(1, (int) $o->multienvio_orden);
        $this->assertSame($grupo->id, (int) $o->multienvio_nacional_id);
        $this->assertSame('600.00', $o->total_mercancia);
        $this->assertSame('F-A', $o->folio_remision);
        $this->assertSame(1, $o->numero_cajas);
        $this->assertEquals(10, (float) $o->peso_real_kg);
        $this->assertEquals(10, (float) $o->peso_cobrado_guia_kg);
        $this->assertSame($cajas[0]->catalogo_tipo_caja_id, (int) $o->catalogo_tipo_caja_id);
        $this->assertNull($o->costo_envio);
        $this->assertSame('0.00', $o->costo_seguro);
        $this->assertSame('600.00', $o->total_a_cobrar);
        $this->assertArrayNotHasKey('cotizacion_nacional', $o->solicitud_preparacion_json);
        $this->assertSame(['p-1'], array_column($o->solicitud_preparacion_json['piezas_nacionales'], 'client_uuid'));
        $this->assertTrue($o->aplica_seguro);
        $this->assertSame('F-A', PedidoBmaReferencia::visible($o)['folio']);
    }

    public function test_conserva_la_cotizacion_previa_del_origen_en_el_snapshot_y_en_el_historial(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();

        $r = $this->confirmar($grupo);

        $previo = $r->fresh()->snapshot_origen_json['al_confirmar'];
        $this->assertEquals(1500, $previo['financiero']['total_mercancia']);
        $this->assertEquals(270, $previo['financiero']['costo_envio']);
        $this->assertEquals(120, $previo['financiero']['costo_seguro']);
        $this->assertEquals(1890, $previo['financiero']['total_a_cobrar']);
        $this->assertTrue($previo['financiero']['aplica_seguro']);
        $this->assertEquals(1800, $previo['cotizacion_nacional']['total']);
        $this->assertCount(3, $previo['cajas']);
        $this->assertEquals(90, $previo['cajas'][$cajas[1]->uuid_operativo]['costo_envio']);
        $this->assertEquals(40, $previo['cajas'][$cajas[1]->uuid_operativo]['costo_seguro']);
        $this->assertArrayHasKey('costo_adicional', $previo['cajas'][$cajas[1]->uuid_operativo]);

        $fila = PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $origen->id)
            ->where('accion', AccionesHistorialPedidoBma::MULTIENVIO_CONFIRMADO)->firstOrFail();
        $this->assertEquals(1890, $fila->snapshot_json['financiero_previo']['total_a_cobrar']);
        $this->assertEquals(1800, $fila->snapshot_json['cotizacion_nacional_previa']['total']);
        $this->assertSame($grupo->id, $fila->snapshot_json['multienvio_nacional_id']);
        // Los datos ya conservados del snapshot de la decisión siguen ahí.
        $this->assertArrayHasKey('documentos_ids', $r->fresh()->snapshot_origen_json);
    }

    public function test_no_copia_pagos_ni_remisiones(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();

        $this->confirmar($grupo);

        $nuevos = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->where('id', '!=', $origen->id)->get();
        $this->assertCount(2, $nuevos);
        foreach ($nuevos as $m) {
            $this->assertSame(0, PedidoBmaPago::query()->where('pedido_bma_id', $m->id)->count());
            $this->assertNull($m->numero_remision);
            $this->assertNull($m->pago_validado_at);
            $this->assertNull($m->numero_rastreo);
            $this->assertNull($m->empacado_at);
            $this->assertNull($m->captura_creacion_clave);
            $this->assertSame('0.00', $m->saldo_a_favor);
            $this->assertNull($m->costo_envio);
            $this->assertSame($m->total_mercancia, $m->total_a_cobrar);
            $json = $m->solicitud_preparacion_json;
            $this->assertSame('nacional', $json['modalidad_entrega']);
            $this->assertSame('compartida', $json['captura_nacional']);
            foreach (['cotizacion_nacional', 'ajustes_nacionales'] as $clave) {
                $this->assertArrayNotHasKey($clave, $json);
            }
            $this->assertSame([], $json['piezas_nacionales']);
            $this->assertSame([], $json['piezas_nacionales_vigentes']);
            $this->assertSame([], $json['respuestas_piezas_nacionales']);
            $this->assertSame(0, $m->revisionesProducto()->count());
        }
        $this->assertSame(1, $origen->revisionesProducto()->count());
    }

    public function test_confirmar_dos_veces_no_duplica(): void
    {
        [, $grupo] = $this->escenarioConBorrador();

        $a = $this->confirmar($grupo);
        $total = PedidoBma::query()->count();
        $b = $this->confirmar($grupo->fresh());

        $this->assertSame($a->id, $b->id);
        $this->assertSame(MultienvioNacional::ESTADO_ACTIVO, $b->estado);
        $this->assertSame($total, PedidoBma::query()->count());
        $this->assertSame(3, PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->count());
    }

    public function test_suma_con_un_centavo_de_diferencia_lanza_suma_no_cuadra_y_no_crea_nada(): void
    {
        [$origen, $grupo, $cajas] = $this->escenario();
        $this->borrador($grupo, $cajas, [600, 500, 399.99]);
        $antes = $this->foto($origen);

        try {
            $this->confirmar($grupo->fresh());
            $this->fail('Debió lanzar sumaNoCuadra.');
        } catch (MultienvioNacionalException $e) {
            $this->assertSame(MultienvioNacionalException::sumaNoCuadra(-0.01)->getMessage(), $e->getMessage());
        }

        $this->assertSame($antes, $this->foto($origen));
    }

    public function test_caja_retirada_tras_el_borrador_lanza_revision_obsoleta(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        PedidoBmaCaja::query()->whereKey($cajas[2]->id)->update(['estado_operativo' => PedidoBmaCaja::ESTADO_RETIRADA]);
        $antes = $this->foto($origen);

        $this->expectExceptionObject(MultienvioNacionalException::revisionObsoleta());

        try {
            $this->confirmar($grupo->fresh());
        } finally {
            $this->assertSame($antes, $this->foto($origen));
        }
    }

    public function test_revision_esperada_distinta_lanza_revision_obsoleta(): void
    {
        [, $grupo] = $this->escenarioConBorrador();

        $this->expectExceptionObject(MultienvioNacionalException::revisionObsoleta());

        app(ConfirmarMultienvio::class)->ejecutar($grupo, $this->ventas->id, 'otra-revision');
    }

    public function test_revision_obsoleta_se_resuelve_reactivando_con_la_revision_vigente_y_se_confirma(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        $r1 = $grupo->revision_consulta;
        PedidoBmaCaja::query()->whereKey($cajas[0]->id)->update(['peso_cobrado_kg' => 20]);
        $r2 = app(EvaluarMultienvioNacional::class)->ejecutar($origen->fresh())['revision'];
        $this->assertNotSame($r1, $r2);

        try {
            $this->confirmar($grupo->fresh());
            $this->fail('Debió lanzar revisión obsoleta.');
        } catch (MultienvioNacionalException $e) {
            $this->assertSame(MultienvioNacionalException::revisionObsoleta()->getMessage(), $e->getMessage());
        }

        $refrescado = app(RegistrarDecisionMultienvio::class)->ejecutar($origen->fresh(), $this->ventas->id, 'activar', $r2);

        $this->assertSame($grupo->id, $refrescado->id);
        $this->assertSame(MultienvioNacional::ESTADO_CONFIGURANDO, $refrescado->estado);
        $this->assertSame($r2, $refrescado->revision_consulta);
        $this->assertCount(3, $refrescado->borrador_json);

        $activo = $this->confirmar($refrescado->fresh());

        $this->assertSame(MultienvioNacional::ESTADO_ACTIVO, $activo->estado);
        $this->assertSame(3, PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->count());
    }

    public function test_al_refrescar_se_descartan_del_borrador_las_cajas_que_ya_no_estan_activas(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        PedidoBmaCaja::query()->whereKey($cajas[2]->id)->update(['estado_operativo' => PedidoBmaCaja::ESTADO_RETIRADA]);
        $r2 = app(EvaluarMultienvioNacional::class)->ejecutar($origen->fresh())['revision'];

        $refrescado = app(RegistrarDecisionMultienvio::class)->ejecutar($origen->fresh(), $this->ventas->id, 'activar', $r2);

        $this->assertSame($r2, $refrescado->revision_consulta);
        $this->assertSame(
            [$cajas[0]->uuid_operativo, $cajas[1]->uuid_operativo],
            array_column($refrescado->borrador_json, 'uuid_operativo')
        );
        $this->assertSame('F-A', $refrescado->borrador_json[0]['folio_remision']);
        $this->assertSame('Caja 2', $refrescado->borrador_json[1]['contenido']);
    }

    public function test_reactivar_con_una_revision_esperada_vieja_sigue_siendo_obsoleta(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        $r1 = $grupo->revision_consulta;
        PedidoBmaCaja::query()->whereKey($cajas[0]->id)->update(['peso_cobrado_kg' => 20]);

        try {
            app(RegistrarDecisionMultienvio::class)->ejecutar($origen->fresh(), $this->ventas->id, 'activar', $r1);
            $this->fail('Debió lanzar revisión obsoleta.');
        } catch (MultienvioNacionalException $e) {
            $this->assertSame(MultienvioNacionalException::revisionObsoleta()->getMessage(), $e->getMessage());
        }
        $this->assertSame($r1, $grupo->fresh()->revision_consulta);
    }

    public function test_hace_rollback_si_falla_un_miembro(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $antes = $this->foto($origen);
        $pedidosAntes = PedidoBma::query()->pluck('id')->all();

        $this->app->instance(GenerarFolioPedidoBmaService::class, new class extends GenerarFolioPedidoBmaService
        {
            private int $llamadas = 0;

            public function ejecutar(): string
            {
                if (++$this->llamadas === 2) {
                    throw new RuntimeException('Falla forzada en el 3.er envío.');
                }

                return parent::ejecutar();
            }
        });

        try {
            $this->confirmar($grupo);
            $this->fail('Debió fallar.');
        } catch (RuntimeException $e) {
            $this->assertSame('Falla forzada en el 3.er envío.', $e->getMessage());
        }

        $this->assertSame($antes, $this->foto($origen));
        $this->assertSame($pedidosAntes, PedidoBma::query()->pluck('id')->all());
        $this->assertSame(MultienvioNacional::ESTADO_CONFIGURANDO, $grupo->fresh()->estado);
        $this->assertSame([], Storage::disk('public')->allFiles('pedidos_bma/multienvio'));
        Storage::disk('public')->assertExists('pedidos_bma/soporte.pdf');
    }

    public function test_cada_miembro_queda_listo_para_cotizar(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();

        $this->confirmar($grupo);
        $o = $origen->fresh();

        foreach (PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->get() as $m) {
            $this->assertTrue($m->consultaCerrada());
            $this->assertFalse($m->puedeCerrarConsulta());
            $this->assertTrue($m->esEditablePorVendedora());
            $this->assertTrue(CapturaNacional::es($m));
            $this->assertTrue($m->tienePesajeRespondido());
            $this->assertTrue($m->tieneSoporteSolicitud());
            $this->assertSame($o->catalogo_estatus_pedido_id, $m->catalogo_estatus_pedido_id);
            $this->assertSame($o->estatus_envio, $m->estatus_envio);
            $this->assertEquals($o->pesaje_respondido_at, $m->pesaje_respondido_at);
            $this->assertEquals($o->consulta_cerrada_at, $m->consulta_cerrada_at);
            $this->assertFalse((bool) $m->consulta_actualizacion_pendiente);
            $this->assertTrue($m->aplica_seguro);
            $this->assertSame(1, $m->numero_cajas);
            $this->assertNotNull($m->peso_real_kg);
            $this->assertNotNull($m->catalogo_tipo_caja_id);
            $this->assertSame($o->cliente_id, $m->cliente_id);
            $this->assertSame($o->almacen_id, $m->almacen_id);
            $this->assertSame($o->catalogo_paqueteria_id, $m->catalogo_paqueteria_id);
            $this->assertSame($o->vendedor_id, $m->vendedor_id);
            $this->assertSame(0, $m->historial()->where('accion', AccionesHistorialPedidoBma::RESPUESTA_PESAJE)->count());
            $this->assertSame(1, PedidoBmaDireccion::query()->where('pedido_bma_id', $m->id)->where('es_vigente', true)->count());
        }
    }

    public function test_folio_wizerp_faltante_lanza_error_y_no_materializa(): void
    {
        [$origen, $grupo, $cajas] = $this->escenario();
        $this->borrador($grupo, $cajas, [600, 500, 400], ['F-A', null, 'F-C']);
        $antes = $this->foto($origen);

        try {
            $this->confirmar($grupo->fresh());
            $this->fail('Debió lanzar error.');
        } catch (MultienvioNacionalException $e) {
            $this->assertStringContainsString('folio Wizerp', $e->getMessage());
        }

        $this->assertSame($antes, $this->foto($origen));
    }

    public function test_folio_ocupado_por_otro_pedido_lanza_folio_repetido(): void
    {
        [, $grupo] = $this->escenarioConBorrador();
        $this->pedidoNacional(['folio_remision' => 'F-C']);

        $this->expectExceptionObject(MultienvioNacionalException::folioRepetido('F-C'));

        $this->confirmar($grupo);
    }

    public function test_borrador_incompleto_lanza_no_elegible(): void
    {
        [$origen, $grupo, $cajas] = $this->escenario();
        app(GuardarBorradorMultienvio::class)->ejecutar($grupo, $this->ventas->id, [
            ['uuid_operativo' => $cajas[0]->uuid_operativo, 'folio_remision' => 'F-A', 'total_mercancia' => 1000],
            ['uuid_operativo' => $cajas[1]->uuid_operativo, 'folio_remision' => 'F-B', 'total_mercancia' => 500],
        ]);
        $antes = $this->foto($origen);

        try {
            $this->confirmar($grupo->fresh());
            $this->fail('Debió rechazar el borrador incompleto.');
        } catch (MultienvioNacionalException $e) {
            $this->assertStringContainsString($cajas[2]->uuid_operativo, $e->getMessage());
        }
        $this->assertSame($antes, $this->foto($origen));
    }

    public function test_documentos_viajan_con_su_caja_y_el_soporte_se_duplica(): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        $foto = $origen->documentos()->where('pedido_bma_caja_id', $cajas[1]->id)->firstOrFail();
        $fotoLegada = $origen->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_ENVIO_CAJA)
            ->whereNull('pedido_bma_caja_id')->where('relacion_id', $cajas[2]->id)->firstOrFail();
        $general = $origen->documentos()->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_GENERAL)->firstOrFail();
        $remision = PedidoBmaCajaRemision::query()->where('pedido_bma_caja_id', $cajas[2]->id)->firstOrFail();

        $this->confirmar($grupo);
        $m2 = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->where('multienvio_orden', 2)->firstOrFail();
        $m3 = PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->where('multienvio_orden', 3)->firstOrFail();

        $this->assertSame($m2->id, (int) $foto->fresh()->pedido_bma_id);
        $this->assertSame($cajas[1]->id, (int) $foto->fresh()->pedido_bma_caja_id);
        $this->assertSame($m3->id, (int) $fotoLegada->fresh()->pedido_bma_id);
        $this->assertSame($origen->id, (int) $general->fresh()->pedido_bma_id);

        $rutas = [];
        foreach ([$origen->fresh(), $m2, $m3] as $p) {
            $soporte = $p->documentos()->where('tipo', PedidoBmaDocumento::TIPO_PDF_PEDIDO)->get();
            $this->assertCount(1, $soporte);
            Storage::disk('public')->assertExists($soporte[0]->ruta_archivo);
            $rutas[] = $soporte[0]->ruta_archivo;
        }
        $this->assertSame('pedidos_bma/soporte.pdf', $rutas[0]);
        $this->assertCount(3, array_unique($rutas));
        $this->assertSame('PDF', Storage::disk('public')->get($rutas[2]));

        $r = $remision->fresh();
        $this->assertSame($cajas[2]->id, (int) $r->pedido_bma_caja_id);
        $this->assertNotNull($remision->pedido_bma_id);
        $this->assertSame($remision->pedido_bma_id, $r->pedido_bma_id);
    }

    public function test_hechos_previos_pasan_a_regularizacion_y_no_cambian_nada(): void
    {
        $variantes = [
            'pago validado' => fn (PedidoBma $p, array $c) => $p->update(['pago_validado_at' => now()]),
            'remisión' => fn (PedidoBma $p, array $c) => $p->update(['numero_remision' => 'R-1']),
            'rastreo' => fn (PedidoBma $p, array $c) => $p->update(['numero_rastreo' => 'TRK-1']),
            'empacado' => fn (PedidoBma $p, array $c) => $p->update(['empacado_at' => now()]),
            'caja recolectada' => fn (PedidoBma $p, array $c) => PedidoBmaCaja::query()->whereKey($c[1]->id)
                ->update(['estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA]),
            'saldo a favor' => fn (PedidoBma $p, array $c) => $p->update(['saldo_a_favor' => 1000]),
            'cierre de pago' => fn (PedidoBma $p, array $c) => PedidoBmaCierrePago::query()->forceCreate([
                'pedido_bma_id' => $p->id, 'version' => 1, 'validado_at' => now(), 'validado_por_id' => $this->ventas->id,
                'monto_venta' => 1500, 'total_pedido' => 1500, 'total_a_cobrar' => 1500, 'pagos_validos' => 1500,
                'diferencia' => 0, 'tolerancia_aplicada' => 0, 'estado_cobertura' => 'cubierto',
            ]),
            'cancelación activa' => fn (PedidoBma $p, array $c) => PedidoBmaCancelacionOperativa::query()->forceCreate([
                'pedido_bma_id' => $p->id, 'estado' => PedidoBmaCancelacionOperativa::ESTADO_SOLICITADA, 'motivo' => 'cliente',
            ]),
            'guía PDF' => fn (PedidoBma $p, array $c) => $p->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_GUIA, 'ruta_archivo' => 'pedidos_bma/guia.pdf', 'nombre_original' => 'guia.pdf', 'activo' => true,
            ]),
            'rastreo de caja' => fn (PedidoBma $p, array $c) => PedidoBmaCaja::query()->whereKey($c[2]->id)->update(['numero_rastreo' => 'TRK-CAJA']),
            'pago capturado' => fn (PedidoBma $p, array $c) => PedidoBmaPago::query()->forceCreate([
                'pedido_bma_id' => $p->id, 'numero_exhibicion' => 1, 'monto' => 100, 'forma_pago' => 'transferencia',
            ]),
        ];

        foreach ($variantes as $nombre => $hecho) {
            [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
            $hecho($origen, $cajas);
            $antes = $this->foto($origen);

            $r = $this->confirmar($grupo->fresh());

            $this->assertSame(MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE, $r->estado, $nombre);
            $this->assertNotEmpty($r->regularizacion_json['hechos'], $nombre);
            $despues = $this->foto($origen);
            $antes['grupo'] = array_map(fn (array $g) => $g['id'] === $grupo->id
                ? array_merge($g, ['estado' => MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE]) : $g, $antes['grupo']);
            $this->assertSame($antes, $despues, $nombre);
        }
    }

    public function test_aplica_seguro_sigue_la_cobertura_de_la_paqueteria(): void
    {
        $sinCobertura = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Paquetería Local', 'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
            'requiere_peso' => true, 'activo' => true, 'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
        ]);
        [, $grupo] = $this->escenarioConBorrador(['catalogo_paqueteria_id' => $sinCobertura->id, 'aplica_seguro' => true]);

        $this->confirmar($grupo);

        foreach (PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->get() as $m) {
            $this->assertFalse($m->aplica_seguro);
        }
    }

    public function test_seguro_dhl_por_miembro_usa_el_calculo_existente(): void
    {
        [, $grupo] = $this->escenarioConBorrador();
        $this->confirmar($grupo);
        $dhl = CatalogoPaqueteriaPedido::query()->where('nombre', 'DHL')->firstOrFail();

        foreach (PedidoBma::query()->where('multienvio_nacional_id', $grupo->id)->get() as $m) {
            $caja = $m->cajas()->activas()->firstOrFail();
            $vista = app(VistaPreviaCotizacionNacional::class)->calcular(
                $m, $dhl, [['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => 100]], (bool) $m->aplica_seguro, null
            );
            $esperado = app(CalcularSeguroPedidoService::class)->calcularCosto('DHL', 100, (float) $m->total_mercancia);
            $this->assertSame($esperado, $vista['seguro']);
            $this->assertSame(round(0.02 * (100 + (float) $m->total_mercancia) + 51, 2), $vista['seguro']);
        }
    }

    public function test_usuario_ajeno_no_puede_confirmar(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $ajeno = User::factory()->create();
        $antes = $this->foto($origen);

        try {
            app(ConfirmarMultienvio::class)->ejecutar($grupo, $ajeno->id);
            $this->fail('Debió negar.');
        } catch (AuthorizationException) {
            $this->assertSame($antes, $this->foto($origen));
        }
    }

    public function test_si_no_se_puede_copiar_el_soporte_no_materializa_ni_deja_copias(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        Storage::disk('public')->delete('pedidos_bma/soporte.pdf');
        $antes = $this->foto($origen);

        try {
            $this->confirmar($grupo);
            $this->fail('Debió fallar la copia.');
        } catch (MultienvioNacionalException $e) {
            $this->assertStringContainsString('No se pudo copiar el documento de soporte', $e->getMessage());
            $this->assertSame('multienvio', $e->campo);
        }

        $this->assertSame($antes, $this->foto($origen));
        $this->assertSame([], Storage::disk('public')->allFiles('pedidos_bma/multienvio'));
    }

    public function test_un_fallo_posterior_al_commit_no_borra_las_copias_del_soporte(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        PedidoBma::created(function (PedidoBma $p) {
            if ($p->multienvio_nacional_id !== null) {
                DB::afterCommit(function () {
                    throw new RuntimeException('Falla posterior al commit.');
                });
            }
        });

        try {
            $this->confirmar($grupo);
            $this->fail('Debió fallar tras el commit.');
        } catch (RuntimeException $e) {
            $this->assertSame('Falla posterior al commit.', $e->getMessage());
        }

        $this->assertSame(MultienvioNacional::ESTADO_ACTIVO, $grupo->fresh()->estado);
        $copias = Storage::disk('public')->allFiles('pedidos_bma/multienvio');
        $this->assertNotEmpty($copias);
        $referenciadas = PedidoBmaDocumento::query()->whereIn('ruta_archivo', $copias)->count();
        $this->assertSame(count($copias), $referenciadas);
    }

    public function test_regularizacion_pendiente_se_devuelve_sin_cambios(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $grupo->update(['estado' => MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE]);
        $antes = $this->foto($origen);

        $r = $this->confirmar($grupo->fresh());

        $this->assertSame($grupo->id, $r->id);
        $this->assertSame(MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE, $r->estado);
        $this->assertSame($antes, $this->foto($origen));
    }

    public function test_saldo_a_favor_en_el_origen_va_a_regularizacion_sin_repartir_el_saldo(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador(['saldo_a_favor' => 1000]);
        $antes = $this->foto($origen);

        $r = $this->confirmar($grupo);

        $this->assertSame(MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE, $r->estado);
        $this->assertSame(['cierre_o_saf'], $r->regularizacion_json['hechos']);
        $this->assertSame('1000.00', $origen->fresh()->saldo_a_favor);
        $this->assertSame($antes['pedidos'], $this->foto($origen)['pedidos']);
    }

    public function test_estado_distinto_de_configurando_lanza_no_elegible(): void
    {
        [, $grupo] = $this->escenarioConBorrador();
        $grupo->update(['estado' => MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO]);

        $this->expectException(MultienvioNacionalException::class);
        $this->expectExceptionMessage('no es elegible');

        $this->confirmar($grupo->fresh());
    }
}
