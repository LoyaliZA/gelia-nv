<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaSesionEvidencia;
use App\Models\ControlPedidos\PedidoBmaSesionEvidenciaFoto;
use App\Models\User;
use App\Services\ControlPedidos\SesionEvidenciaCedisService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SesionEvidenciaCedisTest extends TestCase
{
    use RefreshDatabase;

    private User $cedis;

    private PedidoBma $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        config([
            'app.url' => 'https://gelianv.neobash.site',
            'app.form_public_url' => 'https://form.neobash.site',
            'app.allowed_hosts' => '',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::findOrCreate('control_pedidos.cedis', 'web');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->cedis = User::factory()->create(['name' => 'Operador CEDIS']);
        $this->cedis->givePermissionTo('control_pedidos.cedis');

        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'EVID_PESAJE',
            'nombre_visual' => 'Pesaje',
            'color_hex' => '#EAB308',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS,
            'orden' => 3,
            'activo' => true,
        ]);

        $this->pedido = PedidoBma::create([
            'folio' => 'PED-EV-'.uniqid(),
            'folio_remision' => 'REM-EV-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->cedis->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
        ]);
    }

    public function test_generar_hashea_token_y_url_en_host_form(): void
    {
        $resultado = app(SesionEvidenciaCedisService::class)->generar($this->pedido, $this->cedis->id);

        $this->assertNotSame($resultado['token'], $resultado['sesion']->token_hash);
        $this->assertSame(hash('sha256', $resultado['token']), $resultado['sesion']->token_hash);
        $this->assertSame($resultado['token'], $resultado['sesion']->codigo_publico);
        $this->assertSame(
            'https://form.neobash.site/cedis-evidencia/'.$resultado['token'],
            $resultado['url']
        );
        $this->assertStringStartsWith('data:image/png;base64,', $resultado['qr_data_uri']);
        $this->assertSame(PedidoBmaSesionEvidencia::ESTADO_PENDIENTE, $resultado['sesion']->estado);
    }

    public function test_nuevo_qr_cancela_sesion_anterior(): void
    {
        $service = app(SesionEvidenciaCedisService::class);
        $primera = $service->generar($this->pedido, $this->cedis->id);
        $segunda = $service->generar($this->pedido, $this->cedis->id);

        $this->assertSame(
            PedidoBmaSesionEvidencia::ESTADO_CANCELADA,
            $primera['sesion']->fresh()->estado
        );
        $this->assertSame(PedidoBmaSesionEvidencia::ESTADO_PENDIENTE, $segunda['sesion']->estado);
    }

    public function test_primer_get_reclama_y_segundo_dispositivo_sin_cookie_falla(): void
    {
        $resultado = app(SesionEvidenciaCedisService::class)->generar($this->pedido, $this->cedis->id);
        $codigo = $resultado['token'];
        $cookieName = 'cedis_ev_'.$resultado['sesion']->id;

        $this->get('https://form.neobash.site/cedis-evidencia/'.$codigo)
            ->assertOk()
            ->assertCookie($cookieName)
            ->assertInertia(fn ($page) => $page
                ->component('ControlPedidos/Cedis/EvidenciaPublica/Show', false)
                ->where('error', null)
                ->where('estado', PedidoBmaSesionEvidencia::ESTADO_ACTIVA)
            );

        $this->assertSame(
            PedidoBmaSesionEvidencia::ESTADO_ACTIVA,
            $resultado['sesion']->fresh()->estado
        );

        $this->get('https://form.neobash.site/cedis-evidencia/'.$codigo)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ControlPedidos/Cedis/EvidenciaPublica/Show', false)
                ->where('error', 'Este QR ya fue usado en otro teléfono.')
            );
    }

    public function test_mismo_telefono_con_cookie_sigue_activo(): void
    {
        $service = app(SesionEvidenciaCedisService::class);
        $resultado = $service->generar($this->pedido, $this->cedis->id);
        $codigo = $resultado['token'];
        $sesion = $resultado['sesion'];

        $this->get('https://form.neobash.site/cedis-evidencia/'.$codigo)->assertOk();

        $this->withCookie($service->claimCookieName($sesion), $service->claimCookieValor($sesion))
            ->get('https://form.neobash.site/cedis-evidencia/'.$codigo)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('error', null)
                ->where('estado', PedidoBmaSesionEvidencia::ESTADO_ACTIVA)
            );
    }

    public function test_token_invalido_y_expirado(): void
    {
        $this->get('https://form.neobash.site/cedis-evidencia/NoExisteToken99')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('error', 'El código no es válido.')
            );

        $resultado = app(SesionEvidenciaCedisService::class)->generar($this->pedido, $this->cedis->id);
        $resultado['sesion']->update(['expira_en' => now()->subMinute()]);

        $this->get('https://form.neobash.site/cedis-evidencia/'.$resultado['token'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('error', 'La sesión expiró o fue cancelada.')
            );
    }

    public function test_cancelar_impide_reclamo(): void
    {
        $service = app(SesionEvidenciaCedisService::class);
        $resultado = $service->generar($this->pedido, $this->cedis->id);
        $service->cancelar($this->pedido, $this->cedis->id);

        $this->get('https://form.neobash.site/cedis-evidencia/'.$resultado['token'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('error', 'La sesión expiró o fue cancelada.')
            );
    }

    public function test_subir_foto_exige_cookie_y_promueve_al_pesaje(): void
    {
        Storage::fake('public');
        $service = app(SesionEvidenciaCedisService::class);
        $resultado = $service->generar($this->pedido, $this->cedis->id);
        $codigo = $resultado['token'];
        $sesion = $resultado['sesion'];
        $uuid = 'prod-uuid-1';

        $service->guardarSnapshot($this->pedido, [
            'productos' => [['client_uuid' => $uuid, 'sku' => 'SKU-1', 'descripcion' => 'Pieza']],
            'cajas' => [],
        ], $this->cedis->id);

        $this->withCookie($service->claimCookieName($sesion), $service->claimCookieValor($sesion))
            ->get('https://form.neobash.site/cedis-evidencia/'.$codigo)
            ->assertOk();

        $this->unencryptedCookies = [];
        $this->defaultCookies = [];

        $this->post('https://form.neobash.site/cedis-evidencia/'.$codigo.'/fotos', [
            'foto' => UploadedFile::fake()->image('evidencia.jpg'),
            'objetivo_tipo' => 'producto',
            'objetivo_uuid' => $uuid,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->withCookie($service->claimCookieName($sesion), $service->claimCookieValor($sesion))
            ->post('https://form.neobash.site/cedis-evidencia/'.$codigo.'/fotos', [
                'foto' => UploadedFile::fake()->image('evidencia.jpg'),
                'objetivo_tipo' => 'producto',
                'objetivo_uuid' => $uuid,
            ])
            ->assertOk()
            ->assertJsonPath('foto.objetivo_uuid', $uuid);

        $this->assertTrue(PedidoBmaSesionEvidenciaFoto::query()->where('sesion_id', $sesion->id)->exists());

        $orden = $service->promover($this->pedido, [$uuid => 77], [], 1);

        $this->assertSame(2, $orden);
        $this->assertSame(PedidoBmaSesionEvidencia::ESTADO_CERRADA, $sesion->fresh()->estado);
        $doc = PedidoBmaDocumento::query()->where('pedido_bma_id', $this->pedido->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame(PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION, $doc->tipo);
        $this->assertSame(PedidoBmaDocumento::RELACION_REVISION_PRODUCTO, $doc->relacion_tipo);
        $this->assertSame(77, (int) $doc->relacion_id);
    }

    public function test_crear_sesion_via_cedis_json(): void
    {
        $this->actingAs($this->cedis)
            ->postJson(route('control_pedidos.cedis.sesion_evidencia.store', $this->pedido))
            ->assertOk()
            ->assertJsonStructure(['sesion_id', 'url', 'qr_data_uri', 'expira_en', 'estado']);
    }

    public function test_permissions_policy_camera_self_en_evidencias(): void
    {
        $resultado = app(SesionEvidenciaCedisService::class)->generar($this->pedido, $this->cedis->id);

        $policy = $this->get('https://form.neobash.site/cedis-evidencia/'.$resultado['token'])
            ->assertOk()
            ->headers
            ->get('Permissions-Policy');

        $this->assertIsString($policy);
        $this->assertStringContainsString('camera=(self)', $policy);
    }

    public function test_assert_reclamo_token_ajeno_lanza(): void
    {
        $service = app(SesionEvidenciaCedisService::class);
        $resultado = $service->generar($this->pedido, $this->cedis->id);
        $service->reclamar($resultado['token'], '1.1.1.1', 'UA');

        $this->expectException(ValidationException::class);
        $service->assertReclamo($resultado['sesion']->fresh(), 'cookie-falsa');
    }
}
