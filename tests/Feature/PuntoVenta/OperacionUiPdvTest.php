<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
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
        $this->activarVendedor($this->ventas);
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
                ->where('estado.estado_vendedor', 'disponible')
            );
    }

    public function test_datos_operacion_incluye_equipo_y_no_muta(): void
    {
        $this->activarVendedor($this->ventas);
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

        $config = app(HorarioCierreOperacionPdvConfig::class);
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
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
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
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }

    public function test_gerencia_recibe_permiso_equipo_gestionar_sin_jornada_propia(): void
    {
        $gerenteConVer = User::factory()->create(['name' => 'Gerente con ver']);
        $gerenteConVer->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR,
        ]);
        $gerenteConVer->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($gerenteConVer, $this->sucursal->id);

        $response = $this->actingAs($gerenteConVer)->get(route('punto_venta.operacion.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permisos.equipo_gestionar', true)
                ->where('permisos.jornada_abrir', false)
                ->where('capacidades.equipo_gestionar', true)
                ->where('capacidades.atender', false)
                ->where('capacidades.aparece_como_vendedor', false)
            );
    }

    public function test_panel_vendedores_requiere_equipo_ver(): void
    {
        $this->actingAs($this->ventas)
            ->get(route('punto_venta.operacion.vendedores.index'))
            ->assertForbidden();
    }

    public function test_vendedor_recibe_capacidades_explicitas_en_operacion(): void
    {
        $response = $this->actingAs($this->ventas)->get(route('punto_venta.operacion.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capacidades.atender', true)
                ->where('capacidades.aparece_como_vendedor', true)
                ->where('capacidades.equipo_gestionar', false)
                ->where('capacidades.equipo_ver', false)
            );
    }

    public function test_vendedor_recibe_estado_vendedor_en_operacion(): void
    {
        $response = $this->actingAs($this->ventas)->get(route('punto_venta.operacion.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('estado.estado_vendedor', 'no_activado')
            );
    }

    public function test_vendedor_con_permisos_legados_de_jornada_conserva_estado_sin_gestion_propia(): void
    {
        $vendedorLegado = User::factory()->create(['name' => 'Vendedor legado']);
        $vendedorLegado->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR,
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
        ]);
        $vendedorLegado->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($vendedorLegado, $this->sucursal->id);

        $this->actingAs($vendedorLegado)
            ->get(route('punto_venta.operacion.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permisos.jornada_abrir', true)
                ->where('permisos.pausa', true)
                ->where('estado.estado_vendedor', 'no_activado')
                ->where('capacidades.atender', true)
            );
    }

    private function configurarHorario(): void
    {
        $config = app(HorarioCierreOperacionPdvConfig::class);
        $config->persistir($config->configuracionInicialPlaneada());
        Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);
    }

    private function activarVendedor(User $vendedor): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerencia, $vendedor, now());
    }
}
