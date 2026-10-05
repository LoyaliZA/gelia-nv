<?php

namespace Tests\Feature\PuntoVenta;

use App\Contracts\Medios\AlmacenObjetosMedio;
use App\Events\PuntoVenta\PublicidadPdvActualizada;
use App\Jobs\Medios\MaterializarMedioLocalJob;
use App\Models\ConfiguracionSistema;
use App\Models\Medios\Medio;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Medios\AlmacenObjetosMedioFake;
use App\Services\Medios\MaterializarMedioLocalService;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Pantallas\ConsultaEstadoSalaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('public');
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

    public function test_volumen_de_videos_se_guarda_por_sucursal_y_llega_a_sala(): void
    {
        Event::fake([PublicidadPdvActualizada::class]);

        $this->actingAs($this->autorizado)
            ->putJson(route('punto_venta.publicidad.volumen'), [
                'sucursal_id' => $this->sucursal->id,
                'volumen' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('volumen', 40);

        $payload = app(ConsultaEstadoSalaPdvService::class)->payload($this->sucursal->id, now());
        $this->assertSame(0.4, $payload['volumen_publicidad']);
        Event::assertDispatched(PublicidadPdvActualizada::class);
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
        )->assertOk()->assertJsonPath('estado', Medio::ESTADO_READY)->json();

        $guardado = Medio::query()->findOrFail($medio['media_id']);
        $this->assertNotNull($guardado->ruta_local);
        Storage::disk('public')->assertExists($guardado->ruta_local);
        $this->assertFalse(app(AlmacenObjetosMedio::class)->existe($guardado->object_key));

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
        )->assertOk()
            ->assertJsonPath('tipo', 'video')
            ->assertJsonPath('duracion_seg', 12)
            ->assertJsonPath('estado', Medio::ESTADO_READY);
    }

    public function test_job_copia_correcta_borra_el_objeto(): void
    {
        $medio = Medio::factory()->create([
            'estado' => Medio::ESTADO_PROCESSING,
            'ruta_local' => null,
            'tamano_bytes' => 32,
            'object_key' => 'advertising/media/job/ok.jpg',
        ]);
        $almacen = app(AlmacenObjetosMedio::class);
        $this->assertInstanceOf(AlmacenObjetosMedioFake::class, $almacen);
        $almacen->registrarTamano($medio->object_key, 32);

        (new MaterializarMedioLocalJob($medio->id))->handle(app(MaterializarMedioLocalService::class));

        $medio->refresh();
        $this->assertSame(Medio::ESTADO_READY, $medio->estado);
        $this->assertNotNull($medio->ruta_local);
        Storage::disk('public')->assertExists($medio->ruta_local);
        $this->assertFalse($almacen->existe($medio->object_key));
    }

    public function test_job_tamano_distinto_deja_failed_y_conserva_el_objeto(): void
    {
        $medio = Medio::factory()->create([
            'estado' => Medio::ESTADO_PROCESSING,
            'ruta_local' => null,
            'tamano_bytes' => 64,
            'object_key' => 'advertising/media/job/mal.jpg',
        ]);
        $almacen = app(AlmacenObjetosMedio::class);
        $this->assertInstanceOf(AlmacenObjetosMedioFake::class, $almacen);
        $almacen->registrarTamano($medio->object_key, 10);

        (new MaterializarMedioLocalJob($medio->id))->handle(app(MaterializarMedioLocalService::class));

        $medio->refresh();
        $this->assertSame(Medio::ESTADO_FAILED, $medio->estado);
        $this->assertNull($medio->ruta_local);
        $this->assertTrue($almacen->existe($medio->object_key));
        Storage::disk('public')->assertMissing('pdv/pantalla-publicidad/'.$medio->uuid.'.jpg');
    }

    public function test_eliminar_ultimo_anuncio_borra_el_archivo_local(): void
    {
        $medio = Medio::factory()->create([
            'ruta_local' => 'pdv/pantalla-publicidad/quitar.jpg',
        ]);
        Storage::disk('public')->put($medio->ruta_local, 'demo');
        $item = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $medio->id,
            'ruta' => $medio->ruta_local,
        ]);

        $this->actingAs($this->autorizado)->deleteJson(
            route('punto_venta.publicidad.destroy', $item->id).'?sucursal_id='.$this->sucursal->id,
        )->assertOk();

        Storage::disk('public')->assertMissing($medio->ruta_local);
        $this->assertSame(Medio::ESTADO_DELETED, $medio->fresh()->estado);
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

    public function test_estados_de_vigencia_no_llegan_a_la_tv_salvo_la_activa(): void
    {
        $programada = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => Medio::factory(),
            'activa' => true,
            'vigente_desde' => now()->addDay(),
        ]);
        $activa = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => Medio::factory(),
            'activa' => true,
            'vigente_desde' => now()->subHour(),
            'vigente_hasta' => now()->addHour(),
        ]);
        $expirada = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => Medio::factory(),
            'activa' => true,
            'vigente_hasta' => now()->subMinute(),
        ]);
        $deshabilitada = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => Medio::factory(),
            'activa' => false,
            'vigente_desde' => now()->subDay(),
            'vigente_hasta' => now()->addDay(),
        ]);

        $items = collect($this->actingAs($this->autorizado)->getJson(
            route('punto_venta.publicidad.items', ['sucursal_id' => $this->sucursal->id]),
        )->assertOk()->json('items'));

        $this->assertSame('programada', $items->firstWhere('id', $programada->id)['estado']);
        $this->assertSame('activa', $items->firstWhere('id', $activa->id)['estado']);
        $this->assertSame('expirada', $items->firstWhere('id', $expirada->id)['estado']);
        $this->assertSame('deshabilitada', $items->firstWhere('id', $deshabilitada->id)['estado']);

        $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->sucursal->id]))
            ->assertOk()
            ->assertJsonCount(1, 'publicidad')
            ->assertJsonPath('publicidad.0.id', $activa->id);
    }

    public function test_orden_rechaza_ids_duplicados_o_incompletos(): void
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
            'ids' => [$a->id, $a->id],
        ])->assertUnprocessable();

        $this->actingAs($this->autorizado)->patchJson(route('punto_venta.publicidad.ordenar'), [
            'sucursal_id' => $this->sucursal->id,
            'ids' => [$a->id],
        ])->assertUnprocessable();

        $this->assertSame(1, $a->fresh()->orden);
        $this->assertSame(2, $b->fresh()->orden);
    }

    public function test_duracion_de_imagen_no_acepta_mas_de_300_segundos(): void
    {
        $medio = Medio::factory()->create();
        $item = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $medio->id,
            'tipo' => PdvPantallaPublicidad::TIPO_IMAGEN,
            'duracion_seg' => 10,
        ]);

        $this->actingAs($this->autorizado)->patchJson(route('punto_venta.publicidad.update', $item->id), [
            'sucursal_id' => $this->sucursal->id,
            'duracion_seg' => 301,
        ])->assertUnprocessable();

        $this->assertSame(10, $item->fresh()->duracion_seg);
    }

    public function test_depuracion_respeta_conservacion_referencias_y_es_idempotente(): void
    {
        Event::fake([PublicidadPdvActualizada::class]);
        $compartido = Medio::factory()->create([
            'ruta_local' => 'pdv/pantalla-publicidad/compartido.jpg',
        ]);
        Storage::disk('public')->put($compartido->ruta_local, 'demo');
        $unico = Medio::factory()->create([
            'ruta_local' => 'pdv/pantalla-publicidad/unico.jpg',
        ]);
        Storage::disk('public')->put($unico->ruta_local, 'demo');

        $conservar = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $compartido->id,
            'ruta' => $compartido->ruta_local,
            'vigente_hasta' => now()->subDays(2),
            'eliminar_automaticamente' => true,
            'eliminar_programado_at' => now()->addDays(28),
        ]);
        $listaParaBorrar = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $compartido->id,
            'ruta' => $compartido->ruta_local,
            'vigente_hasta' => now()->subDays(40),
            'eliminar_automaticamente' => true,
            'eliminar_programado_at' => now()->subMinute(),
        ]);
        $sinCompartir = PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'medio_id' => $unico->id,
            'ruta' => $unico->ruta_local,
            'vigente_hasta' => now()->subDays(40),
            'eliminar_automaticamente' => true,
            'eliminar_programado_at' => now()->subMinute(),
        ]);

        $this->artisan('pdv:depurar-publicidad')->assertSuccessful();

        $this->assertNotNull($conservar->fresh());
        $this->assertNull($listaParaBorrar->fresh());
        $this->assertNull($sinCompartir->fresh());
        Storage::disk('public')->assertExists($compartido->ruta_local);
        Storage::disk('public')->assertMissing($unico->ruta_local);
        $this->assertSame(Medio::ESTADO_DELETED, $unico->fresh()->estado);
        $this->assertNotSame(Medio::ESTADO_DELETED, $compartido->fresh()->estado);
        Event::assertDispatched(PublicidadPdvActualizada::class);

        $this->artisan('pdv:depurar-publicidad')->assertSuccessful();
        $this->assertNotNull($conservar->fresh());
        Storage::disk('public')->assertExists($compartido->ruta_local);
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
