<?php

namespace Tests\Unit\Clientes\Direcciones;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\SolicitudDireccion;
use App\Models\User;
use App\Services\Clientes\Direcciones\AplicarDireccionPublicaDesdeEnlaceService;
use App\Services\Clientes\Direcciones\GenerarEnlaceDireccionService;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Support\Clientes\Direcciones\SanitizarEntradaDireccionPublica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Mejoras26DireccionesTest extends TestCase
{
    use RefreshDatabase;

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    private function crearCliente(array $extra = []): Cliente
    {
        return Cliente::query()->create(array_merge([
            'numero_cliente' => '04950',
            'nombre' => 'Cliente Prueba',
            'lista_actual_id' => $this->lista()->id,
            'monto_venta_actual' => 0,
            'telefono' => '5512345678',
            'correo_electronico' => 'cliente@example.com',
        ], $extra));
    }

    public function test_actualizar_direccion_in_place_no_crea_nueva_fila(): void
    {
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);

        $original = $gestion->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Ana Pérez',
            'nombres_destinatario' => 'Ana',
            'apellidos_destinatario' => 'Pérez',
            'calle' => 'Calle 1',
            'numero_exterior' => '10',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
        ], ['verificar' => true]);

        $actualizada = $gestion->actualizarDireccionInPlace($original->id, [
            'nombres_destinatario' => 'Ana María',
            'apellidos_destinatario' => 'Pérez López',
            'nombre_destinatario' => 'Ana María Pérez López',
            'calle' => 'Calle Nueva',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
            'anexa_remision' => true,
        ], ['verificar' => true]);

        $this->assertSame($original->id, $actualizada->id);
        $this->assertSame(1, ClienteDireccion::query()->where('cliente_id', $cliente->id)->activas()->count());
        $this->assertSame('Calle Nueva', $actualizada->calle);
        $this->assertSame('Ana María', $actualizada->nombres_destinatario);
        $this->assertTrue($actualizada->anexa_remision);
        $this->assertSame(ClienteDireccion::ESTADO_VERIFIED, $actualizada->estado_verificacion);
    }

    public function test_aplicar_desde_enlace_update_y_add(): void
    {
        $user = User::factory()->create();
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);

        $principal = $gestion->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Ana Pérez',
            'calle' => 'Calle 1',
            'numero_exterior' => '10',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
        ], ['verificar' => true]);

        $generar = app(GenerarEnlaceDireccionService::class);
        $enlaceUpdate = $generar->ejecutar($cliente, [
            'accion' => SolicitudDireccion::ACCION_ACTUALIZAR,
            'direccion_id' => $principal->id,
            'usuario_id' => $user->id,
        ]);

        $aplicar = app(AplicarDireccionPublicaDesdeEnlaceService::class);
        $actualizada = $aplicar->ejecutar($enlaceUpdate['token'], [
            'nombres_destinatario' => 'Luis',
            'apellidos_destinatario' => 'García',
            'nombre_destinatario' => 'Luis García',
            'calle' => 'Calle Actualizada',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
            'etiqueta' => 'Casa',
            'anexa_remision' => false,
        ]);

        $this->assertSame($principal->id, $actualizada->id);
        $this->assertSame('Calle Actualizada', $actualizada->fresh()->calle);
        $this->assertNotNull($enlaceUpdate['enlace']->fresh()->usado_en);

        $enlaceAdd = $generar->ejecutar($cliente, [
            'accion' => SolicitudDireccion::ACCION_ADICIONAL,
            'usuario_id' => $user->id,
        ]);

        $adicional = $aplicar->ejecutar($enlaceAdd['token'], [
            'nombres_destinatario' => 'María',
            'apellidos_destinatario' => 'López',
            'nombre_destinatario' => 'María López',
            'calle' => 'Calle 2',
            'colonia' => 'Roma',
            'codigo_postal' => '06700',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
            'etiqueta' => 'Trabajo',
            'anexa_remision' => true,
        ]);

        $this->assertNotSame($principal->id, $adicional->id);
        $this->assertSame(2, ClienteDireccion::query()->where('cliente_id', $cliente->id)->activas()->count());
        $this->assertTrue($adicional->anexa_remision);
    }

    public function test_update_address_sin_direccion_id_falla(): void
    {
        $cliente = $this->crearCliente();
        app(GestionDireccionesClienteService::class)->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Ana',
            'calle' => 'Calle 1',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
        ], ['verificar' => true]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, [
            'accion' => SolicitudDireccion::ACCION_ACTUALIZAR,
        ]);
    }

    public function test_update_adicional_no_toca_contenido_principal(): void
    {
        $user = User::factory()->create();
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);

        $principal = $gestion->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Principal',
            'calle' => 'Calle Principal',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
        ], ['verificar' => true]);

        $adicional = $gestion->crearDireccionAdicional($cliente->id, [
            'nombre_destinatario' => 'Adicional',
            'calle' => 'Calle Extra',
            'colonia' => 'Roma',
            'codigo_postal' => '06700',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
        ], ['verificar' => true]);

        $enlace = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, [
            'accion' => SolicitudDireccion::ACCION_ACTUALIZAR,
            'direccion_id' => $adicional->id,
            'usuario_id' => $user->id,
        ]);

        app(AplicarDireccionPublicaDesdeEnlaceService::class)->ejecutar($enlace['token'], [
            'nombres_destinatario' => 'Nueva',
            'apellidos_destinatario' => 'Extra',
            'nombre_destinatario' => 'Nueva Extra',
            'calle' => 'Calle Extra Nueva',
            'colonia' => 'Roma',
            'codigo_postal' => '06700',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
            'etiqueta' => 'Trabajo',
            'anexa_remision' => false,
        ]);

        $this->assertSame('Calle Principal', $principal->fresh()->calle);
        $this->assertSame('Calle Extra Nueva', $adicional->fresh()->calle);
        $this->assertTrue($principal->fresh()->es_principal);
        $this->assertFalse($adicional->fresh()->es_principal);
    }

    public function test_notifica_vendedora_al_aplicar_formulario_publico(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $vendedor = User::factory()->create();
        $cliente = $this->crearCliente(['vendedor_id' => $vendedor->id]);

        $enlace = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, [
            'accion' => SolicitudDireccion::ACCION_PRIMERA,
            'usuario_id' => $vendedor->id,
        ]);

        app(AplicarDireccionPublicaDesdeEnlaceService::class)->ejecutar($enlace['token'], [
            'nombres_destinatario' => 'Ana',
            'apellidos_destinatario' => 'Prueba',
            'nombre_destinatario' => 'Ana Prueba',
            'calle' => 'Calle Sol',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
            'etiqueta' => 'Casa',
            'anexa_remision' => false,
        ]);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $vendedor,
            \App\Notifications\AlertaDireccion::class
        );
    }

    public function test_domicilio_irregular_desde_enlace(): void
    {
        $user = User::factory()->create();
        $cliente = $this->crearCliente();

        $enlace = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, [
            'accion' => SolicitudDireccion::ACCION_PRIMERA,
            'usuario_id' => $user->id,
        ]);

        $direccion = app(AplicarDireccionPublicaDesdeEnlaceService::class)->ejecutar($enlace['token'], [
            'nombres_destinatario' => 'Ana',
            'apellidos_destinatario' => 'Prueba',
            'nombre_destinatario' => 'Ana Prueba',
            'estado' => 'Jalisco',
            'municipio' => 'Tonalá',
            'referencias' => 'Domicilio conocido a un lado de la plaza principal',
            'domicilio_irregular' => true,
            'etiqueta' => 'Casa',
            'anexa_remision' => false,
        ]);

        $this->assertTrue($direccion->domicilio_irregular);
        $this->assertNull($direccion->calle);
        $this->assertStringContainsString('Domicilio conocido', (string) $direccion->referencias);
    }

    public function test_sanitiza_inyeccion_y_html_malicioso(): void
    {
        $limpio = SanitizarEntradaDireccionPublica::texto('<script>alert(1)</script>Calle Unión');
        $this->assertStringNotContainsString('<script>', $limpio);
        $this->assertStringContainsString('Calle Unión', $limpio);

        $this->assertFalse(SanitizarEntradaDireccionPublica::esTextoSeguro("'; DROP TABLE clientes; --"));
        $this->assertFalse(SanitizarEntradaDireccionPublica::esTextoSeguro('javascript:alert(1)'));
        $this->assertTrue(SanitizarEntradaDireccionPublica::esTextoSeguro('Av. Reforma 100'));
    }

    public function test_segundo_uso_del_mismo_token_falla(): void
    {
        $user = User::factory()->create();
        $cliente = $this->crearCliente();
        $enlace = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, [
            'accion' => SolicitudDireccion::ACCION_PRIMERA,
            'usuario_id' => $user->id,
        ]);

        $aplicar = app(AplicarDireccionPublicaDesdeEnlaceService::class);
        $payload = [
            'nombres_destinatario' => 'Ana',
            'apellidos_destinatario' => 'Pérez',
            'nombre_destinatario' => 'Ana Pérez',
            'calle' => 'Calle 1',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
            'etiqueta' => 'Casa',
            'anexa_remision' => false,
        ];

        $aplicar->ejecutar($enlace['token'], $payload);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ya fue utilizado');
        $aplicar->ejecutar($enlace['token'], $payload);
    }
}
