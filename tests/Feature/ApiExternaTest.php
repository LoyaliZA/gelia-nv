<?php

namespace Tests\Feature;

use App\Models\ApiAplicacion;
use App\Models\Cliente;
use App\Models\User;
use App\Services\ApiExterna\ApiPermisoService;
use Database\Seeders\ApiExternaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiExternaTest extends TestCase
{
    use RefreshDatabase;

    protected ApiAplicacion $aplicacion;
    protected string $clientSecret = 'test-secret-12345678901234567890123456789012';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApiExternaSeeder::class);

        Permission::findOrCreate('api_externa.gestionar', 'web');

        $this->aplicacion = ApiAplicacion::create([
            'nombre' => 'App Test',
            'client_id' => 'gel_testclient123456789012345',
            'client_secret' => $this->clientSecret,
            'activa' => true,
            'limite_por_minuto' => 60,
            'creado_por' => User::factory()->create()->id,
        ]);

        app(ApiPermisoService::class)->sincronizarPermisosAplicacion($this->aplicacion);
    }

    public function test_health_endpoint_responde_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', 'v1');
    }

    public function test_token_con_credenciales_validas(): void
    {
        $response = $this->postJson('/api/v1/auth/token', [
            'client_id' => $this->aplicacion->client_id,
            'client_secret' => $this->clientSecret,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_listar_clientes_autenticado(): void
    {
        $lista = \App\Models\CatalogoListaDescuento::create([
            'nombre' => 'Lista Test',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        Cliente::create([
            'numero_cliente' => 'C-001',
            'nombre' => 'Cliente Prueba',
            'lista_actual_id' => $lista->id,
        ]);

        Sanctum::actingAs($this->aplicacion, ['*']);

        $response = $this->getJson('/api/v1/clientes', ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_escritura_bloqueada_por_defecto(): void
    {
        Sanctum::actingAs($this->aplicacion, ['*']);

        $response = $this->postJson('/api/v1/clientes', [
            'numero_cliente' => 'C-999',
            'nombre' => 'Nuevo',
        ], ['Accept' => 'application/json']);

        $response->assertForbidden();
    }

    public function test_ruta_protegida_sin_accept_devuelve_json_no_login(): void
    {
        $response = $this->get('/api/v1/clientes');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'No autorizado.']);
    }

    public function test_admin_puede_acceder_panel(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('api_externa.gestionar');

        $response = $this->actingAs($user)->get(route('admin.api_externa.index'));

        $response->assertOk();
    }
}
