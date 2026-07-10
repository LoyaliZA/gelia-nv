<?php

namespace Tests\Feature\Cobranza;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\CobranzaConfiguracion;
use App\Models\CobranzaFactura;
use App\Models\User;
use App\Notifications\AlertaCargaReporteCobranzaNotification;
use App\Notifications\AlertaLimiteCreditoSuperadoMasivoNotification;
use App\Notifications\AlertaPagoLiquidoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportarReporteCobranzaTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConPermisos(array $permisos): User
    {
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $role = Role::findOrCreate('cobranza_tester', 'web');
        $role->syncPermissions($permisos);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function sincronizarListas(): void
    {
        CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);
    }

    private function csvCobranza(array $filas): UploadedFile
    {
        $header = "Cliente,Consolidado,Por vencer,De 1 a 30 dias,De 31 a 60 dias,De 61 a 90 dias,De 91 a 120 dias,Mas de 120 dias\n";
        $body = implode("\n", $filas) . "\n";

        return UploadedFile::fake()->createWithContent('reporte_cobranza.csv', $header . $body);
    }

    private function csvCxc(array $filas): UploadedFile
    {
        $header = "Cuentas por Cobrar,,,,,,,,,\n";
        $header .= "Cliente,Titulo,Folio,No. Venta,Sucursal,Fecha emisión,Fecha Vencimiento,Importe Original,Saldo Pendiente,Vigencia\n";
        $body = implode("\n", $filas) . "\n";

        return UploadedFile::fake()->createWithContent('reporte_cxc.csv', $header . $body);
    }

    private function filaCxc(
        string $cliente,
        string $folio,
        string $fechaEmision,
        string $fechaVencimiento,
        string $importeOriginal,
        string $saldoPendiente,
        string $vigencia = 'Vigente',
    ): string {
        return sprintf(
            '"%s","","%s","54447 (R)","Matríz","%s","%s","%s","%s","%s"',
            $cliente,
            $folio,
            $fechaEmision,
            $fechaVencimiento,
            $importeOriginal,
            $saldoPendiente,
            $vigencia
        );
    }

    public function test_preview_detecta_credito_nuevo_sin_factura_activa(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        Cliente::create([
            'numero_cliente' => '5001',
            'nombre' => 'Cliente Sin Credito',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
        ]);

        $archivo = $this->csvCobranza([
            '5001 Cliente Sin Credito,1500.00,0,0,0,0,0,0',
        ]);

        $response = $this->actingAs($user)->postJson(route('auto-cobranza.importar.preview'), [
            'archivo' => $archivo,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'creditos_nuevos');
        $response->assertJsonPath('creditos_nuevos.0.clave', '5001');
        $response->assertJsonPath('creditos_nuevos.0.monto', 1500);
    }

    public function test_preview_excluye_cliente_con_factura_impaga(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        $cliente = Cliente::create([
            'numero_cliente' => '5002',
            'nombre' => 'Cliente Con Credito',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(10)->toDateString(),
            'dias_credito' => 30,
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => 'COB-5002-TEST',
            'monto' => 2000,
            'fecha_emision' => now()->subDays(10)->toDateString(),
            'fecha_vencimiento' => now()->addDays(20)->toDateString(),
            'pagada' => false,
        ]);

        $archivo = $this->csvCobranza([
            '5002 Cliente Con Credito,2500.00,0,0,0,0,0,0',
        ]);

        $response = $this->actingAs($user)->postJson(route('auto-cobranza.importar.preview'), [
            'archivo' => $archivo,
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'creditos_nuevos');
    }

    public function test_preview_requiere_permiso_importar_reporte(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver']);

        $archivo = $this->csvCobranza([
            '5003 Cliente,1000.00,0,0,0,0,0,0',
        ]);

        $response = $this->actingAs($user)->postJson(route('auto-cobranza.importar.preview'), [
            'archivo' => $archivo,
        ]);

        $response->assertForbidden();
    }

    public function test_import_con_ajustes_fechas_persiste_fecha_y_vencimiento(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        Cliente::create([
            'numero_cliente' => '6001',
            'nombre' => 'Cliente Nuevo Credito',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'dias_credito' => 30,
        ]);

        $fechaInicio = now()->subDays(15)->toDateString();
        $archivo = $this->csvCobranza([
            '6001 Cliente Nuevo Credito,3000.00,0,0,0,0,0,0',
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
            'ajustes_fechas' => [
                ['clave' => '6001', 'fecha_inicio_credito' => $fechaInicio],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cliente = Cliente::where('numero_cliente', '6001')->first();
        $this->assertNotNull($cliente);
        $this->assertSame($fechaInicio, $cliente->fecha_inicio_credito->toDateString());

        $factura = CobranzaFactura::where('cliente_id', $cliente->id)->where('pagada', false)->first();
        $this->assertNotNull($factura);
        $this->assertSame($fechaInicio, $factura->fecha_emision->toDateString());
        $this->assertSame(
            now()->parse($fechaInicio)->addDays(30)->toDateString(),
            $factura->fecha_vencimiento->toDateString()
        );
    }

    public function test_import_dispara_alerta_carga_a_usuarios_configurados(): void
    {
        Notification::fake();
        $this->sincronizarListas();

        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);
        $destinatario = User::factory()->create();

        CobranzaConfiguracion::create([
            'llave' => 'notified_users_carga',
            'valor' => json_encode([$destinatario->id]),
        ]);

        \Illuminate\Support\Facades\Cache::forever('cobranza_config_users_carga', [$destinatario->id]);

        $archivo = $this->csvCobranza([
            '7001 Cliente Alerta,1200.00,0,0,0,0,0,0',
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        Notification::assertSentTo(
            $destinatario,
            AlertaCargaReporteCobranzaNotification::class
        );
    }

    public function test_import_liquida_automaticamente_saldo_cero(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        $cliente = Cliente::create([
            'numero_cliente' => '8001',
            'nombre' => 'Cliente Pago Auto',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(20)->toDateString(),
            'dias_credito' => 30,
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => 'COB-8001-TEST',
            'monto' => 1500,
            'fecha_emision' => now()->subDays(20)->toDateString(),
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'pagada' => false,
        ]);

        $archivo = $this->csvCobranza([
            '8001 Cliente Pago Auto,0,0,0,0,0,0,0',
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $cliente->refresh();
        $this->assertNull($cliente->fecha_inicio_credito);

        $this->assertDatabaseMissing('cobranza_facturas', [
            'cliente_id' => $cliente->id,
            'pagada' => false,
        ]);

        $this->assertDatabaseHas('cobranza_bitacoras', [
            'cliente_id' => $cliente->id,
            'tipo_evento' => 'pago',
        ]);
    }

    public function test_historial_incluye_clientes_con_credito_activo_y_liquidado(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver']);

        $activo = Cliente::create([
            'numero_cliente' => '8101',
            'nombre' => 'Cliente Activo',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
        ]);

        CobranzaFactura::create([
            'cliente_id' => $activo->id,
            'folio' => 'COB-8101',
            'monto' => 500,
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'pagada' => false,
        ]);

        $liquidado = Cliente::create([
            'numero_cliente' => '8102',
            'nombre' => 'Cliente Liquidado',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
        ]);

        CobranzaFactura::create([
            'cliente_id' => $liquidado->id,
            'folio' => 'COB-8102',
            'monto' => 300,
            'fecha_emision' => now()->subDays(60)->toDateString(),
            'fecha_vencimiento' => now()->subDays(30)->toDateString(),
            'pagada' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('auto-cobranza.historial'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.numero_cliente', '8101');
        $numeros = collect($response->json('data'))->pluck('numero_cliente')->all();
        $this->assertContains('8102', $numeros);
    }

    public function test_import_cxc_vacio_liquida_folios_y_envia_una_notificacion_masiva_de_pagos(): void
    {
        Notification::fake();
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);
        $destinatario = User::factory()->create();

        CobranzaConfiguracion::create([
            'llave' => 'notified_users_pagos',
            'valor' => json_encode([$destinatario->id]),
        ]);
        \Illuminate\Support\Facades\Cache::forget('cobranza_config_users_pagos');

        $clienteUno = Cliente::create([
            'numero_cliente' => '9201',
            'nombre' => 'CLIENTE UNO',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(10)->toDateString(),
        ]);

        $clienteDos = Cliente::create([
            'numero_cliente' => '9202',
            'nombre' => 'CLIENTE DOS',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(8)->toDateString(),
        ]);

        CobranzaFactura::create([
            'cliente_id' => $clienteUno->id,
            'folio' => '9201-A',
            'monto' => 1000,
            'fecha_emision' => now()->subDays(10)->toDateString(),
            'fecha_vencimiento' => now()->subDays(2)->toDateString(),
            'pagada' => false,
        ]);

        CobranzaFactura::create([
            'cliente_id' => $clienteUno->id,
            'folio' => '9201-B',
            'monto' => 500,
            'fecha_emision' => now()->subDays(9)->toDateString(),
            'fecha_vencimiento' => now()->subDays(1)->toDateString(),
            'pagada' => false,
        ]);

        CobranzaFactura::create([
            'cliente_id' => $clienteDos->id,
            'folio' => '9202-A',
            'monto' => 750,
            'fecha_emision' => now()->subDays(8)->toDateString(),
            'fecha_vencimiento' => now()->toDateString(),
            'pagada' => false,
        ]);

        $archivo = $this->csvCxc([]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('cobranza_facturas', ['folio' => '9201-A', 'pagada' => true]);
        $this->assertDatabaseHas('cobranza_facturas', ['folio' => '9201-B', 'pagada' => true]);
        $this->assertDatabaseHas('cobranza_facturas', ['folio' => '9202-A', 'pagada' => true]);

        Notification::assertSentTo(
            $destinatario,
            AlertaPagoLiquidoNotification::class,
            function (AlertaPagoLiquidoNotification $notification) {
                return count($notification->clientesPagados) === 2
                    && collect($notification->clientesPagados)->sum('montoPagado') === 2250.0;
            }
        );

        Notification::assertSentToTimes($destinatario, AlertaPagoLiquidoNotification::class, 1);

        $historial = $this->actingAs($user)->getJson(route('auto-cobranza.historial'));
        $historial->assertOk();
        $historial->assertJsonCount(2, 'data');
    }

    public function test_import_cxc_crea_multiples_facturas_por_folio(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        Cliente::create([
            'numero_cliente' => '9001',
            'nombre' => 'MATUS FELIX RODNEY',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('MATUS FELIX RODNEY', '4414', '2026-07-01', '2026-07-15', '$532.94', '$532.94'),
            $this->filaCxc('MATUS FELIX RODNEY', '4413', '2026-07-01', '2026-07-20', '$729.14', '$729.14'),
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $cliente = Cliente::where('nombre', 'MATUS FELIX RODNEY')->first();
        $this->assertNotNull($cliente);

        $facturas = CobranzaFactura::where('cliente_id', $cliente->id)->where('pagada', false)->get();
        $this->assertCount(2, $facturas);
        $this->assertEqualsCanonicalizing(['4413', '4414'], $facturas->pluck('folio')->all());
        $this->assertSame('2026-07-01', $cliente->fresh()->fecha_inicio_credito->toDateString());
        $this->assertSame(1262.08, round((float) $cliente->fresh()->saldo_total_pendiente, 2));
    }

    public function test_import_cxc_marca_pagada_factura_con_saldo_a_favor(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        $cliente = Cliente::create([
            'numero_cliente' => '9002',
            'nombre' => 'FIGUEROA GARCIA XOCHITL',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(30)->toDateString(),
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => '4250',
            'monto' => 100,
            'fecha_emision' => now()->subDays(30)->toDateString(),
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'pagada' => false,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('FIGUEROA GARCIA XOCHITL', '4250', '2026-06-11', '2026-06-30', '$4,252.53', '$-1.42', 'Vigente (Saldo a Favor)'),
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('cobranza_facturas', [
            'folio' => '4250',
            'pagada' => true,
        ]);
    }

    public function test_import_cxc_liquida_folio_ausente_manteniendo_otros_activos(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        $cliente = Cliente::create([
            'numero_cliente' => '9003',
            'nombre' => 'PALMA REYES ARELI',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(10)->toDateString(),
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => '4367',
            'monto' => 1225.72,
            'fecha_emision' => '2026-06-24',
            'fecha_vencimiento' => '2026-08-07',
            'pagada' => false,
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => '4368',
            'monto' => 500,
            'fecha_emision' => '2026-06-24',
            'fecha_vencimiento' => '2026-08-08',
            'pagada' => false,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('PALMA REYES ARELI', '4367', '2026-06-24', '2026-08-07', '$1,225.72', '$1,225.72'),
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('cobranza_facturas', [
            'folio' => '4367',
            'pagada' => false,
        ]);

        $this->assertDatabaseHas('cobranza_facturas', [
            'folio' => '4368',
            'pagada' => true,
        ]);

        $this->assertNotNull($cliente->fresh()->fecha_inicio_credito);
    }

    public function test_import_cxc_detecta_abono_parcial(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        $cliente = Cliente::create([
            'numero_cliente' => '9004',
            'nombre' => 'MATUS FELIX RODNEY',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(5)->toDateString(),
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => '4406',
            'monto' => 1049.15,
            'fecha_emision' => '2026-06-30',
            'fecha_vencimiento' => '2026-07-14',
            'pagada' => false,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('MATUS FELIX RODNEY', '4406', '2026-06-30', '2026-07-14', '$1,049.15', '$626.40'),
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $factura = CobranzaFactura::where('folio', '4406')->first();
        $this->assertTrue($factura->tiene_abono);
        $this->assertSame(626.40, (float) $factura->monto);

        $this->assertDatabaseHas('cobranza_bitacoras', [
            'cliente_id' => $cliente->id,
            'tipo_evento' => 'abono',
            'monto_anterior' => 1049.15,
            'monto_nuevo' => 626.40,
        ]);
    }

    public function test_import_cxc_detecta_abono_en_folio_nuevo_con_importe_original_mayor(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        $cliente = Cliente::create([
            'numero_cliente' => '3113',
            'nombre' => 'MAY REYES INGRID CECILIA',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(30)->toDateString(),
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => 'COB-3113-TEST',
            'monto' => 1675.78,
            'fecha_emision' => '2026-03-27',
            'fecha_vencimiento' => '2026-04-20',
            'pagada' => false,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('MAY REYES INGRID CECILIA', '3767', '2026-03-27', '2026-04-20', '$2,320.76', '$1,675.78'),
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $factura = CobranzaFactura::where('folio', '3767')->first();
        $this->assertNotNull($factura);
        $this->assertTrue($factura->tiene_abono);
        $this->assertSame(1675.78, (float) $factura->monto);

        $this->assertDatabaseHas('cobranza_bitacoras', [
            'cliente_id' => $cliente->id,
            'tipo_evento' => 'abono',
            'monto_anterior' => 2320.76,
            'monto_nuevo' => 1675.78,
        ]);
    }

    public function test_busqueda_clientes_con_comas_filtra_multiples_numeros(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver']);

        foreach (['100', '200', '300'] as $numero) {
            $cliente = Cliente::create([
                'numero_cliente' => $numero,
                'nombre' => "Cliente {$numero}",
                'lista_actual_id' => CatalogoListaDescuento::first()->id,
            ]);

            CobranzaFactura::create([
                'cliente_id' => $cliente->id,
                'folio' => "F-{$numero}",
                'monto' => 100,
                'fecha_emision' => now()->toDateString(),
                'fecha_vencimiento' => now()->addDays(30)->toDateString(),
                'pagada' => false,
            ]);
        }

        $response = $this->actingAs($user)->get(route('auto-cobranza.index', ['q' => '100,300']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('clientes.data', 2)
            ->where('clientes.data', fn ($data) => collect($data)->pluck('numero_cliente')->sort()->values()->all() === ['100', '300'])
        );
    }

    public function test_preview_cxc_detecta_credito_nuevo_sin_factura_activa(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        Cliente::create([
            'numero_cliente' => '9005',
            'nombre' => 'DENIS GARCIA NERI BENITA',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('DENIS GARCIA NERI BENITA', '4412', '2026-07-01', '2026-07-15', '$4,590.26', '$4,590.26'),
        ]);

        $response = $this->actingAs($user)->postJson(route('auto-cobranza.importar.preview'), [
            'archivo' => $archivo,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'creditos_nuevos');
        $response->assertJsonPath('creditos_nuevos.0.monto', 4590.26);
    }

    public function test_import_cxc_migra_consolidado_legacy_sin_doble_saldo_ni_abono_falso(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte']);

        $cliente = Cliente::create([
            'numero_cliente' => '9010',
            'nombre' => 'CLIENTE MIGRACION LEGACY',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'fecha_inicio_credito' => now()->subDays(20)->toDateString(),
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => 'COB-9010-TEST',
            'monto' => 5000,
            'fecha_emision' => now()->subDays(20)->toDateString(),
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'pagada' => false,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('CLIENTE MIGRACION LEGACY', '4501', '2026-06-01', '2026-06-15', '$1,500.00', '$1,500.00'),
            $this->filaCxc('CLIENTE MIGRACION LEGACY', '4502', '2026-06-02', '2026-06-16', '$532.94', '$532.94'),
        ]);

        $response = $this->actingAs($user)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $cliente->refresh();

        $this->assertDatabaseHas('cobranza_facturas', [
            'folio' => 'COB-9010-TEST',
            'pagada' => true,
        ]);

        $activas = CobranzaFactura::where('cliente_id', $cliente->id)
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->get();

        $this->assertCount(2, $activas);
        $this->assertEqualsCanonicalizing(['4501', '4502'], $activas->pluck('folio')->all());
        $this->assertSame(2032.94, round((float) $cliente->saldo_total_pendiente, 2));
        $this->assertFalse($activas->firstWhere('folio', '4501')->tiene_abono);
        $this->assertFalse($activas->firstWhere('folio', '4502')->tiene_abono);
    }

    public function test_import_cxc_no_notifica_limite_si_abono_deja_saldo_bajo_limite(): void
    {
        Notification::fake();
        $this->sincronizarListas();

        $importador = $this->usuarioConPermisos(['cobranza.ver', 'cobranza.importar_reporte', 'cobranza.recibir_alertas']);

        $cliente = Cliente::create([
            'numero_cliente' => '115',
            'nombre' => 'MATUS FELIX RODNEY',
            'lista_actual_id' => CatalogoListaDescuento::first()->id,
            'monto_credito_autorizado' => 7250,
            'fecha_inicio_credito' => now()->subDays(10)->toDateString(),
        ]);

        CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => '4380',
            'monto' => 7500,
            'fecha_emision' => '2026-06-30',
            'fecha_vencimiento' => '2026-07-14',
            'pagada' => false,
        ]);

        $archivo = $this->csvCxc([
            $this->filaCxc('MATUS FELIX RODNEY', '4380', '2026-06-30', '2026-07-14', '$7,500.00', '$6,465.77'),
        ]);

        $response = $this->actingAs($importador)->post(route('auto-cobranza.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();

        $cliente->refresh();
        $this->assertLessThanOrEqual(7250, (float) $cliente->saldo_total_pendiente);

        Notification::assertNotSentTo(
            $importador,
            AlertaLimiteCreditoSuperadoMasivoNotification::class
        );
    }
}
