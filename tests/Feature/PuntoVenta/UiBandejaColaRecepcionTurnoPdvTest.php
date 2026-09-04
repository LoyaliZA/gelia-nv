<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\MotivosBajaColaTurnoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UiBandejaColaRecepcionTurnoPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private User $recepcion;

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

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Bandeja']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Remota']);
        $this->recepcion = User::factory()->create(['name' => 'Recepcion Bandeja']);

        $this->recepcion->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA,
        ]);
        $this->recepcion->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($this->recepcion, $this->sucursal->id);
    }

    public function test_datos_lista_en_cola_y_asignados_de_sucursal_activa(): void
    {
        $enCola = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'snapshot_nombre_llamado' => 'Cliente Cola',
        ]);

        $vendedor = User::factory()->create(['name' => 'Vendedor Asignado']);
        $asignado = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'snapshot_nombre_llamado' => 'Cliente Asignado',
        ]);
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $asignado->id,
            'user_id' => $vendedor->id,
            'inicio_at' => now()->subMinutes(3),
            'fin_at' => null,
        ]);
        $asignado->update(['atencion_actual_id' => $atencion->id]);

        TurnoPdv::factory()->create([
            'sucursal_id' => $this->otraSucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);

        $this->actingAs($this->recepcion)
            ->getJson(route('punto_venta.turnos.recepcion.datos'))
            ->assertOk()
            ->assertJsonCount(1, 'en_cola')
            ->assertJsonCount(1, 'asignados')
            ->assertJsonPath('en_cola.0.id', $enCola->id)
            ->assertJsonPath('en_cola.0.puede_baja_cola', true)
            ->assertJsonPath('asignados.0.id', $asignado->id)
            ->assertJsonPath('asignados.0.puede_baja_cola', false)
            ->assertJsonPath('asignados.0.atencion.primer_nombre', 'Vendedor');
    }

    public function test_baja_exitosa_y_rechazo_si_ya_asignado(): void
    {
        $enCola = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);

        $this->actingAs($this->recepcion)
            ->postJson(route('punto_venta.turnos.baja_cola', $enCola), [
                'version' => $enCola->version,
                'idempotency_key' => 'pdv:ui-bandeja:baja-ok',
                'motivo' => MotivosBajaColaTurnoPdv::SE_FUE,
            ])
            ->assertOk()
            ->assertJsonPath('turno.estado', TurnoPdv::ESTADO_CERRADO);

        $asignado = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);
        TurnoPdvAtencion::factory()->create([
            'turno_id' => $asignado->id,
            'user_id' => User::factory()->create()->id,
            'inicio_at' => now(),
            'fin_at' => null,
        ]);

        $this->actingAs($this->recepcion)
            ->postJson(route('punto_venta.turnos.baja_cola', $asignado), [
                'version' => $asignado->version,
                'idempotency_key' => 'pdv:ui-bandeja:baja-asignado',
                'motivo' => MotivosBajaColaTurnoPdv::SE_FUE,
            ])
            ->assertUnprocessable();
    }

    public function test_sin_permiso_baja_cola_en_ui(): void
    {
        $soloVer = User::factory()->create();
        $soloVer->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $soloVer->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($soloVer, $this->sucursal->id);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);

        $this->actingAs($soloVer)
            ->get(route('punto_venta.turnos.recepcion'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permisos.ver', true)
                ->where('permisos.baja_cola', false));

        $this->actingAs($soloVer)
            ->postJson(route('punto_venta.turnos.baja_cola', $turno), [
                'version' => $turno->version,
                'idempotency_key' => 'pdv:ui-bandeja:sin-permiso',
                'motivo' => MotivosBajaColaTurnoPdv::SE_FUE,
            ])
            ->assertForbidden();
    }

    public function test_refresco_sin_mutaciones(): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);

        $this->actingAs($this->recepcion)->getJson(route('punto_venta.turnos.recepcion.datos'))->assertOk();
        $this->actingAs($this->recepcion)->getJson(route('punto_venta.turnos.recepcion.datos'))->assertOk();

        $turno->refresh();
        $this->assertSame(TurnoPdv::ESTADO_EN_COLA, $turno->estado);
        $this->assertSame(1, (int) $turno->version);
    }

    public function test_etiquetas_prioridad_en_payload(): void
    {
        TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'prioridad_vip' => true,
            'prioridad_diamante' => true,
        ]);

        $this->actingAs($this->recepcion)
            ->getJson(route('punto_venta.turnos.recepcion.datos'))
            ->assertOk()
            ->assertJsonPath('en_cola.0.prioridad_vip', true)
            ->assertJsonPath('en_cola.0.prioridad_diamante', true);
    }

    public function test_pagina_inertia_incluye_bandeja_y_catalogos(): void
    {
        $this->actingAs($this->recepcion)
            ->get(route('punto_venta.turnos.recepcion'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Turnos/Recepcion', false)
                ->where('permisos.ver', true)
                ->where('permisos.baja_cola', true)
                ->has('bandeja.en_cola')
                ->has('bandeja.asignados')
                ->has('catalogos.motivos_baja', 3));
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
