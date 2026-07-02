<?php

namespace Tests\Feature\Cobranza;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\CobranzaConfiguracion;
use App\Models\CobranzaFactura;
use App\Models\User;
use App\Notifications\AlertaCargaReporteCobranzaNotification;
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

    public function test_historial_solo_incluye_clientes_con_credito_activo(): void
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
        $response->assertJsonPath('data.0.numero_cliente', '8101');
        $response->assertJsonCount(1, 'data');
    }
}
