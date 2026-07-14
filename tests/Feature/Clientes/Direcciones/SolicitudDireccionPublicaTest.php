<?php

namespace Tests\Feature\Clientes\Direcciones;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\EnlaceDireccion;
use App\Models\SolicitudDireccion;
use App\Models\User;
use App\Services\Clientes\Direcciones\CrearSolicitudDireccionService;
use App\Services\Clientes\Direcciones\GenerarEnlaceDireccionService;
use App\Services\Clientes\Direcciones\AprobarSolicitudDireccionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SolicitudDireccionPublicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://gelianv.neobash.site',
            'app.form_public_url' => 'https://form.neobash.site',
            'app.allowed_hosts' => '',
        ]);
    }

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'numero_cliente' => '04950',
            'nombre' => 'Cliente Token',
            'lista_actual_id' => $this->lista()->id,
            'monto_venta_actual' => 0,
            'telefono' => '5511111111',
            'correo_electronico' => 'token@example.com',
        ]);
    }

    public function test_generar_y_validar_enlace_hashea_token(): void
    {
        $user = User::factory()->create();
        $cliente = $this->cliente();
        $resultado = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, [
            'usuario_id' => $user->id,
            'horas' => 24,
        ]);

        $this->assertNotSame($resultado['token'], $resultado['enlace']->token_hash);
        $this->assertSame(hash('sha256', $resultado['token']), $resultado['enlace']->token_hash);
        $this->assertSame($resultado['token'], $resultado['enlace']->codigo_publico);
        $this->assertTrue($resultado['enlace']->estaVigente());
        $this->assertSame(
            'https://form.neobash.site/direcciones-envio/'.$resultado['token'],
            $resultado['url']
        );
        $this->assertLessThanOrEqual(12, strlen($resultado['token']));
    }

    public function test_host_form_permite_formulario_y_bloquea_login(): void
    {
        $this->get('https://form.neobash.site/direcciones-envio')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Clientes/Direcciones/FormularioPublico', false));

        $this->get('https://form.neobash.site/login')
            ->assertNotFound();
    }

    public function test_host_interno_sigue_sirviendo_login(): void
    {
        $this->get('https://gelianv.neobash.site/login')
            ->assertOk();
    }

    public function test_accion_distinta_a_enlace_devuelve_validacion(): void
    {
        $cliente = $this->cliente();
        $resultado = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, [
            'horas' => 24,
            'accion' => SolicitudDireccion::ACCION_ADICIONAL,
        ]);

        try {
            app(CrearSolicitudDireccionService::class)->ejecutar([
                'token' => $resultado['token'],
                'accion_solicitada' => SolicitudDireccion::ACCION_PRIMERA,
                'nombre_declarado' => 'Ana Prueba',
                'telefono_declarado' => '5511111111',
                'datos_direccion' => [
                    'nombre_destinatario' => 'Ana Prueba',
                    'calle' => 'Calle Sol',
                    'colonia' => 'Centro',
                    'codigo_postal' => '06000',
                    'municipio' => 'CDMX',
                    'estado' => 'CDMX',
                    'pais' => 'México',
                ],
            ], '127.0.0.1');
            $this->fail('Se esperaba ValidationException por acción no permitida.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('accion_solicitada', $e->errors());
        }

        $this->get(route('direcciones.publicas.show', ['codigo' => $resultado['token']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clientes/Direcciones/FormularioPublico', false)
                ->where('accion_permitida', SolicitudDireccion::ACCION_ADICIONAL)
                ->has('acciones', 1));
    }

    public function test_solicitud_con_enlace_asociado(): void
    {
        $cliente = $this->cliente();
        $resultado = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, ['horas' => 24]);

        $solicitud = app(CrearSolicitudDireccionService::class)->ejecutar([
            'token' => $resultado['token'],
            'accion_solicitada' => SolicitudDireccion::ACCION_PRIMERA,
            'nombre_declarado' => 'Ana Prueba',
            'telefono_declarado' => '5511111111',
            'datos_direccion' => [
                'nombre_destinatario' => 'Ana Prueba',
                'calle' => 'Calle Sol',
                'colonia' => 'Centro',
                'codigo_postal' => '06000',
                'municipio' => 'CDMX',
                'estado' => 'CDMX',
                'pais' => 'México',
            ],
        ], '127.0.0.1');

        $this->assertSame(SolicitudDireccion::ESTADO_VERIFIED, $solicitud->estado);
        $this->assertSame($cliente->id, $solicitud->cliente_coincidente_id);
        $this->assertNotEmpty($solicitud->folio);
    }

    public function test_identidad_fallida_va_a_revision_sin_revelar(): void
    {
        $this->cliente();

        $solicitud = app(CrearSolicitudDireccionService::class)->ejecutar([
            'numero_cliente' => '04950',
            'accion_solicitada' => SolicitudDireccion::ACCION_PRIMERA,
            'nombre_declarado' => 'Intruso',
            'telefono_declarado' => '0000000000',
            'correo_declarado' => 'otro@example.com',
            'datos_direccion' => [
                'nombre_destinatario' => 'Intruso',
                'calle' => 'X',
                'colonia' => 'Y',
                'codigo_postal' => '06000',
                'municipio' => 'CDMX',
                'estado' => 'CDMX',
            ],
        ], '10.0.0.1');

        $this->assertSame(SolicitudDireccion::ESTADO_IDENTITY_REVIEW, $solicitud->estado);
    }

    public function test_aprobar_solicitud_crea_direccion_y_dual_write(): void
    {
        Permission::findOrCreate('clientes.direcciones.revisar_solicitudes', 'web');
        $user = User::factory()->create();
        $cliente = $this->cliente();
        $resultado = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente);

        $solicitud = app(CrearSolicitudDireccionService::class)->ejecutar([
            'token' => $resultado['token'],
            'accion_solicitada' => SolicitudDireccion::ACCION_PRIMERA,
            'nombre_declarado' => 'Ana',
            'telefono_declarado' => '5511111111',
            'datos_direccion' => [
                'nombre_destinatario' => 'Ana Dest',
                'telefono_destinatario' => '5588888888',
                'calle' => 'Calle Norte',
                'numero_exterior' => '12',
                'colonia' => 'Juárez',
                'codigo_postal' => '06600',
                'municipio' => 'Cuauhtémoc',
                'estado' => 'CDMX',
                'pais' => 'México',
            ],
        ]);

        $direccion = app(AprobarSolicitudDireccionService::class)->ejecutar($solicitud, [
            'usuario_id' => $user->id,
        ]);

        $this->assertTrue($direccion->es_principal);
        $this->assertSame('verified', $direccion->estado_verificacion);
        $cliente->refresh();
        $this->assertStringContainsString('Calle Norte', (string) $cliente->direccion_contacto);
        $this->assertSame('06600', $cliente->cp_contacto);
    }

    public function test_formulario_publico_renderiza(): void
    {
        $this->get(route('direcciones.publicas.form'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Clientes/Direcciones/FormularioPublico', false));
    }

    public function test_formulario_publico_con_codigo_corto(): void
    {
        $cliente = $this->cliente();
        $resultado = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, ['horas' => 24]);

        $this->get(route('direcciones.publicas.show', ['codigo' => $resultado['token']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clientes/Direcciones/FormularioPublico', false)
                ->where('enlace_valido', true)
                ->where('token', $resultado['token']));
    }
}
