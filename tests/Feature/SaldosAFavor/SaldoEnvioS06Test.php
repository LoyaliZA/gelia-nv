<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\User;
use App\Services\SaldosAFavor\ConciliarOrigenCreditoSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\GenerarSaldoEnvioS06Service;
use App\Services\SaldosAFavor\ReservarSaldoSafService;
use App\Support\SaldosAFavor\PoliticaUsoCreditoSaf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SaldoEnvioS06Test extends TestCase
{
    use RefreshDatabase;

    private User $ventas;

    private User $admin;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->ventas = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(Permission::findOrCreate('saldos_favor.autorizar_envio', 'web'));
        $lista = CatalogoListaDescuento::firstOrCreate(['nombre' => 'PUBLICO GENERAL'], ['monto_requerido' => 0, 'activo' => true]);
        $this->cliente = Cliente::create(['numero_cliente' => 'S06-1', 'nombre' => 'Cliente S06', 'lista_actual_id' => $lista->id, 'monto_venta_actual' => 0]);
        foreach ([
            ['pago_de_mas', 'diferencias_pago', false], ['sobrante_envio', 'ajustes_envio', false], ['ajuste_seguro', 'ajustes_envio', false],
            ['envio_no_utilizado', 'ajustes_envio', false], ['ajuste_admin', 'sistema', true],
        ] as $i => [$codigo, $categoria, $detalle]) {
            SafMotivo::firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'categoria' => $categoria, 'requiere_detalle' => $detalle, 'activo' => true, 'orden' => $i]);
        }
    }

    private function credito(string $motivo, float $monto, array $extra = []): SafCredito
    {
        return app(GenerarCreditoSafService::class)->handle(array_merge([
            'cliente_id' => $this->cliente->id, 'monto' => $monto, 'saf_motivo_id' => SafMotivo::where('codigo', $motivo)->value('id'),
            'generado_por_id' => $this->ventas->id, 'canal_origen' => 'bellaroma', 'omitir_monto_minimo' => true,
        ], $extra));
    }

    private function archivo(): array
    {
        return [UploadedFile::fake()->create('evidencia.pdf', 10, 'application/pdf')];
    }

    private function conciliado(string $motivo, float $monto, string $uso = 'solo_transporte'): SafCredito
    {
        $c = $this->credito($motivo, $monto);
        $conciliador = User::factory()->create();
        $conciliador->givePermissionTo('saldos_favor.autorizar_envio');

        return app(ConciliarOrigenCreditoSafService::class)->ejecutar($c, $conciliador, $uso, 'Comprobado con la guía y el comprobante de transporte', $this->archivo());
    }

    private function pedidoCambioAplicado(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'S06-ENV'], ['nombre_visual' => 'Enviado', 'color_hex' => '#94a3b8', 'fase_ciclo' => 'ENVIADO', 'orden' => 9, 'activo' => true]);

        return PedidoBma::create(array_merge([
            'folio' => 'PED-S06-'.uniqid(), 'vendedor_id' => $this->ventas->id, 'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id, 'total_mercancia' => 1000, 'costo_envio' => 80, 'saldo_a_favor' => 0,
            'total_a_cobrar' => 1080, 'fecha' => now()->toDateString(), 'pago_validado_at' => now(), 'pago_validado_por_id' => $this->ventas->id,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal', 'recorrido_fisico_municipal' => true, 'cambio_a_tienda' => ['estado' => 'aplicado', 'solicitado_por_id' => $this->ventas->id]],
        ], $extra));
    }

    public function test_un_credito_de_motivo_de_envio_nace_pendiente_y_los_demas_generales(): void
    {
        $envio = $this->credito('sobrante_envio', 40);
        $this->assertSame('pendiente_conciliacion', $envio->uso_permitido);
        $this->assertSame('no_determinado', $envio->origen_economico);
        $this->assertSame('regla_de_generacion', $envio->clasificacion_fuente);
        $this->assertNull($envio->clasificado_at);

        $seguro = $this->credito('ajuste_seguro', 15);
        $this->assertSame('pendiente_conciliacion', $seguro->uso_permitido, 'El seguro no se clasifica como transporte por su motivo');

        $general = $this->credito('pago_de_mas', 100);
        $this->assertSame('general', $general->uso_permitido);

        $this->expectException(\InvalidArgumentException::class);
        $this->credito('pago_de_mas', 10, ['clasificacion' => ['uso' => 'inventado', 'origen' => 'mercancia', 'fuente' => 'x']]);
    }

    public function test_un_valor_de_clasificacion_invalido_no_se_guarda(): void
    {
        $credito = $this->credito('pago_de_mas', 10);
        $this->expectException(\InvalidArgumentException::class);
        $credito->update(['uso_permitido' => 'otro']);
    }

    public function test_reserva_excluye_pendientes_y_solo_transporte_salvo_hasta_el_tope(): void
    {
        $this->credito('pago_de_mas', 100);
        $this->credito('sobrante_envio', 40); // pendiente
        $transporte = $this->conciliado('envio_no_utilizado', 50);
        $reservar = app(ReservarSaldoSafService::class);

        // Mercancía: solo el general (100). Pedir 120 falla aunque haya 190 en total.
        try {
            $reservar->handle($this->cliente->id, 120, $this->ventas->id);
            $this->fail('Solo el saldo general paga mercancía.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('insuficiente', $e->getMessage());
        }

        // Con tope de transporte 30: 30 del saldo de envío y 90 del general; nunca el pendiente.
        $r = $reservar->handle($this->cliente->id, 120, $this->ventas->id, null, ['tope_transporte' => 30]);
        $porCredito = collect($r)->mapWithKeys(fn ($i) => [$i['credito']->id => $i['monto']]);
        $this->assertSame(30.0, (float) $porCredito[$transporte->id]);
        $this->assertSame(90.0, (float) $porCredito->except($transporte->id)->sum());
        $this->assertSame('pendiente_conciliacion', SafCredito::where('folio', '!=', null)->where('uso_permitido', 'pendiente_conciliacion')->first()->uso_permitido);
        $this->assertEquals(40, SafCredito::where('uso_permitido', 'pendiente_conciliacion')->first()->monto_disponible, 'El pendiente no se tocó');
    }

    public function test_el_pedido_aplica_saldo_de_envio_solo_hasta_su_transporte(): void
    {
        $transporte = $this->conciliado('envio_no_utilizado', 200);
        $pedido = $this->pedidoCambioAplicado(['solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal'], 'pago_validado_at' => null]);
        $pedido->update(['costo_envio' => 80]);

        // Sin saldo general: pedir más que el tope no se cubre solo con el saldo de transporte.
        try {
            app(\App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService::class)->reservarParaPedido($pedido->fresh(), [['monto' => 200]], $this->ventas->id);
            $this->fail('El saldo de envío no cubre más que el transporte del pedido.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('insuficiente', $e->getMessage());
        }
        $this->assertEquals(200, $transporte->fresh()->monto_disponible);

        // Con saldo general, el de envío cubre solo el transporte (80) y el resto sale del general.
        $this->credito('pago_de_mas', 100);
        app(\App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService::class)->reservarParaPedido($pedido->fresh(), [['monto' => 150]], $this->ventas->id);
        $this->assertEquals(80, $transporte->fresh()->monto_reservado);
        $this->assertEquals(150, $pedido->fresh()->saldo_a_favor);
    }

    public function test_proteccion_de_historicos_solo_rige_cuando_se_activa(): void
    {
        $historico = $this->credito('sobrante_envio', 70);
        // Simula un crédito anterior a la clasificación: sin marcar y sin confirmar.
        $historico->forceFill(['uso_permitido' => 'general', 'origen_economico' => null, 'clasificacion_fuente' => null])->save();
        $this->credito('pago_de_mas', 10);

        $this->assertSame('general', PoliticaUsoCreditoSaf::uso($historico->fresh()), 'Sin activar la protección no cambia su uso');
        ConfiguracionSistema::query()->create(['clave' => PoliticaUsoCreditoSaf::CLAVE_PROTEGER_HISTORICOS, 'valor' => '1', 'tipo' => 'booleano', 'grupo' => 'saldos_favor']);
        $this->assertSame('pendiente_conciliacion', PoliticaUsoCreditoSaf::uso($historico->fresh()));
        $this->assertFalse(PoliticaUsoCreditoSaf::usableParaMercancia($historico->fresh()));
        $this->assertSame('general', PoliticaUsoCreditoSaf::uso(SafCredito::where('monto_original', 10)->first()), 'Otros motivos no se reclasifican');
    }

    public function test_la_conciliacion_exige_administracion_evidencia_y_otra_persona_y_no_toca_montos(): void
    {
        $credito = $this->credito('sobrante_envio', 60);
        $servicio = app(ConciliarOrigenCreditoSafService::class);
        $otroAdmin = User::factory()->create();
        $otroAdmin->givePermissionTo('saldos_favor.autorizar_envio');

        foreach ([
            [$this->ventas, 'solo_transporte', 'Comprobado con la guía', $this->archivo(), 'permiso'],
            [$otroAdmin, 'mixto', 'Comprobado con la guía', $this->archivo(), 'uso'],
            [$otroAdmin, 'solo_transporte', 'corto', $this->archivo(), 'motivo'],
            [$otroAdmin, 'solo_transporte', 'Comprobado con la guía y recibo', [], 'evidencias'],
        ] as [$actor, $uso, $motivo, $archivos, $clave]) {
            try {
                $servicio->ejecutar($credito->fresh(), $actor, $uso, $motivo, $archivos);
                $this->fail("Debe rechazar por {$clave}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($clave, $e->errors());
            }
        }
        $this->assertSame('pendiente_conciliacion', $credito->fresh()->uso_permitido);

        // Quien generó el crédito no concilia su propio origen.
        $generador = User::factory()->create();
        $generador->givePermissionTo('saldos_favor.autorizar_envio');
        $propio = $this->credito('sobrante_envio', 20, ['generado_por_id' => $generador->id]);
        try {
            $servicio->ejecutar($propio, $generador, 'general', 'Comprobado con la remisión original', $this->archivo());
            $this->fail('No concilia su propio saldo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }

        $antes = $credito->fresh();
        $resuelto = $servicio->ejecutar($credito->fresh(), $otroAdmin, 'solo_transporte', 'Comprobado con la guía y el recibo', $this->archivo());
        $this->assertSame('solo_transporte', $resuelto->uso_permitido);
        $this->assertSame('transporte_confirmado', $resuelto->origen_economico);
        $this->assertSame($otroAdmin->id, (int) $resuelto->clasificado_por_id);
        $this->assertEquals($antes->monto_disponible, $resuelto->monto_disponible);
        $this->assertEquals($antes->monto_reservado, $resuelto->monto_reservado);
        $this->assertEquals($antes->monto_aplicado, $resuelto->monto_aplicado);
        $mov = SafMovimiento::where('saf_credito_id', $credito->id)->where('tipo', SafMovimiento::TIPO_CLASIFICACION)->sole();
        $this->assertEquals(0, $mov->monto);
        $this->assertSame(1, $mov->evidencias()->count());

        try {
            $servicio->ejecutar($resuelto, $otroAdmin, 'general', 'Intento de volver a clasificar', $this->archivo());
            $this->fail('Una vez resuelto no se reclasifica por esta vía.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credito', $e->errors());
        }
    }

    public function test_s06_exige_cambio_verificado_administracion_distinta_y_no_aplica_minimo(): void
    {
        $servicio = app(GenerarSaldoEnvioS06Service::class);
        $sinCambio = $this->pedidoCambioAplicado(['solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal']]);
        foreach ([
            [$sinCambio, $this->admin, 'pedido'],
            [$this->pedidoCambioAplicado(), $this->ventas, 'permiso'],
        ] as [$pedido, $actor, $clave]) {
            try {
                $servicio->ejecutar($pedido, $actor, 9, 'Transporte pagado y no utilizado', $this->archivo());
                $this->fail("Debe rechazar por {$clave}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($clave, $e->errors());
            }
        }

        // La vendedora con permiso tampoco autoriza su propio saldo.
        $this->ventas->givePermissionTo('saldos_favor.autorizar_envio');
        try {
            $servicio->ejecutar($this->pedidoCambioAplicado(), $this->ventas, 80, 'Transporte pagado y no utilizado', $this->archivo());
            $this->fail('Quien registró la venta no autoriza su saldo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }

        // $9 está bajo el mínimo general ($10) y se acepta: S-06 no lo aplica automáticamente.
        $pedido = $this->pedidoCambioAplicado();
        $credito = $servicio->ejecutar($pedido, $this->admin, 9, 'Transporte pagado y no utilizado por el cambio', $this->archivo());
        $this->assertEquals(9, $credito->monto_original);
        $this->assertSame('solo_transporte', $credito->uso_permitido);
        $this->assertSame('transporte_confirmado', $credito->origen_economico);
        $this->assertSame('s06_autorizado', $credito->clasificacion_fuente);
        $this->assertSame($credito->id, $pedido->fresh()->solicitud_preparacion_json['cambio_a_tienda']['saldo_envio']['credito_id']);

        try {
            $servicio->ejecutar($pedido->fresh(), $this->admin, 20, 'Transporte pagado y no utilizado por el cambio', $this->archivo());
            $this->fail('Un cambio genera un solo saldo de envío.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pedido', $e->errors());
        }

        // Nunca paga mercancía.
        $this->expectException(\InvalidArgumentException::class);
        app(ReservarSaldoSafService::class)->handle($this->cliente->id, 5, $this->ventas->id);
    }

    public function test_reporte_de_impacto_solo_lee_y_la_marca_exige_confirmacion(): void
    {
        $historico = $this->credito('sobrante_envio', 70);
        $historico->forceFill(['uso_permitido' => 'general', 'origen_economico' => null, 'clasificacion_fuente' => null])->save();
        $antes = SafCredito::all()->map->only(['id', 'monto_disponible', 'monto_reservado', 'monto_aplicado', 'estado_financiero', 'uso_permitido'])->all();

        $this->artisan('saf:conciliacion-envio')->expectsOutputToContain('no se modificó nada')->assertExitCode(0);
        $this->assertSame($antes, SafCredito::all()->map->only(['id', 'monto_disponible', 'monto_reservado', 'monto_aplicado', 'estado_financiero', 'uso_permitido'])->all());

        $this->artisan('saf:conciliacion-envio', ['--materializar' => true])->assertExitCode(1);
        $this->assertSame('general', $historico->fresh()->uso_permitido);

        $this->artisan('saf:conciliacion-envio', ['--materializar' => true, '--confirmo' => true])->assertExitCode(0);
        $marcado = $historico->fresh();
        $this->assertSame('pendiente_conciliacion', $marcado->uso_permitido);
        $this->assertSame('migracion_conciliacion', $marcado->clasificacion_fuente);
        $this->assertEquals(70, $marcado->monto_disponible);
        $this->assertSame(1, SafMovimiento::where('saf_credito_id', $historico->id)->where('tipo', SafMovimiento::TIPO_CLASIFICACION)->count());

        $this->artisan('saf:conciliacion-envio', ['--activar-proteccion' => true, '--confirmo' => true])->assertExitCode(0);
        Cache::forget('saldos_favor.reglas');
        $this->assertTrue(PoliticaUsoCreditoSaf::protegerHistoricos());
    }

    public function test_el_saldo_de_envio_se_genera_en_la_pantalla_de_saldos_con_prefill_desde_pedidos(): void
    {
        $this->admin->givePermissionTo([Permission::findOrCreate('saldos_favor.generar', 'web'), Permission::findOrCreate('saldos_favor.ver', 'web')]);
        $pedido = $this->pedidoCambioAplicado();

        // Gestión de pedidos lleva al módulo de saldos con el envío precargado.
        $this->actingAs($this->admin)->get(route('saldos_favor.index', ['saldo_envio_pedido' => $pedido->id]))
            ->assertInertia(fn ($page) => $page->where('saldo_envio_prefill.pedido_id', $pedido->id)
                ->where('saldo_envio_prefill.cliente_id', $this->cliente->id)
                ->where('saldo_envio_prefill.motivo_id', SafMotivo::where('codigo', 'envio_no_utilizado')->value('id')));
        // Quien no es Administración no recibe el prefill.
        $this->actingAs($this->ventas)->get(route('saldos_favor.index', ['saldo_envio_pedido' => $pedido->id]))->assertForbidden();

        $datos = [
            'cliente_id' => $this->cliente->id, 'monto' => 9, 'saf_motivo_id' => SafMotivo::where('codigo', 'envio_no_utilizado')->value('id'),
            'detalle_motivo' => 'Transporte pagado y no utilizado por el cambio', 'pedido_bma_id' => $pedido->id, 'es_saldo_envio' => 1,
            'evidencias' => $this->archivo(),
        ];
        $this->actingAs($this->admin)->post(route('saldos_favor.generar'), $datos)->assertRedirect(route('saldos_favor.cuenta', $this->cliente->id));

        $credito = SafCredito::where('pedido_bma_id', $pedido->id)->sole();
        $this->assertSame('solo_transporte', $credito->uso_permitido);
        $this->assertSame('s06_autorizado', $credito->clasificacion_fuente);
        $this->assertEquals(9, $credito->monto_original, 'S-06 no aplica el mínimo general');
        $this->assertSame($credito->id, $pedido->fresh()->solicitud_preparacion_json['cambio_a_tienda']['saldo_envio']['credito_id']);

        // Un segundo intento por la misma pantalla no duplica el saldo de ese cambio.
        $this->actingAs($this->admin)->post(route('saldos_favor.generar'), array_merge($datos, ['evidencias' => $this->archivo()]))->assertSessionHasErrors('pedido');
        $this->assertSame(1, SafCredito::where('pedido_bma_id', $pedido->id)->count());
    }

    public function test_generacion_manual_con_motivo_de_envio_sin_el_indicador_nace_pendiente(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate('saldos_favor.generar', 'web'));
        $this->actingAs($this->admin)->post(route('saldos_favor.generar'), [
            'cliente_id' => $this->cliente->id, 'monto' => 40, 'saf_motivo_id' => SafMotivo::where('codigo', 'sobrante_envio')->value('id'),
            'evidencias' => $this->archivo(),
        ])->assertRedirect();

        $this->assertSame('pendiente_conciliacion', SafCredito::where('monto_original', 40)->sole()->uso_permitido);
    }

    public function test_rutas_exigen_el_permiso_de_administracion(): void
    {
        Storage::fake('public');
        $credito = $this->credito('sobrante_envio', 30);
        $datos = ['uso' => 'solo_transporte', 'motivo' => 'Comprobado con la guía y el recibo', 'evidencias' => $this->archivo()];

        $this->actingAs($this->ventas)->post(route('saldos_favor.conciliar_origen', $credito), $datos, ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('saldos_favor.conciliar_origen', $credito), $datos, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('credito.uso_permitido', 'solo_transporte');
    }
}
