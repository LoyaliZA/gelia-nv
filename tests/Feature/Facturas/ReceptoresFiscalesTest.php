<?php

namespace Tests\Feature\Facturas;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ReceptorFiscal;
use App\Models\User;
use App\Services\Facturas\GestionarReceptorFiscalService;
use App\Services\Facturas\ImportarReceptoresFiscalesService;
use Database\Seeders\CatalogosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceptoresFiscalesTest extends TestCase
{
    use RefreshDatabase;

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    private function encargada(): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $perm = Permission::findOrCreate('facturas.gestionar_datos_fiscales', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo($perm);

        return $user->fresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosFiscalesSeeder::class);
    }

    public function test_crear_receptor_asigna_codigo_tf(): void
    {
        $receptor = app(GestionarReceptorFiscalService::class)->crear([
            'nombre_razon_social' => '  José Tercero  ',
            'rfc' => 'XAXX010101000',
            'regimen_fiscal' => '601',
            'uso_factura' => 'G03',
        ]);

        $this->assertSame('TF-000001', $receptor->codigo_interno);
        $this->assertSame('JOSE TERCERO', $receptor->nombre_razon_social);
        $this->assertSame('XAXX010101000', $receptor->rfc);
    }

    public function test_vincular_cliente_receptor_no_duplica(): void
    {
        $cliente = Cliente::create([
            'numero_cliente' => '9001',
            'nombre' => 'Cuenta Prestada',
            'lista_actual_id' => $this->lista()->id,
        ]);
        $receptor = app(GestionarReceptorFiscalService::class)->crear([
            'nombre_razon_social' => 'TERCERO UNO',
            'rfc' => 'XAXX010101000',
        ]);

        $cliente->receptoresFiscales()->syncWithoutDetaching([$receptor->id]);
        $cliente->receptoresFiscales()->syncWithoutDetaching([$receptor->id]);

        $this->assertSame(1, $cliente->receptoresFiscales()->count());
    }

    public function test_import_receptores_crea_y_reimport_actualiza(): void
    {
        $csv = storage_path('framework/testing/receptores.csv');
        @mkdir(dirname($csv), 0777, true);
        file_put_contents($csv, implode("\n", [
            'NOMBRE (RAZON SOCIAL),RFC,CODIGO POSTAL,REGIMEN FISCAL,CORREO ELECTRONICO,USO DE FACTURA,NUMERO TELEFONICO',
            'Empresa Importada SA,XAXX010101000,12345,601,a@b.com,G03,5511111111',
        ]));

        $file = new UploadedFile($csv, 'receptores.csv', 'text/csv', null, true);
        $stats = app(ImportarReceptoresFiscalesService::class)->ejecutar($file);

        $this->assertSame(1, $stats['creados']);
        $receptor = ReceptorFiscal::query()->first();
        $this->assertNotNull($receptor);
        $this->assertSame('EMPRESA IMPORTADA SA', $receptor->nombre_razon_social);
        $codigo = $receptor->codigo_interno;

        file_put_contents($csv, implode("\n", [
            'NOMBRE (RAZON SOCIAL),RFC,CODIGO POSTAL,REGIMEN FISCAL,CORREO ELECTRONICO,USO DE FACTURA,NUMERO TELEFONICO',
            'Empresa Importada SA Actualizada,XAXX010101000,54321,601,c@d.com,G03,5522222222',
        ]));
        $file2 = new UploadedFile($csv, 'receptores.csv', 'text/csv', null, true);
        $stats2 = app(ImportarReceptoresFiscalesService::class)->ejecutar($file2);

        $this->assertSame(1, $stats2['actualizados']);
        $receptor->refresh();
        $this->assertSame($codigo, $receptor->codigo_interno);
        $this->assertSame('EMPRESA IMPORTADA SA ACTUALIZADA', $receptor->nombre_razon_social);
        $this->assertSame('54321', $receptor->codigo_postal);
    }

    public function test_encargada_puede_ver_pestana_receptores(): void
    {
        $user = $this->encargada();
        $this->actingAs($user)
            ->get(route('facturas.datos_fiscales.index', ['tab' => 'receptores']))
            ->assertOk();
    }
}
