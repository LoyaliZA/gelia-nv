<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\ConfiguracionUsuario;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PreferenciasAlertasPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

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

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
    }

    public function test_usuario_guarda_y_restaura_preferencias_pdv(): void
    {
        $payload = [
            'canales' => [
                'sonido' => false,
                'voz' => true,
                'web_push' => false,
            ],
            'tono_id' => 'default',
        ];

        $response = $this->actingAs($this->usuario)->putJson(
            route('punto_venta.preferencias_alertas'),
            $payload,
        );

        $response->assertOk()
            ->assertJsonPath('pdv_alertas_prefs.canales.sonido', false)
            ->assertJsonPath('pdv_alertas_prefs.canales.voz', true)
            ->assertJsonPath('pdv_alertas_prefs.canales.web_push', false)
            ->assertJsonPath('pdv_alertas_prefs.tono_id', 'default');

        $config = ConfiguracionUsuario::where('user_id', $this->usuario->id)->first();
        $this->assertNotNull($config);
        $this->assertSame(false, $config->tema_visual['pdv_alertas_prefs']['canales']['sonido']);
        $this->assertSame(true, $config->tema_visual['pdv_alertas_prefs']['canales']['voz']);
    }

    public function test_normaliza_tono_invalido_y_canales_booleanos(): void
    {
        $response = $this->actingAs($this->usuario)->putJson(
            route('punto_venta.preferencias_alertas'),
            [
                'canales' => [
                    'sonido' => 1,
                    'voz' => 0,
                    'web_push' => true,
                ],
                'tono_id' => 'tono-inexistente',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('pdv_alertas_prefs.canales.sonido', true)
            ->assertJsonPath('pdv_alertas_prefs.canales.voz', false)
            ->assertJsonPath('pdv_alertas_prefs.canales.web_push', true)
            ->assertJsonPath('pdv_alertas_prefs.tono_id', 'default');
    }

    public function test_requiere_modulo_pdv(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '0']
        );

        $this->actingAs($this->usuario)
            ->putJson(route('punto_venta.preferencias_alertas'), [
                'canales' => [
                    'sonido' => true,
                    'voz' => true,
                    'web_push' => true,
                ],
                'tono_id' => 'default',
            ])
            ->assertNotFound();
    }

    public function test_requiere_autenticacion(): void
    {
        $this->putJson(route('punto_venta.preferencias_alertas'), [
            'canales' => [
                'sonido' => true,
                'voz' => true,
                'web_push' => true,
            ],
            'tono_id' => 'default',
        ])->assertUnauthorized();
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
