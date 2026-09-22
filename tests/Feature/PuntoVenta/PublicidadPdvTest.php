<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\PublicidadPdvActualizada;
use App\Models\ConfiguracionSistema;
use App\Models\Medios\Medio;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicidadPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private User $autorizado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1'],
        );
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal TV']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Otra']);
        $this->autorizado = $this->crearUsuario();
    }

    public function test_pagina_publicidad_usa_componente_propio(): void
    {
        $this->actingAs($this->autorizado)
            ->get(route('punto_venta.publicidad.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('PuntoVenta/Publicidad/Index', false));
    }

    public function test_acceso_sala_ya_no_expone_crud_de_publicidad(): void
    {
        $this->autorizado->givePermissionTo(PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR);

        $this->actingAs($this->autorizado)
            ->get(route('punto_venta.pantalla_sala.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('PuntoVenta/Pantallas/Acceso', false));
    }

    public function test_playlist_publica_mezcla_global_y_sucursal_vigente(): void
    {
        $global = Medio::factory()->create(['nombre_original' => 'global.jpg']);
        $local = Medio::factory()->create(['nombre_original' => 'local.jpg']);
        $otra = Medio::factory()->create();
        $vencida = Medio::factory()->create();

        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => null,
            'medio_id' => $global->id,
            'orden' => 1,
            'ruta' => $global->object_key,
            'activa' => true,
        ]);
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $local->id,
            'orden' => 2,
            'ruta' => $local->object_key,
            'activa' => true,
        ]);
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->otraSucursal->id,
            'medio_id' => $otra->id,
            'orden' => 3,
            'ruta' => $otra->object_key,
            'activa' => true,
        ]);
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $vencida->id,
            'orden' => 0,
            'ruta' => $vencida->object_key,
            'activa' => true,
            'vigente_hasta' => now()->subDay(),
        ]);

        $response = $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->sucursal->id]))
            ->assertOk();

        $ids = collect($response->json('publicidad'))->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertSame(
            PdvPantallaPublicidad::query()->whereIn('orden', [1, 2])->orderBy('orden')->pluck('id')->all(),
            $ids,
        );
    }

    public function test_carga_firmada_y_alta_de_publicidad(): void
    {
        Event::fake([PublicidadPdvActualizada::class]);

        $init = $this->actingAs($this->autorizado)->postJson(route('medios.cargas.iniciar'), [
            'filename' => 'promo.jpg',
            'size' => 12000,
            'mime_type' => 'image/jpeg',
            'proposito' => 'pdv_publicidad',
        ])->assertCreated()->json();

        $this->assertSame('single', $init['upload_type']);
        $this->assertNotEmpty($init['upload_url']);

        $medio = $this->actingAs($this->autorizado)->postJson(
            route('medios.cargas.completar', $init['media_upload_id']),
        )->assertOk()->json();

        $this->actingAs($this->autorizado)->postJson(route('punto_venta.publicidad.store'), [
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $medio['media_id'],
            'alcance' => 'sucursal',
            'ajuste' => 'cover',
        ])
            ->assertCreated()
            ->assertJsonPath('items.0.alcance', 'sucursal')
            ->assertJsonPath('items.0.duracion_seg', 10)
            ->assertJsonPath('items.0.estado', 'activa');

        Event::assertDispatched(PublicidadPdvActualizada::class);
    }

    public function test_publicidad_global_aparece_en_otra_sucursal(): void
    {
        $medio = Medio::factory()->create();
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => null,
            'medio_id' => $medio->id,
            'ruta' => $medio->object_key,
            'ajuste' => 'contain',
            'activa' => true,
        ]);

        $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->otraSucursal->id]))
            ->assertOk()
            ->assertJsonCount(1, 'publicidad');
    }

    public function test_carga_multipart_cuando_supera_umbral(): void
    {
        config(['medios.umbral_multipart_bytes' => 1000]);

        $init = $this->actingAs($this->autorizado)->postJson(route('medios.cargas.iniciar'), [
            'filename' => 'promo.mp4',
            'size' => 5000,
            'mime_type' => 'video/mp4',
            'proposito' => 'pdv_publicidad',
        ])->assertCreated()->json();

        $this->assertSame('multipart', $init['upload_type']);
        $this->assertArrayHasKey('total_parts', $init);

        $this->actingAs($this->autorizado)->postJson(
            route('medios.cargas.partes', $init['media_upload_id']),
            ['part_numbers' => [1]],
        )->assertOk()->assertJsonPath('parts.0.part_number', 1);

        $this->actingAs($this->autorizado)->postJson(
            route('medios.cargas.completar', $init['media_upload_id']),
            ['parts' => [['part_number' => 1, 'etag' => '"abc"']], 'duration_seconds' => 12],
        )->assertOk()->assertJsonPath('tipo', 'video')->assertJsonPath('duracion_seg', 12);
    }

    public function test_ordenar_persiste_prioridad(): void
    {
        $a = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => Medio::factory(),
            'orden' => 1,
        ]);
        $b = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => Medio::factory(),
            'orden' => 2,
        ]);

        $this->actingAs($this->autorizado)->patchJson(route('punto_venta.publicidad.ordenar'), [
            'sucursal_id' => $this->sucursal->id,
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(1, $b->fresh()->orden);
        $this->assertSame(2, $a->fresh()->orden);
    }

    public function test_sin_permiso_crear_no_inicia_carga(): void
    {
        $soloVer = $this->crearUsuario([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_VER,
        ]);

        $this->actingAs($soloVer)->postJson(route('medios.cargas.iniciar'), [
            'filename' => 'promo.jpg',
            'size' => 100,
            'mime_type' => 'image/jpeg',
            'proposito' => 'pdv_publicidad',
        ])->assertForbidden();
    }

    /**
     * @param  list<string>|null  $permisos
     */
    private function crearUsuario(?array $permisos = null): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permisos ?? [
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_VER,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_CREAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_EDITAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_ELIMINAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_ORDENAR,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }
}
