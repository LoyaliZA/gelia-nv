<?php

namespace Tests\Feature\Clientes\Direcciones;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\SolicitudDireccion;
use App\Services\Clientes\Direcciones\GenerarEnlaceDireccionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El formulario público exige teléfono de 10 dígitos y código postal de 5. */
class DireccionPublicaValidacionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://form.neobash.site/direcciones-envio';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://gelianv.neobash.site', 'app.form_public_url' => 'https://form.neobash.site', 'app.allowed_hosts' => '']);
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_rechaza_un_telefono_con_menos_de_10_digitos(): void
    {
        $this->enviar(['telefono_destinatario' => '5512345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('telefono_destinatario');
    }

    public function test_rechaza_un_telefono_con_letras(): void
    {
        $this->enviar(['telefono_destinatario' => 'abc5512345678'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('telefono_destinatario');
    }

    public function test_rechaza_un_codigo_postal_de_4_digitos(): void
    {
        $this->enviar(['codigo_postal' => '0600'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo_postal');
    }

    public function test_guarda_el_telefono_normalizado_a_10_digitos(): void
    {
        $cliente = $this->enviar(['telefono_destinatario' => '+52 (55) 1234-5678'], true)->assertRedirect();

        $this->assertSame('5512345678', ClienteDireccion::query()->value('telefono_destinatario'));
    }

    public function test_el_telefono_sigue_siendo_opcional(): void
    {
        $this->enviar(['telefono_destinatario' => ''], true)->assertRedirect();

        $this->assertNull(ClienteDireccion::query()->value('telefono_destinatario'));
    }

    private function enviar(array $extra, bool $comoFormulario = false)
    {
        $lista = CatalogoListaDescuento::firstOrCreate(['nombre' => 'PUBLICO GENERAL'], ['monto_requerido' => 0, 'activo' => true]);
        $cliente = Cliente::create([
            'numero_cliente' => '04970', 'nombre' => 'Cliente Formulario', 'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0, 'telefono' => '5511111111', 'correo_electronico' => 'form@example.com',
        ]);
        $enlace = app(GenerarEnlaceDireccionService::class)->ejecutar($cliente, ['horas' => 24, 'accion' => SolicitudDireccion::ACCION_PRIMERA]);
        $datos = array_merge([
            'token' => $enlace['token'],
            'nombres_destinatario' => 'Ana', 'apellidos_destinatario' => 'Prueba',
            'calle' => 'Calle Sol', 'colonia' => 'Centro', 'codigo_postal' => '06000',
            'municipio' => 'CDMX', 'estado' => 'CDMX', 'pais' => 'México',
            'etiqueta_opcion' => 'Casa', 'anexa_remision' => false, 'domicilio_irregular' => false,
        ], $extra);

        return $comoFormulario ? $this->post(self::URL, $datos) : $this->postJson(self::URL, $datos);
    }
}
