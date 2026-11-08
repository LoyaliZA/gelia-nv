<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckApiResourcePermission;
use App\Models\ApiAplicacion;
use App\Models\ApiAplicacionCampo;
use App\Models\ApiAplicacionPermiso;
use App\Models\ApiCampoRecurso;
use App\Models\ApiRecurso;
use App\Models\Cliente;
use App\Models\User;
use App\Services\ApiExterna\ApiPermisoService;
use Database\Seeders\ApiExternaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_app_nueva_no_recibe_campos_fiscales_sensibles(): void
    {
        $lista = \App\Models\CatalogoListaDescuento::create([
            'nombre' => 'Lista Test',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        Cliente::create([
            'numero_cliente' => 'C-FIS-001',
            'nombre' => 'Cliente Fiscal',
            'rfc' => 'XAXX010101000',
            'nombre_razon_social' => 'Razon SA',
            'lista_actual_id' => $lista->id,
        ]);

        Sanctum::actingAs($this->aplicacion, ['*']);

        $response = $this->getJson('/api/v1/clientes/C-FIS-001', ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.numero_cliente', 'C-FIS-001')
            ->assertJsonPath('data.nombre', 'Cliente Fiscal')
            ->assertJsonMissingPath('data.rfc')
            ->assertJsonMissingPath('data.nombre_razon_social')
            ->assertJsonMissingPath('data.regimen_fiscal');
    }

    public function test_app_con_override_sensible_recibe_rfc(): void
    {
        $lista = \App\Models\CatalogoListaDescuento::create([
            'nombre' => 'Lista Test',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        Cliente::create([
            'numero_cliente' => 'C-FIS-002',
            'nombre' => 'Cliente Con Rfc',
            'rfc' => 'XAXX010101000',
            'lista_actual_id' => $lista->id,
        ]);

        $campoRfc = \App\Models\ApiCampoRecurso::where('slug', 'rfc')->firstOrFail();
        \App\Models\ApiAplicacionCampo::updateOrCreate(
            [
                'api_aplicacion_id' => $this->aplicacion->id,
                'api_campo_recurso_id' => $campoRfc->id,
            ],
            ['habilitado' => true]
        );

        Sanctum::actingAs($this->aplicacion, ['*']);

        $response = $this->getJson('/api/v1/clientes/C-FIS-002', ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.rfc', 'XAXX010101000');
    }

    public function test_catalogo_nuevo_habilita_lectura_sin_cambiar_permisos_ni_secreto(): void
    {
        $hashSecreto = $this->aplicacion->fresh()->client_secret;

        $permisoClientes = ApiAplicacionPermiso::query()
            ->where('api_aplicacion_id', $this->aplicacion->id)
            ->whereHas('recurso', fn ($query) => $query->where('slug', 'clientes'))
            ->firstOrFail();
        $permisoClientes->update(['puede_leer' => false]);

        $recurso = ApiRecurso::create([
            'slug' => 'productos',
            'nombre' => 'Productos',
            'activo' => true,
            'lectura_habilitada' => true,
            'escritura_habilitada' => true,
        ]);

        $campo = ApiCampoRecurso::create([
            'api_recurso_id' => $recurso->id,
            'slug' => 'sku',
            'etiqueta' => 'SKU',
            'es_sensible' => false,
            'habilitado_global' => true,
            'orden' => 1,
        ]);

        $request = Request::create('/api/v1/productos', 'GET');
        $request->setUserResolver(fn () => $this->aplicacion);
        $middleware = app(CheckApiResourcePermission::class);

        $lectura = $middleware->handle(
            $request,
            fn () => response()->json(['ok' => true]),
            'productos',
            'read'
        );
        $escritura = $middleware->handle(
            Request::create('/api/v1/productos', 'POST')->setUserResolver(fn () => $this->aplicacion),
            fn () => response()->json(['ok' => true]),
            'productos',
            'write'
        );

        $this->assertSame(200, $lectura->getStatusCode());
        $this->assertSame(403, $escritura->getStatusCode());

        $permisoNuevo = ApiAplicacionPermiso::query()
            ->where('api_aplicacion_id', $this->aplicacion->id)
            ->where('api_recurso_id', $recurso->id)
            ->firstOrFail();
        $this->assertTrue($permisoNuevo->puede_leer);
        $this->assertFalse($permisoNuevo->puede_escribir);
        $this->assertTrue($permisoNuevo->activo);

        $permisoClientes->refresh();
        $this->assertFalse($permisoClientes->puede_leer);

        $filaCampo = ApiAplicacionCampo::query()
            ->where('api_aplicacion_id', $this->aplicacion->id)
            ->where('api_campo_recurso_id', $campo->id)
            ->firstOrFail();
        $this->assertTrue($filaCampo->habilitado);

        $this->aplicacion->refresh();
        $this->assertSame($hashSecreto, $this->aplicacion->client_secret);
        $this->assertSame('gel_testclient123456789012345', $this->aplicacion->client_id);
    }
}
