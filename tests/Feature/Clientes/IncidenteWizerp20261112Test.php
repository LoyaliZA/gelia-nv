<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\HistorialMontoCliente;
use App\Models\SolicitudBeneficio;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncidenteWizerp20261112Test extends TestCase
{
    use RefreshDatabase;

    private const FILAS_REPORTADAS = 6827;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'PUBLICO GENERAL' => 0,
            'MAYOREO BRONCE' => 0.01,
            'MAYOREO PLATA' => 5104,
            'MAYOREO ORO' => 31250,
            'MAYOREO DIAMANTE' => 85108,
        ] as $nombre => $monto) {
            CatalogoListaDescuento::create(['nombre' => $nombre, 'monto_requerido' => $monto, 'activo' => true]);
        }
    }

    public function test_codigo_vacio_no_inactiva_ni_degrada_ni_toca_beneficios(): void
    {
        $bronce = $this->lista('MAYOREO BRONCE');
        $tipo = CatalogoTipoCliente::create(['nombre' => 'MOSTRADOR', 'activo' => true]);
        $vendedor = User::factory()->create(['name' => 'Colaborador asignado']);
        $cliente = Cliente::create([
            'numero_cliente' => '1001',
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $bronce,
            'monto_venta_actual' => '1500.00',
            'es_inactivo' => false,
            'vendedor_id' => $vendedor->id,
            'catalogo_tipo_cliente_id' => $tipo->id,
        ]);
        $estado = CatalogoEstadoSolicitud::create(['nombre' => 'Pendiente', 'activo' => true]);
        $proceso = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $solicitud = SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $estado->id,
            'catalogo_lista_descuento_id' => $this->lista('MAYOREO PLATA'),
            'catalogo_tipo_cliente_id' => $tipo->id,
            'monto_cotizado' => 4000,
            'monto_aplicado_al_cliente' => 0,
            'cubierto_por_carga_masiva' => true,
            'pago_confirmado' => false,
        ]);
        SolicitudBeneficio::create([
            'cliente_id' => $cliente->id,
            'solicitud_tag_id' => $solicitud->id,
            'campo' => SolicitudBeneficio::CAMPO_LISTA,
            'valor_anterior' => (string) $bronce,
            'valor_aplicado' => (string) $bronce,
            'estado' => SolicitudBeneficio::ESTADO_VIGENTE,
            'motivo' => 'Concesión de prueba.',
        ]);

        $resultado = $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n1001,,6000\n");

        $cliente->refresh();
        $this->assertFalse($cliente->es_inactivo);
        $this->assertSame($bronce, $cliente->lista_actual_id);
        $this->assertSame('1500.00', $cliente->monto_venta_actual);
        $this->assertSame($vendedor->id, $cliente->vendedor_id);
        $this->assertSame($tipo->id, $cliente->catalogo_tipo_cliente_id);
        $this->assertSame(0, $resultado['clientes_marcados_inactivos']);
        $this->assertSame(1, $resultado['stats']['errores']);
        $this->assertTrue((bool) $solicitud->fresh()->cubierto_por_carga_masiva);
        $this->assertSame($estado->id, $solicitud->fresh()->catalogo_estado_solicitud_id);
        $this->assertSame(0, EscalonamientoConciliacionSolicitud::count());
        $this->assertSame(SolicitudBeneficio::ESTADO_VIGENTE, SolicitudBeneficio::query()->value('estado'));
        $this->assertSame(0, HistorialMontoCliente::count());
    }

    public function test_cero_y_codigos_de_lista_validos(): void
    {
        $activo = $this->cliente('1002', 'MAYOREO PLATA', '80.00');
        $inactivo = $this->cliente('1003', 'MAYOREO BRONCE', '10.00', true);
        $bloqueado = $this->cliente('1004', 'MAYOREO ORO', '20.00');
        $bloqueado->update(['lista_bloqueada' => true]);

        $this->importar(implode("\n", [
            'numero_cliente,nombre,codigo_lista,monto_venta_actual',
            '1002,Cliente ficticio,,0',
            '1003,Cliente ficticio,4,0',
            '1004,Cliente ficticio,2,0',
            '1005,Cliente ficticio,1,0',
        ])."\n");

        $this->assertTrue($activo->fresh()->es_inactivo);
        $this->assertSame($this->lista('MAYOREO PLATA'), $activo->fresh()->lista_actual_id);
        $this->assertSame('0.00', $activo->fresh()->monto_venta_actual);
        $this->assertFalse($inactivo->fresh()->es_inactivo);
        $this->assertSame($this->lista('MAYOREO DIAMANTE'), $inactivo->fresh()->lista_actual_id);
        $this->assertFalse($bloqueado->fresh()->es_inactivo);
        $this->assertSame($this->lista('MAYOREO ORO'), $bloqueado->fresh()->lista_actual_id);
        $nuevo = Cliente::where('numero_cliente', '1005')->first();
        $this->assertNotNull($nuevo);
        $this->assertFalse($nuevo->es_inactivo);
        $this->assertSame($this->lista('MAYOREO ORO'), $nuevo->lista_actual_id);
    }

    public function test_fila_incompleta_no_detiene_las_validas_ni_inventa_estado(): void
    {
        $existente = $this->cliente('1006', 'MAYOREO BRONCE', '40.00');
        $resultado = $this->importar(implode("\n", [
            'numero_cliente,nombre,codigo_lista,monto_venta_actual',
            '1006,Cliente ficticio,,15',
            '2001,, ,0',
            '2002,Cliente ficticio,,10',
            '2003,Cliente ficticio,NO,10',
            '2004,Cliente ficticio,2,abc',
            '2005,Cliente ficticio,3,12',
        ])."\n");

        $this->assertFalse($existente->fresh()->es_inactivo);
        $this->assertSame($this->lista('MAYOREO BRONCE'), $existente->fresh()->lista_actual_id);
        $this->assertSame('40.00', $existente->fresh()->monto_venta_actual);
        $this->assertNull(Cliente::where('numero_cliente', '2001')->first());
        $this->assertNull(Cliente::where('numero_cliente', '2002')->first());
        $this->assertNull(Cliente::where('numero_cliente', '2003')->first());
        $this->assertNull(Cliente::where('numero_cliente', '2004')->first());
        $creado = Cliente::where('numero_cliente', '2005')->first();
        $this->assertNotNull($creado);
        $this->assertFalse($creado->es_inactivo);
        $this->assertSame($this->lista('MAYOREO PLATA'), $creado->lista_actual_id);
        $this->assertSame(5, $resultado['stats']['errores']);
        $this->assertSame(0, $resultado['clientes_marcados_inactivos']);
    }

    public function test_sin_columna_repite_y_no_restaura_el_monto_anterior(): void
    {
        $cliente = $this->cliente('1007', 'MAYOREO BRONCE', '400.00', true);
        $octubre = HistorialMontoCliente::create([
            'cliente_id' => $cliente->id,
            'monto_anterior' => '100.00',
            'monto_nuevo' => '400.00',
            'diferencia_aplicada' => '300.00',
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA,
        ]);
        $octubre->created_at = '2026-10-15 12:00:00';
        $octubre->save();

        $csv = "numero_cliente,monto_venta_actual\n1007,900\n";
        $this->importar($csv);
        $segunda = $this->importar($csv);

        $this->assertTrue($cliente->fresh()->es_inactivo);
        $this->assertSame($this->lista('MAYOREO BRONCE'), $cliente->fresh()->lista_actual_id);
        $this->assertSame('900.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('400.00', $octubre->fresh()->monto_nuevo);
        $this->assertSame(2, HistorialMontoCliente::where('cliente_id', $cliente->id)->count());
        $this->assertSame(1, $segunda['stats']['sin_cambios']);
    }

    public function test_una_importacion_en_curso_rechaza_la_siguiente(): void
    {
        if (DB::getDriverName() === 'mysql') {
            config(['database.connections.competidor' => config('database.connections.mysql')]);
            $competidor = DB::connection('competidor');
            $this->assertSame('gelia_wizerp_test', $competidor->getDatabaseName());
            $this->assertSame(1, (int) $competidor->selectOne("SELECT GET_LOCK('gelia.importacion.clientes.wizerp', 0) AS adquirido")->adquirido);
            try {
                $this->importar("numero_cliente,codigo_lista\n1008,2\n");
                $this->fail('La segunda importación debía rechazarse.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('en curso', $e->getMessage());
                $this->assertSame(0, Cliente::count());
            } finally {
                $competidor->selectOne("SELECT RELEASE_LOCK('gelia.importacion.clientes.wizerp')");
                DB::disconnect('competidor');
            }

            return;
        }

        $lock = Cache::lock('gelia.importacion.clientes.wizerp', 0);
        $lock->get();
        try {
            $this->importar("numero_cliente,codigo_lista\n1008,2\n");
            $this->fail('La segunda importación debía rechazarse.');
        } catch (ValidationException) {
            $this->assertSame(0, Cliente::count());
        } finally {
            $lock->release();
        }
    }

    public function test_el_conteo_reportado_de_filas_con_codigo_vacio_no_inactiva(): void
    {
        $bronce = $this->lista('MAYOREO BRONCE');
        $ahora = now();
        $lote = [];
        $lineas = ["numero_cliente,codigo_lista,monto_venta_actual"];
        for ($i = 1; $i <= self::FILAS_REPORTADAS; $i++) {
            $lote[] = [
                'numero_cliente' => (string) $i,
                'nombre' => 'Cliente ficticio '.$i,
                'lista_actual_id' => $bronce,
                'monto_venta_actual' => '10.00',
                'es_inactivo' => false,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $lineas[] = $i.',,'.($i % 2 === 0 ? '0' : '25.00');
            if (count($lote) === 500) {
                Cliente::insert($lote);
                $lote = [];
            }
        }
        if ($lote !== []) {
            Cliente::insert($lote);
        }

        $resultado = $this->importar(implode("\n", $lineas)."\n");

        $this->assertSame(self::FILAS_REPORTADAS, $resultado['stats']['leidas']);
        $this->assertSame(3414, $resultado['stats']['errores']);
        $this->assertSame(3413, $resultado['clientes_marcados_inactivos']);
        $this->assertSame(3413, Cliente::where('es_inactivo', true)->count());
        $this->assertSame(self::FILAS_REPORTADAS, Cliente::where('lista_actual_id', $bronce)->count());
    }

    private function cliente(string $numero, string $lista, string $monto, bool $inactivo = false): Cliente
    {
        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $this->lista($lista),
            'monto_venta_actual' => $monto,
            'es_inactivo' => $inactivo,
        ]);
    }

    private function lista(string $nombre): int
    {
        return (int) CatalogoListaDescuento::where('nombre', $nombre)->value('id');
    }

    private function importar(string $csv): array
    {
        return app(ImportarClientesWizerpService::class)->ejecutar(
            UploadedFile::fake()->createWithContent('incidente.csv', $csv)
        );
    }
}
