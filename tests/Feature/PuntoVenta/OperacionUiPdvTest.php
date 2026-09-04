<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperacionUiPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $ventas;

    private User $gerencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal UI Operación']);
        $this->ventas = $this->crearVendedor('Vendedor UI');
        $this->gerencia = $this->crearGerencia('Gerente UI');
    }

    public function test_pagina_operacion_requiere_permiso_ver(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
        $sinPermiso->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($sinPermiso, $this->sucursal->id);

        $this->actingAs($sinPermiso)
            ->get(route('punto_venta.operacion.index'))
            ->assertForbidden();
    }

    public function test_pagina_operacion_renderiza_inertia_con_estado_extendido(): void
    {
        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());
        $this->configurarHorario();

        $response = $this->actingAs($this->ventas)->get(route('punto_venta.operacion.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PuntoVenta/Operacion/Index', false)
                ->has('estado.servidor_at')
                ->has('estado.intervalo.inicio_at')
                ->has('estado.horario_cierre.hora_cierre')
                ->has('estado.equipo')
                ->where('estado.jornada.estado', 'ABIERTA')
                ->where('estado.actividad', TipoIntervaloOperativoPdv::Disponible->value)
            );
    }

    public function test_datos_operacion_incluye_equipo_y_no_muta(): void
    {
        app(AbrirJornadaPdvService::class)->ejecutar($this->ventas, now());
        $this->configurarHorario();

        $response = $this->actingAs($this->ventas)->getJson(route('punto_venta.operacion.datos'));

        $response->assertOk()
            ->assertJsonPath('jornada.estado', 'ABIERTA')
            ->assertJsonStructure([
                'servidor_at',
                'intervalo' => ['tipo', 'inicio_at'],
                'horario_cierre' => ['configurado', 'hora_cierre', 'zona_horaria'],
                'equipo',
            ]);
    }

    public function test_gerencia_actualiza_horario_cierre_de_sucursal(): void
    {
        $this->configurarHorario();

        $response = $this->actingAs($this->gerencia)->putJson(
            route('punto_venta.operacion.configuracion.horario_cierre'),
            ['hora_cierre' => '20:30'],
        );

        $response->assertOk()
            ->assertJsonPath('horario_cierre.hora_cierre', '20:30')
            ->assertJsonPath('horario_cierre.es_override_sucursal', true);

        $config = new HorarioCierreOperacionPdvConfig;
        $efectivo = $config->resolverParaSucursal($this->sucursal->id);
        $this->assertSame('20:30', $efectivo['hora_cierre'] ?? null);
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

    private function crearVendedor(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR,
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }

    private function crearGerencia(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }

    private function configurarHorario(): void
    {
        $config = new HorarioCierreOperacionPdvConfig;
        $config->persistir($config->configuracionInicialPlaneada());
        Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);
    }
}
