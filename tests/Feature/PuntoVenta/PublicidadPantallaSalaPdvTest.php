<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicidadPantallaSalaPdvTest extends TestCase
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

        Storage::fake('public');

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal TV']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Otra']);
        $this->autorizado = $this->crearUsuario();
    }

    public function test_playlist_publica_mezcla_global_y_sucursal_vigente(): void
    {
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => null,
            'orden' => 1,
            'ruta' => 'pdv/pantalla-publicidad/global.jpg',
            'activa' => true,
        ]);
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'orden' => 2,
            'ruta' => 'pdv/pantalla-publicidad/local.jpg',
            'activa' => true,
        ]);
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->otraSucursal->id,
            'orden' => 3,
            'ruta' => 'pdv/pantalla-publicidad/otra.jpg',
            'activa' => true,
        ]);
        PdvPantallaPublicidad::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'orden' => 0,
            'ruta' => 'pdv/pantalla-publicidad/vencida.jpg',
            'activa' => true,
            'vigente_hasta' => now()->subDay(),
        ]);

        $response = $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->sucursal->id]))
            ->assertOk();

        $idsRuta = array_map(
            static fn (array $item): string => basename((string) $item['url']),
            $response->json('publicidad'),
        );

        $this->assertSame(['global.jpg', 'local.jpg'], $idsRuta);
    }

    public function test_usuario_autorizado_crea_publicidad_de_sucursal(): void
    {
        $archivo = UploadedFile::fake()->image('promo.jpg', 80, 80);

        $this->actingAs($this->autorizado)->post(
            route('punto_venta.pantalla_sala.publicidad.store'),
            [
                'sucursal_id' => $this->sucursal->id,
                'alcance' => 'sucursal',
                'ajuste' => 'cover',
                'orden' => 1,
                'duracion_seg' => 12,
                'archivo' => $archivo,
            ],
        )
            ->assertCreated()
            ->assertJsonPath('items.0.alcance', 'sucursal')
            ->assertJsonPath('items.0.duracion_seg', 12);

        $this->assertDatabaseHas('pdv_pantalla_publicidades', [
            'sucursal_id' => $this->sucursal->id,
            'tipo' => PdvPantallaPublicidad::TIPO_IMAGEN,
            'duracion_seg' => 12,
        ]);
    }

    public function test_publicidad_global_aparece_en_otra_sucursal(): void
    {
        $archivo = UploadedFile::fake()->image('global.png', 40, 40);

        $this->actingAs($this->autorizado)->post(
            route('punto_venta.pantalla_sala.publicidad.store'),
            [
                'sucursal_id' => $this->sucursal->id,
                'alcance' => 'global',
                'ajuste' => 'contain',
                'archivo' => $archivo,
            ],
        )->assertCreated();

        $this->assertDatabaseHas('pdv_pantalla_publicidades', [
            'sucursal_id' => null,
            'ajuste' => 'contain',
        ]);

        $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->otraSucursal->id]))
            ->assertOk()
            ->assertJsonCount(1, 'publicidad');
    }

    private function crearUsuario(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }
}
