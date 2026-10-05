<?php

namespace Tests\Feature\Mobile;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\Departamento;
use App\Models\MobileDevice;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ApiExterna\ApiDocumentacionService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobilePuntoVentaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $principal;

    private Sucursal $otra;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ThrottleRequests::class]);
        Role::findOrCreate('Super Admin', 'web');

        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );

        $this->principal = Sucursal::factory()->create(['nombre' => 'Sucursal Principal']);
        $this->otra = Sucursal::factory()->create(['nombre' => 'Sucursal Alterna']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_REZAGADOS,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
        ]);
        $this->usuario->concederAccesoSucursal($this->principal, esPrincipal: true);
        $this->usuario->concederAccesoSucursal($this->otra);
    }

    public function test_contexto_usa_la_sucursal_principal_si_el_dispositivo_no_tiene_activa(): void
    {
        $this->conToken($this->token())
            ->getJson('/api/v1/mobile/punto-venta/contexto')
            ->assertOk()
            ->assertJsonPath('sucursal_activa.id', $this->principal->id)
            ->assertJsonPath('permisos.resguardos_ver', true)
            ->assertJsonPath('permisos.turnos_alta', true)
            ->assertJsonPath('registro_manual', false)
            ->assertJsonPath('origenes', []);
    }

    public function test_contexto_expone_el_registro_manual_y_solo_origenes_validos(): void
    {
        $this->activarRegistroManual();

        $visible = Departamento::query()->firstOrCreate(
            ['nombre' => 'Aromas móvil'],
            ['activo' => true, 'visible_origen_resguardo_pdv' => true]
        );
        $visible->forceFill([
            'activo' => true,
            'visible_origen_resguardo_pdv' => true,
        ])->save();

        $oculto = Departamento::query()->firstOrCreate(
            ['nombre' => 'Origen oculto móvil'],
            ['activo' => true, 'visible_origen_resguardo_pdv' => false]
        );
        $oculto->forceFill(['visible_origen_resguardo_pdv' => false])->save();

        $inactivo = Departamento::query()->firstOrCreate(
            ['nombre' => 'Origen inactivo móvil'],
            ['activo' => false, 'visible_origen_resguardo_pdv' => true]
        );
        $inactivo->forceFill([
            'activo' => false,
            'visible_origen_resguardo_pdv' => true,
        ])->save();

        $respuesta = $this->conToken($this->token())
            ->getJson('/api/v1/mobile/punto-venta/contexto')
            ->assertOk()
            ->assertJsonPath('registro_manual', true);

        $origenes = collect($respuesta->json('origenes'));
        $this->assertTrue($origenes->contains(fn (array $origen): bool => $origen['id'] === $visible->id
            && $origen['nombre'] === 'Aromas móvil'));
        $this->assertFalse($origenes->contains(fn (array $origen): bool => $origen['id'] === $oculto->id));
        $this->assertFalse($origenes->contains(fn (array $origen): bool => $origen['id'] === $inactivo->id));
    }

    public function test_establece_sucursal_activa_del_dispositivo_y_rechaza_una_no_operable(): void
    {
        $token = $this->token();

        $this->conToken($token)
            ->putJson('/api/v1/mobile/punto-venta/sucursal-activa', [
                'sucursal_id' => $this->otra->id,
            ])
            ->assertOk()
            ->assertJsonPath('sucursal_activa.id', $this->otra->id);

        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $this->usuario->id,
            'sucursal_activa_id' => $this->otra->id,
        ]);

        $ajena = Sucursal::factory()->create();

        $this->conToken($token)
            ->putJson('/api/v1/mobile/punto-venta/sucursal-activa', [
                'sucursal_id' => $ajena->id,
            ])
            ->assertForbidden();
    }

    public function test_operacion_responde_409_si_no_hay_sucursal_resoluble(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $usuario->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: false);
        $usuario->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: false);

        $this->conToken($this->token($usuario))
            ->getJson('/api/v1/mobile/punto-venta/contexto')
            ->assertOk()
            ->assertJsonPath('sucursal_activa', null);

        $this->conToken($this->token($usuario))
            ->getJson('/api/v1/mobile/punto-venta/resguardos')
            ->assertStatus(409)
            ->assertJsonPath('code', 'sucursal_activa_requerida');
    }

    public function test_alta_recepcion_y_paso_a_recepcion_en_la_sucursal_activa(): void
    {
        Storage::fake('local');
        $this->activarRegistroManual();
        $cliente = $this->crearCliente();
        $origen = Departamento::query()->firstOrCreate(
            ['nombre' => 'Aromas'],
            ['activo' => true, 'visible_origen_resguardo_pdv' => true]
        );

        $token = $this->token(sucursalActivaId: $this->otra->id);

        $alta = $this->conToken($token)->post('/api/v1/mobile/punto-venta/resguardos', [
            'idempotency_key' => 'pdv:man:movil-1',
            'cliente_id' => $cliente->id,
            'folio' => 'REM-MOVIL-001',
            'origen_id' => $origen->id,
            'cantidad_bultos_esperada' => 1,
            'archivo_ticket' => UploadedFile::fake()->image('ticket.jpg'),
            'foto_paquete' => UploadedFile::fake()->image('paquete.jpg'),
        ], ['Accept' => 'application/json']);

        $alta->assertCreated()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION);

        $resguardo = ResguardoPdv::query()->firstOrFail();
        $this->assertSame($this->otra->id, $resguardo->sucursal_id);

        $this->conToken($token)
            ->putJson('/api/v1/mobile/punto-venta/resguardos/'.$resguardo->id.'/recepcion', [
                'version' => 1,
                'idempotency_key' => 'pdv:rec:movil-1',
            ])
            ->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_RECIBIDO);

        $this->conToken($token)
            ->putJson('/api/v1/mobile/punto-venta/resguardos/'.$resguardo->id.'/pasar-recepcion', [
                'version' => 2,
                'idempotency_key' => 'pdv:paso:movil-1',
            ])
            ->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_RECEPCION);
    }

    public function test_entrega_registra_la_firma(): void
    {
        Storage::fake('local');
        $token = $this->token();
        $resguardo = $this->resguardoEnCustodia($this->principal);

        $this->conToken($token)->put('/api/v1/mobile/punto-venta/resguardos/'.$resguardo->id.'/entrega', [
            'version' => 1,
            'idempotency_key' => 'pdv:ent:movil-1',
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Persona titular',
            'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
            'firma' => UploadedFile::fake()->image('firma.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_ENTREGADO)
            ->assertJsonPath('entrega.nombre_quien_retira', 'Persona titular');
    }

    public function test_listado_rezagado_exige_permiso_y_el_detalle_respeta_la_sucursal_activa(): void
    {
        $token = $this->token();
        $visible = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->principal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'salida_cedis_at' => now()->subDays(20),
        ]);
        $ajeno = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->otra->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
        ]);

        $this->conToken($token)
            ->getJson('/api/v1/mobile/punto-venta/resguardos/'.$visible->id)
            ->assertOk()
            ->assertJsonPath('resguardo.id', $visible->id);

        $this->conToken($token)
            ->getJson('/api/v1/mobile/punto-venta/resguardos/'.$ajeno->id)
            ->assertNotFound();

        $sinRezagados = User::factory()->create();
        $sinRezagados->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $sinRezagados->concederAccesoSucursal($this->principal, esPrincipal: true);

        $this->conToken($this->token($sinRezagados))
            ->getJson('/api/v1/mobile/punto-venta/resguardos?antiguedad='.AntiguedadOperativaResguardoPdv::REZAGADO)
            ->assertForbidden();

        $this->conToken($token)
            ->getJson('/api/v1/mobile/punto-venta/resguardos?antiguedad='.AntiguedadOperativaResguardoPdv::REZAGADO)
            ->assertOk()
            ->assertJsonPath('filtros.antiguedad', AntiguedadOperativaResguardoPdv::REZAGADO);
    }

    public function test_alta_y_recepcion_de_turnos_usan_la_sucursal_activa_del_dispositivo(): void
    {
        $token = $this->token(sucursalActivaId: $this->otra->id);

        $this->conToken($token)
            ->postJson('/api/v1/mobile/punto-venta/turnos', [
                'idempotency_key' => 'pdv:turno:movil-1',
                'nombre_llamado' => 'Persona en recepción',
            ])
            ->assertCreated()
            ->assertJsonPath('turno.sucursal_id', $this->otra->id)
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_EN_COLA)
            ->assertJsonPath('turno.snapshot_nombre_llamado', 'Persona en recepción');

        $this->conToken($token)
            ->getJson('/api/v1/mobile/punto-venta/turnos/recepcion')
            ->assertOk()
            ->assertJsonPath('resumen.en_espera', 1)
            ->assertJsonPath('en_cola.0.sucursal_id', $this->otra->id)
            ->assertJsonPath('permisos.alta', true)
            ->assertJsonPath('catalogos.servicio', 'Ventas')
            ->assertJsonPath('sucursal_activa.id', $this->otra->id);
    }

    public function test_contexto_expone_busqueda_de_clientes_para_alta_de_turnos(): void
    {
        $this->conToken($this->token())
            ->getJson('/api/v1/mobile/punto-venta/contexto')
            ->assertOk()
            ->assertJsonPath('permisos.turnos_buscar_clientes', true)
            ->assertJsonPath('permisos.turnos_alta', true);
    }

    public function test_la_documentacion_movil_lista_las_rutas_de_piso(): void
    {
        $rutas = collect(app(ApiDocumentacionService::class)->construirDatos()['mobile']['endpoints'])
            ->pluck('ruta');

        $this->assertTrue($rutas->contains('/mobile/punto-venta/resguardos/{id}/entrega'));
        $this->assertTrue($rutas->contains('/mobile/punto-venta/turnos'));
        $this->assertTrue($rutas->contains('/mobile/punto-venta/visitas-programadas'));
        $this->assertTrue($rutas->contains('/mobile/punto-venta/sucursal-activa'));
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(?User $usuario = null, ?int $sucursalActivaId = null): string
    {
        $usuario ??= $this->usuario;

        $device = MobileDevice::query()->create([
            'user_id' => $usuario->id,
            'device_uuid' => (string) Str::uuid(),
            'sucursal_activa_id' => $sucursalActivaId,
            'last_seen_at' => now(),
        ]);

        return $usuario->createToken('mobile:'.$device->device_uuid, ['mobile'])->plainTextToken;
    }

    private function resguardoEnCustodia(Sucursal $sucursal): ResguardoPdv
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'recepcion_fisica_at' => now()->subHour(),
            'entrega_bloqueada' => false,
            'version' => 1,
        ]);

        ResguardoPdvBulto::factory()->create([
            'resguardo_id' => $resguardo->id,
            'folio' => 'CJA-'.$resguardo->id,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now()->subHour(),
            'recepcion_por_id' => $this->usuario->id,
        ]);

        return $resguardo;
    }

    private function crearCliente(): Cliente
    {
        $listaId = CatalogoListaDescuento::query()->value('id');
        if ($listaId === null) {
            $listaId = CatalogoListaDescuento::query()->create([
                'nombre' => 'PUBLICO GENERAL',
                'activo' => true,
            ])->id;
        }

        return Cliente::query()->create([
            'numero_cliente' => '93001',
            'nombre' => 'Cliente resguardo móvil',
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
        ]);
    }

    private function activarRegistroManual(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => RegistroManualResguardoPdvConfig::CLAVE],
            ['valor' => '1', 'tipo' => 'boolean', 'grupo' => 'PuntoVenta']
        );
    }
}
