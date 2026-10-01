<?php

namespace Tests\Feature\Mobile;

use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\MobileDevice;
use App\Models\Producto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Scopes\EsDemoScope;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Demo\AlcanceDemo;
use App\Services\Demo\SemillaModoDemo;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\SincronizarEstatusSucursalPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ModoDemoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    private Sucursal $sucursalDemo;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ThrottleRequests::class]);

        Config::set('demo.review_password', 'secreta-demo');
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );

        app(SemillaModoDemo::class)->sembrar();

        $this->demo = User::query()->where('username', SemillaModoDemo::USERNAME)->firstOrFail();
        $this->sucursalDemo = Sucursal::withoutGlobalScope(EsDemoScope::class)
            ->where('codigo', SemillaModoDemo::CODIGO_SUCURSAL)
            ->firstOrFail();
        $this->token = $this->postJson('/api/v1/mobile/login', [
            'login' => $this->demo->email,
            'password' => 'secreta-demo',
            'device_uuid' => '11111111-1111-4111-8111-111111111111',
        ])->json('access_token');
    }

    public function test_login_y_me_exponen_es_demo_sin_permisos_administrativos(): void
    {
        $login = $this->postJson('/api/v1/mobile/login', [
            'login' => SemillaModoDemo::USERNAME,
            'password' => 'secreta-demo',
            'device_uuid' => '22222222-2222-4222-8222-222222222222',
        ])->assertOk();

        $login->assertJsonPath('es_demo', true)
            ->assertJsonPath('user.name', 'Revisión Play');

        $permisos = $login->json('permissions');
        $this->assertContains(PuntoVentaModulo::PERMISO_ACCEDER, $permisos);
        $this->assertContains('clientes.ver', $permisos);
        $this->assertNotContains('pdv.alcance.global', $permisos);
        $this->assertNotContains('usuarios.gestionar', $permisos);

        $this->withToken($this->token)
            ->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('es_demo', true);
    }

    public function test_contexto_tiene_una_sucursal_y_registro_manual(): void
    {
        $respuesta = $this->withToken($this->token)
            ->getJson('/api/v1/mobile/punto-venta/contexto')
            ->assertOk()
            ->assertJsonPath('registro_manual', true)
            ->assertJsonPath('sucursal_activa.id', $this->sucursalDemo->id)
            ->assertJsonCount(1, 'sucursales_operables');

        $this->assertSame('Mostrador demostración', $respuesta->json('origenes.0.nombre'));
    }

    public function test_cambio_de_sucursal_real_se_rechaza(): void
    {
        $real = Sucursal::factory()->create(['nombre' => 'Sucursal real']);

        $this->withToken($this->token)
            ->putJson('/api/v1/mobile/punto-venta/sucursal-activa', [
                'sucursal_id' => $real->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'sucursal_demo_fija');
    }

    public function test_listados_ocultan_datos_reales_y_un_id_real_responde_404(): void
    {
        $realSucursal = Sucursal::factory()->create();
        $real = ResguardoPdv::factory()->create([
            'sucursal_id' => $realSucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'es_demo' => false,
        ]);
        $turnoReal = TurnoPdv::factory()->create([
            'sucursal_id' => $realSucursal->id,
            'es_demo' => false,
        ]);

        $listado = $this->withToken($this->token)
            ->getJson('/api/v1/mobile/punto-venta/resguardos')
            ->assertOk()
            ->json('resguardos');

        $ids = collect($listado)->pluck('id');
        $this->assertFalse($ids->contains($real->id));

        $demoId = ResguardoPdv::withoutGlobalScope(EsDemoScope::class)
            ->where('snapshot_folio', 'DEMO-PEND')
            ->value('id');

        $this->withToken($this->token)
            ->getJson('/api/v1/mobile/punto-venta/resguardos/'.$demoId)
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/mobile/punto-venta/resguardos/'.$real->id)
            ->assertNotFound();

        $turnos = $this->withToken($this->token)
            ->getJson('/api/v1/mobile/punto-venta/turnos/recepcion')
            ->assertOk()
            ->json('en_cola');

        $this->assertFalse(collect($turnos)->pluck('id')->contains($turnoReal->id));

        $piso = User::factory()->create();
        $piso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $piso->concederAccesoSucursal($realSucursal, esPrincipal: true);
        $this->app['auth']->forgetGuards();
        $tokenPiso = $piso->createToken('mobile:piso-demo-test', ['mobile'])->plainTextToken;
        MobileDevice::query()->create([
            'user_id' => $piso->id,
            'device_uuid' => 'piso-demo-test',
            'sucursal_activa_id' => $realSucursal->id,
            'last_seen_at' => now(),
        ]);

        $demoId = ResguardoPdv::withoutGlobalScope(EsDemoScope::class)
            ->where('snapshot_folio', 'DEMO-PEND')
            ->value('id');

        $this->withToken($tokenPiso)
            ->getJson('/api/v1/mobile/punto-venta/resguardos/'.$demoId)
            ->assertNotFound();
    }

    public function test_alta_de_resguardo_y_turno_persisten_solo_en_el_recinto(): void
    {
        Storage::fake('local');
        Bus::fake([EjecutarMatchmakerTurnosPdvJob::class]);
        Notification::fake();

        $cliente = Cliente::withoutGlobalScope(EsDemoScope::class)->where('numero_cliente', 'DEMO-001')->firstOrFail();
        $origenId = DB::table('departamentos')->where('codigo', 'DEMO')->value('id');
        $producto = Producto::withoutGlobalScope(EsDemoScope::class)->where('sku', 'DEMO-MUESTRA-1')->firstOrFail();
        $clienteReal = $this->clienteReal();

        $this->withToken($this->token)
            ->post('/api/v1/mobile/punto-venta/resguardos', [
                'idempotency_key' => 'pdv:man:demo-1',
                'cliente_id' => $clienteReal->id,
                'folio' => 'DEMO-RECHAZO',
                'origen_id' => $origenId,
                'cantidad_bultos_esperada' => 1,
                'archivo_ticket' => UploadedFile::fake()->image('ticket.jpg'),
                'foto_paquete' => UploadedFile::fake()->image('paquete.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->post('/api/v1/mobile/punto-venta/resguardos', [
                'idempotency_key' => 'pdv:man:demo-2',
                'cliente_id' => $cliente->id,
                'folio' => 'DEMO-NUEVO',
                'origen_id' => $origenId,
                'cantidad_bultos_esperada' => 1,
                'piezas' => [
                    ['producto_id' => $producto->id, 'cantidad' => 1],
                ],
                'archivo_ticket' => UploadedFile::fake()->image('ticket.jpg'),
                'foto_paquete' => UploadedFile::fake()->image('paquete.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $nuevo = ResguardoPdv::withoutGlobalScope(EsDemoScope::class)->where('snapshot_folio', 'DEMO-NUEVO')->first();
        $this->assertNotNull($nuevo);
        $this->assertTrue($nuevo->es_demo);
        $this->assertSame($this->sucursalDemo->id, $nuevo->sucursal_id);
        $this->assertTrue(
            str_starts_with((string) DB::table('pdv_resguardo_evidencias')->where('resguardo_id', $nuevo->id)->value('ruta_interna'), 'demo/resguardos/')
        );

        $this->withToken($this->token)
            ->postJson('/api/v1/mobile/punto-venta/turnos', [
                'idempotency_key' => 'pdv:turno:demo-1',
                'nombre_llamado' => 'Visita demostración',
            ])
            ->assertCreated();

        $this->assertTrue((bool) DB::table('pdv_turnos')->where('snapshot_nombre_llamado', 'Visita demostración')->value('es_demo'));

        Bus::assertNotDispatched(EjecutarMatchmakerTurnosPdvJob::class);

        $piso = User::factory()->create();
        Permission::findOrCreate('pdv.alcance.global', 'web');
        $piso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            'pdv.alcance.global',
        ]);

        app(AlcanceDemo::class)->fijarDesdeUsuario($piso);
        $idsVisibles = ResguardoPdv::query()->pluck('id');
        $this->assertFalse($idsVisibles->contains($nuevo->id));
    }

    public function test_bootstrap_de_clientes_no_incluye_ids_reales(): void
    {
        $real = $this->clienteReal();

        $head = $this->withToken($this->token)
            ->getJson('/api/v1/mobile/sync/changes/head')
            ->assertOk();

        $this->assertSame(3, $head->json('authorized_total'));

        $numeros = collect($this->withToken($this->token)
            ->getJson('/api/v1/mobile/clientes?q=Cliente')
            ->assertOk()
            ->json('data'))->pluck('numero_cliente');

        $this->assertTrue($numeros->contains('DEMO-001'));
        $this->assertFalse($numeros->contains($real->numero_cliente));
    }

    public function test_entrega_guarda_la_firma_fuera_del_almacenamiento_operativo(): void
    {
        Storage::fake('local');
        Notification::fake();

        $resguardo = ResguardoPdv::withoutGlobalScope(EsDemoScope::class)->create([
            'sucursal_id' => $this->sucursalDemo->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'recepcion_fisica_at' => now()->subHour(),
            'entrega_bloqueada' => false,
            'snapshot_folio' => 'DEMO-ENT',
            'es_demo' => true,
            'version' => 1,
        ]);
        ResguardoPdvBulto::query()->create([
            'resguardo_id' => $resguardo->id,
            'folio' => 'CJA-DEMO-1',
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now()->subHour(),
            'recepcion_por_id' => $this->demo->id,
            'version' => 1,
        ]);

        $this->withToken($this->token)
            ->put('/api/v1/mobile/punto-venta/resguardos/'.$resguardo->id.'/entrega', [
                'version' => 1,
                'idempotency_key' => 'pdv:ent:demo-1',
                'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
                'nombre_quien_retira' => 'Persona titular',
                'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
                'firma' => UploadedFile::fake()->image('firma.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $ruta = DB::table('pdv_resguardo_evidencias')->where('resguardo_id', $resguardo->id)->value('ruta_interna');
        $this->assertIsString($ruta);
        $this->assertStringStartsWith('demo/resguardos/'.$resguardo->id.'/entregas', $ruta);
        $this->assertFalse(str_starts_with($ruta, 'pdv/resguardos/'));
        Notification::assertNothingSent();
    }

    public function test_una_accion_demo_no_actualiza_el_pedido(): void
    {
        $resguardo = ResguardoPdv::withoutGlobalScope(EsDemoScope::class)
            ->where('snapshot_folio', 'DEMO-CUST')
            ->firstOrFail();
        $resguardo->forceFill([
            'estado' => ResguardoPdv::ESTADO_RECIBIDO,
            'es_demo' => true,
        ])->save();

        app(SincronizarEstatusSucursalPedidoBmaService::class)->desdeResguardo($resguardo->fresh());

        $this->assertNull($resguardo->pedido_bma_id);
    }

    public function test_demo_reset_restaura_la_semilla_y_no_toca_filas_reales(): void
    {
        $realSucursal = Sucursal::factory()->create();
        $real = ResguardoPdv::factory()->create([
            'sucursal_id' => $realSucursal->id,
            'snapshot_folio' => 'REAL-1',
            'es_demo' => false,
        ]);

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertDatabaseHas('pdv_resguardos', [
            'id' => $real->id,
            'es_demo' => false,
        ]);
        $this->assertDatabaseHas('pdv_resguardos', [
            'snapshot_folio' => 'DEMO-PEND',
            'es_demo' => true,
        ]);
        $this->assertDatabaseMissing('pdv_resguardos', [
            'snapshot_folio' => 'NO-EXISTE',
        ]);
        $this->assertSame(3, DB::table('clientes')->where('es_demo', true)->count());
    }

    public function test_el_panel_web_rechaza_la_cuenta_demo(): void
    {
        $this->post('/login', [
            'login' => $this->demo->email,
            'password' => 'secreta-demo',
        ])->assertRedirect();

        $this->assertGuest();
    }

    private function clienteReal(): Cliente
    {
        $lista = CatalogoListaDescuento::query()->first();

        return Cliente::query()->create([
            'numero_cliente' => '90001',
            'nombre' => 'Cliente real',
            'lista_actual_id' => $lista->id,
            'es_demo' => false,
        ]);
    }
}
