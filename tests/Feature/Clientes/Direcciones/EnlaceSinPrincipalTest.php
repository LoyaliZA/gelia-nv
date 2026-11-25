<?php

namespace Tests\Feature\Clientes\Direcciones;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\SolicitudDireccion;
use App\Services\Clientes\Direcciones\AplicarDireccionPublicaDesdeEnlaceService;
use App\Services\Clientes\Direcciones\GenerarEnlaceDireccionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un cliente sin direcciones puede registrar una principal o una adicional desde el enlace, sin exigir principal previa. */
class EnlaceSinPrincipalTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_enlace_de_primera_direccion_la_deja_como_principal(): void
    {
        $direccion = $this->aplicar(SolicitudDireccion::ACCION_PRIMERA);

        $this->assertTrue((bool) $direccion->es_principal);
        $this->assertSame(1, ClienteDireccion::query()->where('cliente_id', $direccion->cliente_id)->count());
    }

    public function test_el_enlace_de_adicional_guarda_sin_exigir_una_principal_previa(): void
    {
        $direccion = $this->aplicar(SolicitudDireccion::ACCION_ADICIONAL);

        $this->assertFalse((bool) $direccion->es_principal);
        $this->assertTrue((bool) $direccion->esta_activa);
        $this->assertSame(ClienteDireccion::ESTADO_VERIFIED, $direccion->estado_verificacion);
        $this->assertSame(0, ClienteDireccion::query()->where('cliente_id', $direccion->cliente_id)->where('es_principal', true)->count());
    }

    private function aplicar(string $accion): ClienteDireccion
    {
        $lista = CatalogoListaDescuento::firstOrCreate(['nombre' => 'PUBLICO GENERAL'], ['monto_requerido' => 0, 'activo' => true]);
        $cliente = Cliente::create([
            'numero_cliente' => '04960',
            'nombre' => 'Cliente Sin Direcciones',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
            'telefono' => '5511111111',
            'correo_electronico' => 'sin@example.com',
        ]);
        $this->assertSame(0, ClienteDireccion::query()->where('cliente_id', $cliente->id)->count());

        $enlace = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, ['horas' => 24, 'accion' => $accion]);

        return app(AplicarDireccionPublicaDesdeEnlaceService::class)->ejecutar($enlace['token'], [
            'nombres_destinatario' => 'Ana',
            'apellidos_destinatario' => 'Prueba',
            'nombre_destinatario' => 'Ana Prueba',
            'telefono_destinatario' => '5512345678',
            'calle' => 'Calle Sol',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
            'etiqueta' => 'Casa',
            'anexa_remision' => false,
        ]);
    }
}
