<?php

namespace Tests\Feature\Clientes\Direcciones;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\User;
use App\Services\Clientes\Direcciones\ImportarDireccionesClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImportarDireccionesClienteTest extends TestCase
{
    use RefreshDatabase;

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    private function crearCliente(string $numero = '8699'): Cliente
    {
        return Cliente::query()->create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente Import',
            'lista_actual_id' => $this->lista()->id,
            'monto_venta_actual' => 0,
            'telefono' => '5512345678',
            'correo_electronico' => 'import@example.com',
        ]);
    }

    private function usuarioConPermiso(): User
    {
        Permission::findOrCreate('clientes.direcciones.crear', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('clientes.direcciones.crear');

        return $user;
    }

    private function csv(string $body): UploadedFile
    {
        $headers = implode(',', ImportarDireccionesClienteService::HEADERS);
        $content = $headers."\n".$body;

        return UploadedFile::fake()->createWithContent('direcciones.csv', $content);
    }

    public function test_plantilla_descarga_ok(): void
    {
        $user = $this->usuarioConPermiso();

        $this->actingAs($user)
            ->get(route('control_pedidos.direcciones.plantilla_importacion'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_importa_principal_y_adicional(): void
    {
        Storage::fake('local');
        $this->crearCliente('8699');
        $user = $this->usuarioConPermiso();

        $body = implode("\n", [
            '8699,1,Ana Pérez,5511111111,Av Reforma,100,,Centro,06000,Cuauhtémoc,CDMX,CDMX,México,,,Casa,envio,0,1',
            '8699,0,Luis Gómez,5522222222,Calle Sur,20,,Roma,06700,Cuauhtémoc,CDMX,CDMX,México,,,Oficina,envio,0,0',
        ]);

        $this->actingAs($user)
            ->post(route('control_pedidos.direcciones.importar'), [
                'archivo' => $this->csv($body),
            ])
            ->assertRedirect();

        $dirs = ClienteDireccion::query()->whereHas('cliente', fn ($q) => $q->where('numero_cliente', '8699'))
            ->activas()
            ->orderBy('numero_direccion')
            ->get();

        $this->assertCount(2, $dirs);
        $this->assertTrue($dirs[0]->es_principal);
        $this->assertSame(1, $dirs[0]->numero_direccion);
        $this->assertSame('Ana Pérez', $dirs[0]->nombre_destinatario);
        $this->assertTrue($dirs[0]->anexa_remision);
        $this->assertSame(ClienteDireccion::ORIGEN_IMPORT_CATALOGO, $dirs[0]->origen);
        $this->assertFalse($dirs[1]->es_principal);
        $this->assertSame(2, $dirs[1]->numero_direccion);
        $this->assertSame(ClienteDireccion::ESTADO_VERIFIED, $dirs[0]->estado_verificacion);
    }

    public function test_omite_cliente_inexistente_y_duplicado(): void
    {
        Storage::fake('local');
        $this->crearCliente('8699');
        $user = $this->usuarioConPermiso();

        $filaValida = '8699,1,Ana Pérez,5511111111,Av Reforma,100,,Centro,06000,Cuauhtémoc,CDMX,CDMX,México,,,Casa,envio,0,0';
        $filaDup = $filaValida;
        $filaInexistente = '99999,1,Nadie,5511111111,Calle X,1,,Centro,06000,Cuauhtémoc,CDMX,CDMX,México,,,Casa,envio,0,0';

        $body = implode("\n", [$filaValida, $filaDup, $filaInexistente]);

        $this->actingAs($user)
            ->post(route('control_pedidos.direcciones.importar'), [
                'archivo' => $this->csv($body),
            ])
            ->assertRedirect();

        $this->assertSame(1, ClienteDireccion::query()->activas()->count());
    }
}
