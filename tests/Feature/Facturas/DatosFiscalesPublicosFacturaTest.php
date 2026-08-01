<?php

namespace Tests\Feature\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Notifications\AlertaFactura;
use App\Services\Facturas\AplicarDatosFiscalesPublicosDesdeEnlaceService;
use App\Services\Facturas\CrearSolicitudFacturaService;
use App\Services\Facturas\GenerarEnlaceDatosFiscalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatosFiscalesPublicosFacturaTest extends TestCase
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

        foreach ([
            'Pendiente',
            'Respondida',
            'Verificada',
            'Incorrecta',
            'Cancelada',
            'Borrador',
        ] as $nombre) {
            CatalogoEstadoSolicitud::updateOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $nombre, 'activo' => true]
            );
        }
        CatalogoEstadoSolicitud::reiniciarCache();

        app(\App\Services\Facturas\ImportarCatalogosFiscalesService::class)->ejecutar();

        foreach (['facturas.crear', 'facturas.responder', 'facturas.verificar', 'facturas.ver_listado'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        Role::findOrCreate('Administrador', 'web');
    }

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    private function departamento(): Departamento
    {
        return Departamento::firstOrCreate(
            ['nombre' => 'Ventas Test'],
            ['activo' => true, 'logo_key_claro' => 'aromas_logo_negro', 'logo_key_oscuro' => 'aromas_logo_blanco']
        );
    }

    public function test_formulario_publico_recibe_branding_del_departamento(): void
    {
        $vendedor = $this->vendedor();
        $cliente = $this->cliente();
        $depto = $this->departamento();
        $depto->update([
            'logo_key_claro' => 'bellaroma_logo_negro',
            'logo_key_oscuro' => 'bellaroma_logo_blanco',
        ]);

        $creado = app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'razon_social' => 'EMPRESA PRUEBA SA DE CV',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $enlace = EnlaceDatosFiscales::query()->firstOrFail();
        $enlace->solicitud->update(['departamento_id' => $depto->id]);

        $this->get('https://form.neobash.site/datos-fiscales/'.$enlace->codigo_publico)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clientes/DatosFiscales/FormularioPublico', false)
                ->where('branding.key_claro', 'bellaroma_logo_negro')
                ->where('branding.key_oscuro', 'bellaroma_logo_blanco')
                ->where('branding.departamento', 'Ventas Test')
                ->missing('accion_permitida')
                ->where('cliente.nombre_enmascarado', fn ($v) => is_string($v) && ! str_contains(strtolower($v), 'rfc'))
                ->has('cliente.nombre_enmascarado')
                ->has('cliente.numero_enmascarado')
                ->missing('cliente.rfc')
                ->missing('cliente.nombre_razon_social')
                ->missing('cliente.correo_electronico')
            );
    }

    private function vendedor(): User
    {
        $user = User::factory()->create(['name' => 'Vendedora Factura']);
        $user->givePermissionTo(['facturas.crear', 'facturas.ver_listado']);
        $depto = $this->departamento();
        $user->departamentos()->syncWithoutDetaching([$depto->id]);

        return $user;
    }

    private function encargada(): User
    {
        $user = User::factory()->create(['name' => 'Encargada Factura']);
        $user->givePermissionTo(['facturas.responder', 'facturas.verificar', 'facturas.ver_listado']);
        $depto = $this->departamento();
        $user->departamentos()->syncWithoutDetaching([$depto->id]);

        return $user;
    }

    private function cliente(array $extra = []): Cliente
    {
        return Cliente::create(array_merge([
            'numero_cliente' => '04950',
            'nombre' => 'Cliente Token',
            'lista_actual_id' => $this->lista()->id,
            'monto_venta_actual' => 0,
            'telefono' => '5511111111',
            'correo_electronico' => 'token@example.com',
        ], $extra));
    }

    private function datosFiscalesCompletos(): array
    {
        return [
            'rfc' => 'XAXX010101000',
            'codigo_postal' => '06000',
            'regimen_fiscal' => '626',
            'correo_electronico' => 'fiscal@example.com',
            'uso_factura' => 'G03',
            'nombre_razon_social' => 'EMPRESA PRUEBA SA DE CV',
            'telefono' => '5512345678',
        ];
    }

    public function test_generar_enlace_fiscal_usa_form_public_url(): void
    {
        $vendedor = $this->vendedor();
        $cliente = $this->cliente();

        $resultado = app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'razon_social' => 'EMPRESA PRUEBA SA DE CV',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $this->assertSame('Borrador', $resultado['solicitud']->estado->nombre);
        $this->assertNotNull($resultado['enlace_url']);
        $this->assertStringStartsWith('https://form.neobash.site/datos-fiscales/', $resultado['enlace_url']);
        $this->assertDatabaseCount('enlaces_datos_fiscales', 1);
    }

    public function test_borrador_no_notifica_encargada(): void
    {
        Notification::fake();
        $vendedor = $this->vendedor();
        $this->encargada();
        $cliente = $this->cliente();

        app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'razon_social' => 'EMPRESA PRUEBA SA DE CV',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        Notification::assertNothingSent();
    }

    public function test_formulario_cliente_actualiza_cliente_y_sigue_en_borrador(): void
    {
        Notification::fake();
        $vendedor = $this->vendedor();
        $encargada = $this->encargada();
        $cliente = $this->cliente();

        $creado = app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'razon_social' => 'TEMP',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $enlace = EnlaceDatosFiscales::query()->firstOrFail();
        $token = $enlace->codigo_publico;

        $this->get(route('datos_fiscales.publicas.show', ['codigo' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clientes/DatosFiscales/FormularioPublico', false)
                ->has('campos', 7));

        app(AplicarDatosFiscalesPublicosDesdeEnlaceService::class)->ejecutar(
            $token,
            $this->datosFiscalesCompletos()
        );

        $solicitud = $creado['solicitud']->fresh(['estado', 'cliente']);
        $this->assertSame('Borrador', $solicitud->estado->nombre);
        $this->assertNotNull($solicitud->formulario_respondido_at);
        $this->assertSame('XAXX010101000', $solicitud->datos_fiscales['rfc']);
        $this->assertSame('EMPRESA PRUEBA SA DE CV', $solicitud->razon_social);

        $cliente->refresh();
        $this->assertSame('XAXX010101000', $cliente->rfc);
        $this->assertSame('EMPRESA PRUEBA SA DE CV', $cliente->nombre_razon_social);

        Notification::assertSentTo($vendedor, AlertaFactura::class, fn ($n) => $n->tipoAlerta === 'formulario_respondido');
        Notification::assertNotSentTo($encargada, AlertaFactura::class);

        $this->assertNotNull($enlace->fresh()->usado_en);

        $this->get(route('datos_fiscales.publicas.show', ['codigo' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clientes/DatosFiscales/ConfirmacionPublica', false)
                ->where('aplicado', true));
    }

    public function test_formulario_tercero_no_toca_cliente(): void
    {
        Notification::fake();
        $vendedor = $this->vendedor();
        $this->encargada();
        $cliente = $this->cliente(['rfc' => 'OLDX010101AAA', 'nombre_razon_social' => 'CLIENTE CUENTA']);

        $creado = app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_TERCERO,
            'razon_social' => 'TERCERO TEMP',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $token = EnlaceDatosFiscales::query()->value('codigo_publico');

        app(AplicarDatosFiscalesPublicosDesdeEnlaceService::class)->ejecutar(
            $token,
            $this->datosFiscalesCompletos()
        );

        $solicitud = $creado['solicitud']->fresh(['estado']);
        $this->assertSame('Borrador', $solicitud->estado->nombre);
        $this->assertSame('EMPRESA PRUEBA SA DE CV', $solicitud->razon_social);
        $this->assertSame('XAXX010101000', $solicitud->datos_fiscales['rfc']);

        $cliente->refresh();
        $this->assertSame('OLDX010101AAA', $cliente->rfc);
        $this->assertSame('CLIENTE CUENTA', $cliente->nombre_razon_social);

        Notification::assertSentTo($vendedor, AlertaFactura::class, fn ($n) => $n->tipoAlerta === 'formulario_respondido');
    }

    public function test_host_form_bloquea_rutas_internas_y_permite_datos_fiscales(): void
    {
        $this->get('https://form.neobash.site/datos-fiscales')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clientes/DatosFiscales/ConfirmacionPublica', false)
                ->where('motivo', 'sin_token'));

        $this->get('https://form.neobash.site/login')
            ->assertNotFound();
    }

    public function test_enviar_pendiente_directo_notifica_encargada(): void
    {
        Notification::fake();
        Storage::fake('public');
        $vendedor = $this->vendedor();
        $encargada = $this->encargada();
        $cliente = $this->cliente();

        $voucher = UploadedFile::fake()->image('voucher.jpg');

        app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'pendiente',
            'razon_social' => 'EMPRESA DIRECTA',
            'numero_cliente' => $cliente->numero_cliente,
            'vouchers' => [$voucher],
        ], $vendedor->id);

        Notification::assertSentTo($encargada, AlertaFactura::class, fn ($n) => $n->tipoAlerta === 'nueva');
        Notification::assertNotSentTo($vendedor, AlertaFactura::class);
    }

    public function test_regenerar_revoca_enlace_previo(): void
    {
        $vendedor = $this->vendedor();
        $cliente = $this->cliente();

        $creado = app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'razon_social' => 'EMPRESA',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $primero = EnlaceDatosFiscales::query()->firstOrFail();

        $segundo = app(GenerarEnlaceDatosFiscalesService::class)->ejecutar($creado['solicitud'], [
            'accion' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos' => EnlaceDatosFiscales::CAMPOS,
            'usuario_id' => $vendedor->id,
        ]);

        $this->assertNotNull($primero->fresh()->revocado_en);
        $this->assertTrue($segundo['enlace']->estaVigente());
    }

    public function test_bloquear_enviar_ahora_con_formulario_pendiente(): void
    {
        $vendedor = $this->vendedor();
        $cliente = $this->cliente();

        $creado = app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'razon_social' => 'EMPRESA',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $solicitud = $creado['solicitud']->fresh();
        $this->assertNotNull($solicitud->formulario_enviado_at);
        $this->assertNull($solicitud->formulario_respondido_at);

        \App\Models\SolicitudFacturaVoucher::create([
            'solicitud_factura_id' => $solicitud->id,
            'path' => 'facturas/vouchers/test.jpg',
            'nombre_original' => 'voucher.jpg',
            'mime' => 'image/jpeg',
            'orden' => 1,
        ]);

        try {
            app(\App\Services\Facturas\ActualizarBorradorFacturaService::class)->ejecutar($solicitud, [
                'razon_social' => $solicitud->razon_social,
                'numero_cliente' => $cliente->numero_cliente,
                'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
                'enviar_ahora' => true,
            ], $vendedor);
            $this->fail('Se esperaba ValidationException');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('enviar_ahora', $e->errors());
        }

        $this->assertSame('Borrador', $solicitud->fresh(['estado'])->estado->nombre);
    }

    public function test_enviar_ahora_tras_formulario_respondido(): void
    {
        Notification::fake();
        $vendedor = $this->vendedor();
        $this->encargada();
        $cliente = $this->cliente();

        $creado = app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'razon_social' => 'TEMP',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $token = EnlaceDatosFiscales::query()->value('codigo_publico');
        app(AplicarDatosFiscalesPublicosDesdeEnlaceService::class)->ejecutar(
            $token,
            $this->datosFiscalesCompletos()
        );

        $solicitud = $creado['solicitud']->fresh();
        $this->assertNotNull($solicitud->formulario_respondido_at);

        \App\Models\SolicitudFacturaVoucher::create([
            'solicitud_factura_id' => $solicitud->id,
            'path' => 'facturas/vouchers/test.jpg',
            'nombre_original' => 'voucher.jpg',
            'mime' => 'image/jpeg',
            'orden' => 1,
        ]);

        $resultado = app(\App\Services\Facturas\ActualizarBorradorFacturaService::class)->ejecutar($solicitud, [
            'razon_social' => $solicitud->razon_social,
            'numero_cliente' => $cliente->numero_cliente,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'enviar_ahora' => true,
        ], $vendedor);

        $this->assertSame('Pendiente', $resultado['solicitud']->estado->nombre);
    }

    public function test_evento_formulario_respondido_sin_por_usuario(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\SolicitudFacturaActualizada::class]);
        Notification::fake();
        $vendedor = $this->vendedor();
        $cliente = $this->cliente();

        app(CrearSolicitudFacturaService::class)->ejecutar([
            'modo' => 'borrador',
            'pedir_formulario' => true,
            'accion_formulario' => EnlaceDatosFiscales::ACCION_PRIMERA,
            'campos_fiscales' => EnlaceDatosFiscales::CAMPOS,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'razon_social' => 'TEMP',
            'numero_cliente' => $cliente->numero_cliente,
        ], $vendedor->id);

        $token = EnlaceDatosFiscales::query()->value('codigo_publico');
        app(AplicarDatosFiscalesPublicosDesdeEnlaceService::class)->ejecutar(
            $token,
            $this->datosFiscalesCompletos()
        );

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\SolicitudFacturaActualizada::class,
            fn ($e) => $e->accion === 'formulario_respondido' && $e->porUsuarioId === null
        );
    }
}
