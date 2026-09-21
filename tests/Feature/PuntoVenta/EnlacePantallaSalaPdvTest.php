<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\PdvPantallaSalaToken;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Pantallas\GestionarEnlacePantallaSalaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnlacePantallaSalaPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private User $autorizado;

    private User $sinPermiso;

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

        config([
            'app.url' => 'https://gelianv.neobash.site',
            'app.form_public_url' => 'https://form.neobash.site',
        ]);

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal TV']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Otra Sucursal']);
        $this->autorizado = $this->crearUsuarioConPermiso(true);
        $this->sinPermiso = $this->crearUsuarioConPermiso(false);
    }

    public function test_usuario_autorizado_obtiene_enlace_de_su_sucursal(): void
    {
        $response = $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->sucursal->id],
        );

        $response->assertOk()
            ->assertJsonPath('sucursal_id', $this->sucursal->id)
            ->assertJsonPath('enlace_activo', true)
            ->assertJsonStructure(['url']);

        $url = (string) $response->json('url');
        $this->assertStringStartsWith('https://gelianv.neobash.site/sala-turnos/t/', $url);
        $this->assertStringNotContainsString('form.neobash.site', $url);
        $this->assertDoesNotMatchRegularExpression(
            '#/sala-turnos/'.$this->sucursal->id.'(?:/|$)#',
            $url,
        );

        $this->assertDatabaseHas('pdv_pantalla_sala_tokens', [
            'sucursal_id' => $this->sucursal->id,
            'estado' => PdvPantallaSalaToken::ESTADO_ACTIVA,
        ]);
    }

    public function test_segunda_solicitud_devuelve_la_misma_url(): void
    {
        $primero = $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertOk();

        $segundo = $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertOk();

        $this->assertSame($primero->json('url'), $segundo->json('url'));
        $this->assertSame(1, PdvPantallaSalaToken::query()->where('sucursal_id', $this->sucursal->id)->count());
    }

    public function test_consultar_expone_url_permanente(): void
    {
        $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertOk();

        $this->actingAs($this->autorizado)->getJson(
            route('punto_venta.pantalla_sala.enlace.estado', ['sucursal_id' => $this->sucursal->id]),
        )
            ->assertOk()
            ->assertJsonPath('enlace_activo', true)
            ->assertJsonStructure(['url']);
    }

    public function test_usuario_sin_permiso_no_obtiene_enlace(): void
    {
        $this->actingAs($this->sinPermiso)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertForbidden();
    }

    public function test_no_puede_generar_enlace_para_sucursal_no_operable(): void
    {
        $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->otraSucursal->id],
        )->assertForbidden();
    }

    public function test_desactivar_mantiene_url_y_muestra_pantalla_inactiva(): void
    {
        $resultado = app(GestionarEnlacePantallaSalaPdvService::class)->obtenerEnlace(
            $this->autorizado,
            $this->sucursal->id,
            now(),
        );

        preg_match('#/sala-turnos/t/([A-Za-z0-9]+)#', (string) $resultado['url'], $coincidencias);
        $token = $coincidencias[1] ?? '';
        $this->assertNotSame('', $token);

        $this->actingAs($this->autorizado)->putJson(
            route('punto_venta.pantalla_sala.enlace.desactivar'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertOk()
            ->assertJsonPath('enlace_activo', false)
            ->assertJsonPath('url', $resultado['url']);

        $this->get(route('sala_turnos.publica.token.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PuntoVenta/Pantallas/SalaInactiva', false));

        $this->getJson(route('sala_turnos.publica.token.estado', ['token' => $token]))
            ->assertForbidden()
            ->assertJsonPath('activa', false);
    }

    public function test_activar_restaura_pantalla_publica(): void
    {
        $servicio = app(GestionarEnlacePantallaSalaPdvService::class);
        $resultado = $servicio->obtenerEnlace($this->autorizado, $this->sucursal->id, now());

        preg_match('#/sala-turnos/t/([A-Za-z0-9]+)#', (string) $resultado['url'], $coincidencias);
        $token = $coincidencias[1] ?? '';

        $servicio->desactivar($this->autorizado, $this->sucursal->id, now());

        $this->actingAs($this->autorizado)->putJson(
            route('punto_venta.pantalla_sala.enlace.activar'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertOk()
            ->assertJsonPath('enlace_activo', true)
            ->assertJsonPath('url', $resultado['url']);

        $this->crearLlamadoActivo('V-0500', 'Cliente TV', 'Ana Vendedora');

        $this->get(route('sala_turnos.publica.token.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Pantallas/Sala', false)
                ->where('sucursal_id', $this->sucursal->id));
    }

    public function test_desactivar_no_afecta_otra_sucursal(): void
    {
        $otroAutorizado = $this->crearUsuarioConPermiso(true, $this->otraSucursal);

        $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertOk();

        $this->actingAs($otroAutorizado)->postJson(
            route('punto_venta.pantalla_sala.enlace.obtener'),
            ['sucursal_id' => $this->otraSucursal->id],
        )->assertOk();

        $this->actingAs($this->autorizado)->putJson(
            route('punto_venta.pantalla_sala.enlace.desactivar'),
            ['sucursal_id' => $this->sucursal->id],
        )->assertOk();

        $this->assertDatabaseHas('pdv_pantalla_sala_tokens', [
            'sucursal_id' => $this->otraSucursal->id,
            'estado' => PdvPantallaSalaToken::ESTADO_ACTIVA,
        ]);
    }

    public function test_token_valido_carga_sala_y_estado(): void
    {
        $this->crearLlamadoActivo('V-0500', 'Cliente TV', 'Ana Vendedora');

        $resultado = app(GestionarEnlacePantallaSalaPdvService::class)->obtenerEnlace(
            $this->autorizado,
            $this->sucursal->id,
            now(),
        );

        preg_match('#/sala-turnos/t/([A-Za-z0-9]+)#', (string) $resultado['url'], $coincidencias);
        $token = $coincidencias[1] ?? '';
        $this->assertNotSame('', $token);

        $this->get(route('sala_turnos.publica.token.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Pantallas/Sala', false)
                ->where('sucursal_id', $this->sucursal->id)
                ->has('estado_inicial.llamados', 1));

        $this->getJson(route('sala_turnos.publica.token.estado', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('llamados.0.folio', 'V-0500')
            ->assertJsonPath('sucursal.id', $this->sucursal->id);
    }

    public function test_token_desconocido_falla_de_forma_segura(): void
    {
        $this->get(route('sala_turnos.publica.token.show', ['token' => 'tokeninvalido123456']))
            ->assertNotFound();

        $this->getJson(route('sala_turnos.publica.token.estado', ['token' => 'tokeninvalido123456']))
            ->assertNotFound();
    }

    public function test_token_no_permite_cambiar_sucursal_manipulando_url(): void
    {
        $resultado = app(GestionarEnlacePantallaSalaPdvService::class)->obtenerEnlace(
            $this->autorizado,
            $this->sucursal->id,
            now(),
        );

        preg_match('#/sala-turnos/t/([A-Za-z0-9]+)#', (string) $resultado['url'], $coincidencias);
        $token = $coincidencias[1] ?? '';

        $this->getJson(route('sala_turnos.publica.token.estado', ['token' => $token]))
            ->assertOk()
            ->assertJsonPath('sucursal.id', $this->sucursal->id)
            ->assertJsonPath('sucursal.id', fn ($id) => $id !== $this->otraSucursal->id);
    }

    public function test_pagina_acceso_requiere_permiso(): void
    {
        $this->actingAs($this->autorizado)->get(route('punto_venta.pantalla_sala.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PuntoVenta/Pantallas/Acceso', false));

        $this->actingAs($this->sinPermiso)->get(route('punto_venta.pantalla_sala.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function crearLlamadoActivo(
        string $folio,
        string $nombreLlamado,
        string $nombreVendedor,
        array $extra = [],
    ): TurnoPdv {
        $ventas = User::factory()->create(['name' => $nombreVendedor]);
        $turno = TurnoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'folio' => $folio,
            'snapshot_nombre_llamado' => $nombreLlamado,
        ], $extra));

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $ventas->id,
            'inicio_at' => now(),
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);

        return $turno->fresh();
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1'],
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    private function crearUsuarioConPermiso(bool $conPantallaSala, ?Sucursal $sucursal = null): User
    {
        $sucursal = $sucursal ?? $this->sucursal;
        $user = User::factory()->create();
        $permisos = [
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ];
        if ($conPantallaSala) {
            $permisos[] = PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR;
        }
        $user->givePermissionTo($permisos);
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $sucursal->id);

        return $user;
    }
}
