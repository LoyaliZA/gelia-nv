<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RevisionImportacionClientesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['PUBLICO GENERAL' => 0, 'MAYOREO BRONCE' => 0.01, 'MAYOREO PLATA' => 5104] as $nombre => $monto) {
            CatalogoListaDescuento::create(['nombre' => $nombre, 'monto_requerido' => $monto, 'activo' => true]);
        }
        Permission::findOrCreate('clientes.ver', 'web');
        Permission::findOrCreate('clientes.carga_masiva', 'web');
        $rol = Role::findOrCreate('admin_import', 'web');
        $rol->givePermissionTo(['clientes.ver', 'clientes.carga_masiva']);
        $this->usuario = User::factory()->create();
        $this->usuario->assignRole($rol);
    }

    public function test_el_codigo_vacio_no_inactiva_ni_baja_la_lista(): void
    {
        $bronce = $this->lista('MAYOREO BRONCE');
        $this->cliente('100', $bronce, '40.00');
        $this->cliente('101', $bronce, '80.00');

        $vista = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n100,,0\n101,,25\n");
        $this->confirmar($vista);

        $cero = Cliente::where('numero_cliente', '100')->first();
        $positivo = Cliente::where('numero_cliente', '101')->first();
        $this->assertFalse($cero->es_inactivo);
        $this->assertFalse($positivo->es_inactivo);
        $this->assertSame($bronce, $cero->lista_actual_id);
        $this->assertSame($bronce, $positivo->lista_actual_id);
        $this->assertSame('0.00', $cero->monto_venta_actual);
        $this->assertSame('25.00', $positivo->monto_venta_actual);
        $this->assertSame(0, $vista['conteos']['inactivaciones']);
        $this->assertSame(0, $vista['conteos']['descensos']);
    }

    public function test_un_archivo_grande_se_revisa_y_bloquea_la_escritura_destructiva(): void
    {
        $bronce = $this->lista('MAYOREO BRONCE');
        $total = 2000;
        $ahora = now();
        $lote = [];
        $lineas = ['numero_cliente,codigo_lista,monto_venta_actual,fecha_corte'];
        for ($i = 1; $i <= $total; $i++) {
            $lote[] = [
                'numero_cliente' => (string) $i,
                'nombre' => 'Cliente ficticio '.$i,
                'lista_actual_id' => $bronce,
                'monto_venta_actual' => '10.00',
                'es_inactivo' => false,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $fecha = $i === $total ? '2020-01-01' : '2026-11-12';
            $lineas[] = $i.',,5000,'.$fecha;
            if (count($lote) === 500) {
                Cliente::insert($lote);
                $lote = [];
            }
        }
        if ($lote !== []) {
            Cliente::insert($lote);
        }
        $ultimo = Cliente::where('numero_cliente', (string) $total)->first();
        $historial = HistorialMontoCliente::create([
            'cliente_id' => $ultimo->id,
            'monto_anterior' => '5.00',
            'monto_nuevo' => '10.00',
            'diferencia_aplicada' => '5.00',
            'origen' => 'carga_masiva',
        ]);
        $historial->created_at = '2026-11-01 12:00:00';
        $historial->save();

        $vista = $this->revisar(implode("\n", $lineas)."\n");

        $this->assertSame($total, $vista['conteos']['filas']);
        $this->assertSame(0, $vista['conteos']['inactivaciones']);
        $this->assertSame(0, $vista['conteos']['descensos']);
        $this->assertTrue(collect($vista['avisos'])->contains(fn ($aviso) => $aviso['codigo'] === 'diferencia_monto'));
        $this->assertTrue($vista['bloqueado']);
        $this->assertTrue(collect($vista['bloqueos'])->contains(fn ($bloqueo) => $bloqueo['codigo'] === 'archivo_anterior'));
        $this->assertSame($total, Cliente::where('lista_actual_id', $bronce)->where('es_inactivo', false)->count());
        $this->assertSame(1, HistorialMontoCliente::count());

        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $vista['importacion_id'],
            'token' => $vista['token'],
        ])->assertSessionHasErrors('archivo');

        $this->assertSame('10.00', Cliente::where('numero_cliente', '1')->value('monto_venta_actual'));
        $this->assertSame(1, HistorialMontoCliente::count());
    }

    public function test_la_confirmacion_escribe_auditoria_y_no_se_duplica(): void
    {
        $bronce = $this->lista('MAYOREO BRONCE');
        $plata = $this->lista('MAYOREO PLATA');
        $this->cliente('300', $bronce, '10.00');
        $this->cliente('301', $plata, '100.00');
        $vista = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n300,3,20\n301,2,100\n");
        $this->assertFalse($vista['bloqueado']);
        $this->assertTrue(collect($vista['avisos'])->contains(fn ($aviso) => $aviso['codigo'] === 'descenso_lista'));

        $this->confirmar($vista);
        $this->confirmar($vista);

        $cliente = Cliente::where('numero_cliente', '300')->first();
        $this->assertSame($plata, $cliente->lista_actual_id);
        $this->assertSame('20.00', $cliente->monto_venta_actual);
        $bajada = Cliente::where('numero_cliente', '301')->first();
        $this->assertSame($bronce, $bajada->lista_actual_id);
        $this->assertFalse($bajada->es_inactivo);
        $this->assertSame(1, HistorialMontoCliente::count());
        $this->assertSame(1, ImportacionCliente::where('estado', 'completo')->count());

        $repetida = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n300,3,20\n301,2,100\n");
        $this->assertTrue($repetida['bloqueado']);
        $this->assertTrue(collect($repetida['bloqueos'])->contains(fn ($bloqueo) => $bloqueo['codigo'] === 'archivo_repetido'));
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $repetida['importacion_id'],
            'token' => $repetida['token'],
        ])->assertSessionHasErrors('archivo');
        $this->assertSame(1, HistorialMontoCliente::count());
    }

    public function test_un_archivo_o_un_cliente_cambiado_no_aplica_la_revision_anterior(): void
    {
        $bronce = $this->lista('MAYOREO BRONCE');
        $cliente = $this->cliente('400', $bronce, '10.00');
        $vista = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n400,,30\n");
        $importacion = ImportacionCliente::findOrFail($vista['importacion_id']);
        Storage::disk('local')->put($importacion->ruta_almacenamiento, "numero_cliente,codigo_lista,monto_venta_actual\n400,,90\n");

        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $vista['importacion_id'],
            'token' => $vista['token'],
        ])->assertSessionHasErrors('archivo');
        $this->assertSame('10.00', $cliente->fresh()->monto_venta_actual);

        $otra = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n400,,30\n");
        $cliente->update(['monto_venta_actual' => '12.00']);
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $otra['importacion_id'],
            'token' => $otra['token'],
        ])->assertSessionHasErrors('archivo');
        $this->assertSame('12.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame(0, HistorialMontoCliente::count());
    }

    public function test_la_ruta_directa_no_escribe_sin_revision(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('directo.csv', "numero_cliente,nombre,codigo_lista\n500,Cliente ficticio,2\n");

        $this->actingAs($this->usuario)->post(route('admin.clientes.importar'), [
            'archivo' => $archivo,
        ])->assertSessionHasErrors('archivo');

        $this->assertNull(Cliente::where('numero_cliente', '500')->first());
    }

    private function cliente(string $numero, int $lista, string $monto): Cliente
    {
        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $lista,
            'monto_venta_actual' => $monto,
            'es_inactivo' => false,
        ]);
    }

    private function lista(string $nombre): int
    {
        return (int) CatalogoListaDescuento::where('nombre', $nombre)->value('id');
    }

    private function revisar(string $csv): array
    {
        $respuesta = $this->actingAs($this->usuario)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => UploadedFile::fake()->createWithContent('revision.csv', $csv),
        ]);
        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('previsualizacion_importacion');

        return session('previsualizacion_importacion');
    }

    private function confirmar(array $vista): void
    {
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $vista['importacion_id'],
            'token' => $vista['token'],
        ])->assertRedirect();
    }
}
