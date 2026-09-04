<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UiRecepcionTurnoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $recepcion;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Centro']);
        $this->recepcion = User::factory()->create();
        $this->recepcion->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_MARCAR_PRIORIDAD,
        ]);
        $this->recepcion->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($this->recepcion, $this->sucursal->id);
    }

    public function test_recepcion_renderiza_inertia_con_permisos_y_catalogos(): void
    {
        $this->actingAs($this->recepcion)
            ->get(route('punto_venta.turnos.recepcion'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Turnos/Recepcion', false)
                ->where('permisos.alta', true)
                ->where('permisos.ver', true)
                ->where('permisos.marcar_prioridad', true)
                ->where('sucursal_activa.id', $this->sucursal->id)
                ->where('catalogos.servicio', 'Ventas')
                ->has('catalogos.estados')
                ->has('bandeja.en_cola'));
    }

    public function test_recepcion_solo_ver_sin_permiso_alta(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        $this->actingAs($usuario)
            ->get(route('punto_venta.turnos.recepcion'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permisos.ver', true)
                ->where('permisos.alta', false));
    }

    public function test_recepcion_sin_permiso_ver_ni_alta(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        $this->actingAs($usuario)
            ->get(route('punto_venta.turnos.recepcion'))
            ->assertForbidden();
    }

    public function test_flujo_ui_cliente_visitante_y_idempotencia(): void
    {
        $cliente = $this->crearCliente('Cliente UI');

        $clave = 'pdv:turno:ui-idempotente';
        $payloadVisitante = [
            'idempotency_key' => 'pdv:turno:ui-visitante',
            'nombre_llamado' => 'Visitante UI',
        ];

        $this->actingAs($this->recepcion)
            ->postJson(route('punto_venta.turnos.store'), $payloadVisitante)
            ->assertCreated()
            ->assertJsonPath('turno.snapshot_nombre_llamado', 'Visitante UI')
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_EN_COLA);

        $payloadCliente = [
            'idempotency_key' => 'pdv:turno:ui-cliente',
            'cliente_id' => $cliente->id,
        ];

        $this->actingAs($this->recepcion)
            ->postJson(route('punto_venta.turnos.store'), $payloadCliente)
            ->assertCreated()
            ->assertJsonPath('turno.cliente_id', $cliente->id);

        $payloadIdempotente = [
            'idempotency_key' => $clave,
            'nombre_llamado' => 'Reintento UI',
        ];

        $primero = $this->actingAs($this->recepcion)->postJson(route('punto_venta.turnos.store'), $payloadIdempotente);
        $segundo = $this->actingAs($this->recepcion)->postJson(route('punto_venta.turnos.store'), $payloadIdempotente);

        $primero->assertCreated();
        $segundo->assertCreated()
            ->assertJsonPath('turno.folio', $primero->json('turno.folio'));
    }

    public function test_prioridad_sin_permiso_marcar_prioridad(): void
    {
        $sinPrioridad = User::factory()->create();
        $sinPrioridad->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
        ]);
        $sinPrioridad->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($sinPrioridad, $this->sucursal->id);

        $this->actingAs($sinPrioridad)
            ->postJson(route('punto_venta.turnos.store'), [
                'idempotency_key' => 'pdv:turno:ui-sin-prioridad',
                'nombre_llamado' => 'Sin prioridad',
                'prioridad_adulto_mayor' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prioridad_adulto_mayor']);
    }

    private function crearCliente(string $nombre): Cliente
    {
        $listaId = CatalogoListaDescuento::query()->value('id');
        if ($listaId === null) {
            $listaId = CatalogoListaDescuento::query()->create([
                'nombre' => 'PUBLICO GENERAL',
                'activo' => true,
            ])->id;
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
}
