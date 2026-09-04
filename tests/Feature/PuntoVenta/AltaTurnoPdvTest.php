<?php

namespace Tests\Feature\PuntoVenta;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoCreado;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AltaTurnoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $recepcion;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Centro']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->recepcion = User::factory()->create();
        $this->recepcion->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
            PuntoVentaModulo::PERMISO_TURNOS_MARCAR_PRIORIDAD,
        ]);
        $this->recepcion->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($this->recepcion, $this->sucursal->id);
    }

    public function test_alta_con_cliente_registrado_crea_turno_en_cola_con_evento(): void
    {
        Event::fake([TurnoCreado::class]);

        $cliente = $this->crearCliente('Cliente registrado');

        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(clienteId: $cliente->id, clave: 'pdv:turno:cliente-1')
        );

        $response->assertCreated()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_EN_COLA)
            ->assertJsonPath('turno.folio', 'V-0001')
            ->assertJsonPath('turno.servicio', TurnoPdv::SERVICIO_VENTAS)
            ->assertJsonPath('turno.origen', TurnoPdv::ORIGEN_RECEPCION)
            ->assertJsonPath('turno.cliente_id', $cliente->id)
            ->assertJsonPath('turno.snapshot_nombre_llamado', 'Cliente registrado');

        $this->assertSame(1, TurnoPdv::query()->count());
        $evento = TurnoPdvEvento::query()->first();
        $this->assertSame(TurnoPdvEvento::TIPO_ALTA, $evento->tipo_evento);
        $this->assertSame('pdv:turno:cliente-1', $evento->idempotency_key);
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, $evento->estado_nuevo);

        Event::assertDispatched(TurnoCreado::class);
    }

    public function test_alta_con_visitante_usa_snapshot_sin_crear_cliente(): void
    {
        Event::fake([TurnoCreado::class]);

        $clientesAntes = Cliente::query()->count();

        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(nombre: 'Ana Visitante', clave: 'pdv:turno:visitante-1')
        );

        $response->assertCreated()
            ->assertJsonPath('turno.cliente_id', null)
            ->assertJsonPath('turno.snapshot_nombre_llamado', 'Ana Visitante');

        $this->assertSame($clientesAntes, Cliente::query()->count());
        Event::assertDispatched(TurnoCreado::class);
    }

    public function test_marca_prioridad_adulto_mayor_con_permiso(): void
    {
        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(
                nombre: 'Persona mayor',
                clave: 'pdv:turno:adulto-1',
                adultoMayor: true,
            )
        );

        $response->assertCreated()
            ->assertJsonPath('turno.prioridad_adulto_mayor', true)
            ->assertJsonPath('turno.prioridad_discapacidad', false);
    }

    public function test_rechaza_prioridad_sin_permiso_marcar(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        $this->actingAs($usuario)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(
                nombre: 'Sin permiso prioridad',
                clave: 'pdv:turno:prio-denegada',
                adultoMayor: true,
            )
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['prioridad_adulto_mayor']);
    }

    public function test_rechaza_campos_prohibidos_de_prioridad_sistema_y_sucursal(): void
    {
        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            array_merge($this->payloadAlta(nombre: 'Prueba', clave: 'pdv:turno:invalido'), [
                'prioridad_diamante' => true,
                'sucursal_id' => $this->otraSucursal->id,
            ])
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['prioridad_diamante', 'sucursal_id']);
    }

    public function test_detecta_lista_diamante_desde_cliente_sin_duplicar_maestro(): void
    {
        $listaDiamante = CatalogoListaDescuento::query()->create([
            'nombre' => 'MAYOREO DIAMANTE',
            'activo' => true,
        ]);
        $cliente = $this->crearCliente('Cliente diamante', $listaDiamante->id);

        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(clienteId: $cliente->id, clave: 'pdv:turno:diamante-1')
        )->assertCreated()
            ->assertJsonPath('turno.prioridad_diamante', true);
    }

    public function test_reintento_idempotente_devuelve_mismo_turno(): void
    {
        Event::fake([TurnoCreado::class]);

        $clave = 'pdv:turno:idempotente-1';
        $payload = $this->payloadAlta(nombre: 'Reintento', clave: $clave);

        $primero = $this->actingAs($this->recepcion)->postJson(route('punto_venta.turnos.store'), $payload);
        $segundo = $this->actingAs($this->recepcion)->postJson(route('punto_venta.turnos.store'), $payload);

        $primero->assertCreated();
        $segundo->assertCreated()
            ->assertJsonPath('turno.id', $primero->json('turno.id'))
            ->assertJsonPath('turno.folio', $primero->json('turno.folio'));

        $this->assertSame(1, TurnoPdv::query()->count());
        $this->assertSame(1, TurnoPdvEvento::query()->count());
        Event::assertDispatched(TurnoCreado::class, 1);
    }

    public function test_folios_consecutivos_son_unicos_en_la_sucursal(): void
    {
        $folios = [];

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->actingAs($this->recepcion)->postJson(
                route('punto_venta.turnos.store'),
                $this->payloadAlta(nombre: "Persona {$i}", clave: "pdv:turno:folio-{$i}")
            );
            $response->assertCreated();
            $folios[] = $response->json('turno.folio');
        }

        $this->assertSame(['V-0001', 'V-0002', 'V-0003'], $folios);
        $this->assertSame(3, TurnoPdv::query()->where('sucursal_id', $this->sucursal->id)->count());
    }

    public function test_deniega_alta_sin_permiso_o_sin_sucursal_activa(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
        $sinPermiso->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($sinPermiso, $this->sucursal->id);

        $this->actingAs($sinPermiso)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(nombre: 'Sin permiso', clave: 'pdv:turno:sin-permiso')
        )->assertForbidden();

        $sinSucursal = User::factory()->create();
        $sinSucursal->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
        ]);

        $this->actingAs($sinSucursal)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(nombre: 'Sin sucursal', clave: 'pdv:turno:sin-sucursal')
        )->assertForbidden();
    }

    public function test_alta_usa_sucursal_activa_y_no_permite_operar_otra_asignada(): void
    {
        $this->recepcion->concederAccesoSucursal($this->otraSucursal, esPrincipal: false);
        app(AlcancePdv::class)->establecerSucursalActiva($this->recepcion, $this->sucursal->id);

        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(nombre: 'Solo activa', clave: 'pdv:turno:sucursal-activa')
        );

        $response->assertCreated()
            ->assertJsonPath('turno.sucursal_id', $this->sucursal->id);

        $this->assertFalse(
            TurnoPdv::query()->where('sucursal_id', $this->otraSucursal->id)->exists()
        );

        app(AlcancePdv::class)->establecerSucursalActiva($this->recepcion, $this->otraSucursal->id);

        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(nombre: 'En otra', clave: 'pdv:turno:otra-sucursal')
        )->assertCreated()
            ->assertJsonPath('turno.sucursal_id', $this->otraSucursal->id)
            ->assertJsonPath('turno.folio', 'V-0001');
    }

    public function test_no_permite_segundo_turno_activo_del_mismo_cliente_en_sucursal(): void
    {
        $cliente = $this->crearCliente('Cliente activo');

        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(clienteId: $cliente->id, clave: 'pdv:turno:dup-1')
        )->assertCreated();

        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(clienteId: $cliente->id, clave: 'pdv:turno:dup-2')
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente_id']);
    }

    public function test_alta_sin_persona_disponible_permanece_en_cola(): void
    {
        Event::fake([TurnoCreado::class, TurnoAsignado::class]);

        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(nombre: 'En cola', clave: 'pdv:turno:sin-disponible')
        );

        $response->assertCreated()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_EN_COLA);

        $this->assertSame(0, TurnoPdvAtencion::query()->count());
        $this->assertFalse(
            TurnoPdvEvento::query()->where('tipo_evento', TurnoPdvEvento::TIPO_ASIGNADO)->exists()
        );

        Event::assertDispatched(TurnoCreado::class);
        Event::assertNotDispatched(TurnoAsignado::class);
    }

    public function test_alta_con_persona_disponible_asigna_y_emite_evento(): void
    {
        Event::fake([TurnoCreado::class, TurnoAsignado::class]);

        $ventas = User::factory()->create(['name' => 'Vendedor Uno']);
        $this->simularPersonaDisponible($ventas);

        $response = $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(nombre: 'Asignado al alta', clave: 'pdv:turno:con-disponible')
        );

        $response->assertCreated()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_ASIGNADO);

        $turno = TurnoPdv::query()->first();
        $this->assertNotNull($turno->atencion_actual_id);

        $eventoAlta = TurnoPdvEvento::query()
            ->where('tipo_evento', TurnoPdvEvento::TIPO_ALTA)
            ->first();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $eventoAlta->estado_nuevo);

        $eventoAsignado = TurnoPdvEvento::query()
            ->where('tipo_evento', TurnoPdvEvento::TIPO_ASIGNADO)
            ->first();
        $this->assertNotNull($eventoAsignado);
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, $eventoAsignado->estado_anterior);
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $eventoAsignado->estado_nuevo);
        $this->assertNull($eventoAsignado->actor_id);

        $atencion = TurnoPdvAtencion::query()->first();
        $this->assertSame($ventas->id, $atencion->user_id);
        $this->assertSame($turno->id, $atencion->turno_id);

        Event::assertDispatched(TurnoCreado::class);
        Event::assertDispatched(TurnoAsignado::class);
    }

    public function test_reintento_idempotente_preserva_asignacion_inmediata(): void
    {
        Event::fake([TurnoCreado::class, TurnoAsignado::class]);

        $ventas = User::factory()->create();
        $this->simularPersonaDisponible($ventas);

        $clave = 'pdv:turno:idempotente-asignado';
        $payload = $this->payloadAlta(nombre: 'Idempotente asignado', clave: $clave);

        $primero = $this->actingAs($this->recepcion)->postJson(route('punto_venta.turnos.store'), $payload);
        $segundo = $this->actingAs($this->recepcion)->postJson(route('punto_venta.turnos.store'), $payload);

        $primero->assertCreated()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_ASIGNADO);
        $segundo->assertCreated()
            ->assertJsonPath('turno.id', $primero->json('turno.id'))
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_ASIGNADO);

        $this->assertSame(1, TurnoPdv::query()->count());
        $this->assertSame(1, TurnoPdvAtencion::query()->count());
        $this->assertSame(2, TurnoPdvEvento::query()->count());
        Event::assertDispatched(TurnoCreado::class, 1);
        Event::assertDispatched(TurnoAsignado::class, 1);
    }

    public function test_rechaza_alta_con_turno_activo_en_reatencion(): void
    {
        $cliente = $this->crearCliente('Cliente reatencion');

        TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'cliente_id' => $cliente->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
        ]);

        $this->actingAs($this->recepcion)->postJson(
            route('punto_venta.turnos.store'),
            $this->payloadAlta(clienteId: $cliente->id, clave: 'pdv:turno:reatencion-dup')
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadAlta(
        ?int $clienteId = null,
        ?string $nombre = null,
        string $clave = 'pdv:turno:default',
        bool $adultoMayor = false,
        bool $discapacidad = false,
    ): array {
        $payload = [
            'idempotency_key' => $clave,
            'prioridad_adulto_mayor' => $adultoMayor,
            'prioridad_discapacidad' => $discapacidad,
        ];

        if ($clienteId !== null) {
            $payload['cliente_id'] = $clienteId;
        }

        if ($nombre !== null) {
            $payload['nombre_llamado'] = $nombre;
        }

        return $payload;
    }

    private function crearCliente(string $nombre, ?int $listaId = null): Cliente
    {
        if ($listaId === null) {
            $listaId = CatalogoListaDescuento::query()->value('id');
            if ($listaId === null) {
                $listaId = CatalogoListaDescuento::query()->create([
                    'nombre' => 'PUBLICO GENERAL',
                    'activo' => true,
                ])->id;
            }
        }

        return Cliente::query()->create([
            'numero_cliente' => (string) fake()->unique()->numerify('92###'),
            'nombre' => $nombre,
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
        ]);
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    private function simularPersonaDisponible(User $persona): void
    {
        $this->app->instance(ConsultaPersonaDisponiblePdv::class, new class($persona) implements ConsultaPersonaDisponiblePdv
        {
            public function __construct(private readonly User $persona) {}

            public function primeraDisponible(int $sucursalId, string $servicio): ?User
            {
                return $this->persona;
            }

            public function esDisponible(User $user, int $sucursalId, bool $paraAltaNueva = false): bool
            {
                return $user->is($this->persona);
            }
        });
    }
}
