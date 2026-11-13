<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\MobileSyncPublication;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Clientes\MontoWizerp;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Escalonamiento\ProyeccionClienteEscalonamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegridadImportacionWizerpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(EscalonamientoAutoridadConfig::class)->guardar(false);
        foreach (['PUBLICO GENERAL' => 0, 'MAYOREO BRONCE' => 0.01, 'MAYOREO PLATA' => 5001] as $nombre => $monto) {
            CatalogoListaDescuento::create(['nombre' => $nombre, 'monto_requerido' => $monto, 'activo' => true]);
        }
    }

    private function cliente(array $datos = []): Cliente
    {
        return Cliente::create($datos + ['numero_cliente' => '990001', 'nombre' => 'Cliente ficticio', 'monto_venta_actual' => '1000.00',
            'lista_actual_id' => CatalogoListaDescuento::where('nombre', 'MAYOREO BRONCE')->value('id'), 'es_inactivo' => false]);
    }

    private function importar(string $csv, bool $registrar = false): array
    {
        $archivo = UploadedFile::fake()->createWithContent('integridad.csv', $csv);
        $importacion = null;
        if ($registrar) {
            Storage::fake('local');
            Storage::disk('local')->put('integridad.csv', $csv);
            $importacion = ImportacionCliente::create(['usuario_id' => User::factory()->create()->id,
                'nombre_archivo_original' => 'integridad.csv', 'ruta_almacenamiento' => 'integridad.csv']);
        }

        return app(ImportarClientesWizerpService::class)->ejecutar($archivo, $importacion);
    }

    public static function matriz(): array
    {
        return [
            'vacíos' => [',', false, '1000.00', 'MAYOREO BRONCE', 0],
            'cero' => [',0.00', true, '0.00', 'MAYOREO BRONCE', 0],
            'vacio_positivo' => [',2000', false, '1000.00', 'MAYOREO BRONCE', 1],
            'válido vacío' => ['3,', false, '1000.00', 'MAYOREO BRONCE', 0],
            'válido cero' => ['3,0', false, '0.00', 'MAYOREO PLATA', 0],
            'válido positivo' => ['2,6000', false, '6000.00', 'MAYOREO BRONCE', 0],
            'desconocido' => ['NO,100', false, '1000.00', 'MAYOREO BRONCE', 1],
            'fraccionario' => ['2.9,100', false, '1000.00', 'MAYOREO BRONCE', 1],
            'monto inválido' => ['3,abc', false, '1000.00', 'MAYOREO BRONCE', 1],
        ];
    }

    #[DataProvider('matriz')]
    public function test_matriz(string $fila, bool $inactivo, string $monto, string $lista, int $errores): void
    {
        $c = $this->cliente();
        $r = $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n990001,{$fila}\n");
        $c->refresh();
        $this->assertSame($inactivo, $c->es_inactivo);
        $this->assertSame($monto, $c->monto_venta_actual);
        $this->assertSame($lista, $c->listaDescuento->nombre);
        $this->assertSame($errores, $r['stats']['errores']);
        $this->assertSame(1, array_sum(array_intersect_key($r['stats'], array_flip(['creados', 'actualizados', 'sin_cambios', 'omitidas', 'errores']))));
    }

    public function test_bloqueo_no_impide_estado_ni_monto_y_no_concede_lista_efectiva(): void
    {
        $c = $this->cliente(['lista_bloqueada' => true]);
        $lista = $c->lista_actual_id;
        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n990001,,0\n");
        $this->assertTrue($c->fresh()->es_inactivo);
        $this->assertSame($lista, $c->fresh()->lista_actual_id);
        $this->assertSame('0.00', $c->fresh()->monto_venta_actual);
        $this->assertNotSame($lista, app(ProyeccionClienteEscalonamiento::class)->listaVigenteId($c->fresh()));
        $this->importar("numero_cliente,codigo_lista\n990001,3\n");
        $this->assertTrue($c->fresh()->es_inactivo);
        $this->assertSame($lista, $c->fresh()->lista_actual_id);
    }

    public function test_sin_columna_no_reactiva_y_repeticion_no_publica_ni_duplica_historial(): void
    {
        $c = $this->cliente(['es_inactivo' => true]);
        $csv = "numero_cliente,monto_venta_actual\n990001,6000\n";
        $this->importar($csv);
        $publicaciones = MobileSyncPublication::count();
        $r = $this->importar($csv);
        $this->assertTrue($c->fresh()->es_inactivo);
        $this->assertSame(1, HistorialMontoCliente::count());
        $this->assertSame($publicaciones, MobileSyncPublication::count());
        $this->assertSame(1, $r['stats']['sin_cambios']);
    }

    public function test_altas_requieren_nombre_y_estado_y_existentes_admiten_parcial(): void
    {
        $r = $this->importar("numero_cliente,nombre\n990001,Ficticio\n");
        $this->assertSame(1, $r['stats']['errores']);
        $this->assertSame(0, Cliente::count());
        $r = $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n990001,3,0\n");
        $this->assertSame(1, $r['stats']['errores']);
        $r = $this->importar("numero_cliente,nombre,codigo_lista\n990001,Ficticio,3\n");
        $this->assertSame(1, $r['stats']['creados']);
        $this->assertFalse(Cliente::first()->es_inactivo);
        $r = $this->importar("numero_cliente,nombre\n990001,Actualizado\n");
        $this->assertSame(1, $r['stats']['actualizados']);
    }

    public static function estructurasInvalidas(): array
    {
        return [[''], ['nombre\nFicticio'], ["numero_cliente,monto_venta_actual,monto_venta_actua\n990001,1,2\n"],
            ["numero_cliente,nombre\n990001,Primero\n990002\n"], ["numero_cliente,nombre\n990001,Primero\n990001,Segundo\n"],
            ["numero_cliente,nombre\n990001,Primero,Extra\n"]];
    }

    #[DataProvider('estructurasInvalidas')]
    public function test_preflight_no_escribe(string $csv): void
    {
        $c = $this->cliente();
        $publicaciones = MobileSyncPublication::count();
        try {
            $this->importar($csv, true);
            $this->fail('Debió rechazar estructura');
        } catch (ValidationException) {
            $this->assertSame('Cliente ficticio', $c->fresh()->nombre);
            $this->assertSame(0, HistorialMontoCliente::count());
            $this->assertSame($publicaciones, MobileSyncPublication::count());
            $this->assertSame('fallido', ImportacionCliente::first()->estado);
        }
    }

    public function test_alias_y_duplicados_identicos(): void
    {
        $c = $this->cliente();
        $r = $this->importar("numero_cli,monto_venta_actua\n990001,1234.50\n990001,1234.50\n");
        $this->assertSame('1234.50', $c->fresh()->monto_venta_actual);
        $this->assertSame(1, $r['stats']['omitidas']);
        $this->assertSame(1, HistorialMontoCliente::count());
    }

    public static function montosInvalidos(): array
    {
        return array_map(fn ($v) => [$v], ['-', '-1', 'abc', '1e3', '1,23', '1.001', '10000000000.00', '1.000,00']);
    }

    #[DataProvider('montosInvalidos')]
    public function test_parser_rechaza_sin_coercion(string $valor): void
    {
        $this->expectException(ValidationException::class);
        app(MontoWizerp::class)->interpretar($valor);
    }

    public function test_parser_preserva_centavos(): void
    {
        $this->assertSame('9999999999.99', app(MontoWizerp::class)->interpretar('$9,999,999,999.99'));
        $this->assertSame('9999999999999.99', app(MontoWizerp::class)->interpretar('9999999999999.99', 'monto_credito_autorizado'));
        $this->assertNull(app(MontoWizerp::class)->interpretar(''));
    }

    public function test_error_de_fila_revierte_cliente_historial_y_publicacion_y_continua(): void
    {
        $c = $this->cliente();
        $antes = MobileSyncPublication::count();
        Event::listen('eloquent.creating: '.HistorialMontoCliente::class, fn () => null);
        $fallar = true;
        Event::listen('eloquent.updated: '.Cliente::class, function ($cliente) use (&$fallar) {
            if ($fallar && $cliente->numero_cliente === '990001') {
                $fallar = false;
                throw new \RuntimeException('Fallo ficticio posterior a escritura');
            }
        });
        $r = $this->importar("numero_cliente,nombre,codigo_lista,monto_venta_actual\n990001,Cambio,3,6000\n990002,Nuevo,2,100\n", true);
        $this->assertSame('1000.00', $c->fresh()->monto_venta_actual);
        $this->assertSame('Cliente ficticio', $c->fresh()->nombre);
        $this->assertSame(0, HistorialMontoCliente::count());
        $this->assertSame($antes + 1, MobileSyncPublication::count());
        $this->assertSame('parcial', $r['estado']);
        $this->assertSame(1, $r['stats']['creados']);
        $this->assertSame('parcial', ImportacionCliente::first()->estado);
        $this->assertDatabaseHas('errores_importacion_clientes', ['numero_fila' => 2, 'numero_cliente' => '990001', 'campo' => 'fila']);
    }

    public function test_autoridad_activa_protege_monto_lista_pero_no_estado(): void
    {
        $c = $this->cliente(['es_inactivo' => true]);
        $lista = $c->lista_actual_id;
        DB::table('configuraciones_sistema')->where('clave', EscalonamientoAutoridadConfig::CLAVE_AUTORIDAD)->update(['valor' => '1']);
        Cache::forget(EscalonamientoAutoridadConfig::CACHE_KEY);
        $this->assertFalse(config('escalonamiento.autoridad_activa'));
        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n990001,3,6000\n");
        $this->assertFalse($c->fresh()->es_inactivo);
        $this->assertSame('1000.00', $c->fresh()->monto_venta_actual);
        $this->assertSame($lista, $c->fresh()->lista_actual_id);
        $this->assertSame(0, HistorialMontoCliente::count());
    }

    public function test_csv_conserva_marcas_y_evidencias_de_solicitudes(): void
    {
        $c = $this->cliente();
        $estado = CatalogoEstadoSolicitud::create(['nombre' => 'Respondida', 'activo' => true]);
        $proceso = CatalogoProceso::create(['nombre' => 'CAMBIO DE LISTA', 'categoria_flujo' => 'financiero', 'activo' => true]);
        $s = SolicitudTag::create(['cliente_id' => $c->id, 'vendedor_id' => User::factory()->create()->id,
            'catalogo_proceso_id' => $proceso->id, 'catalogo_estado_solicitud_id' => $estado->id,
            'monto_cotizado' => 6000, 'monto_aplicado_al_cliente' => 3000, 'cubierto_por_carga_masiva' => true, 'pago_confirmado' => false]);
        $pagada = $s->replicate();
        $pagada->pago_confirmado = true;
        $pagada->cubierto_por_carga_masiva = false;
        $pagada->save();
        $antesPagada = $pagada->fresh()->getAttributes();
        $antes = $s->fresh()->getAttributes();
        $csv = "numero_cliente,monto_venta_actual\n990001,7000\n";
        $this->importar($csv);
        $this->importar($csv);
        $this->assertSame($antes, $s->fresh()->getAttributes());
        $this->assertSame($antesPagada, $pagada->fresh()->getAttributes());
    }

    public function test_exclusion_mutua(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->markTestSkipped('La exclusión MySQL se verifica desde conexiones independientes.');
        }
        $lock = Cache::lock('gelia.importacion.clientes.wizerp', 0);
        $lock->get();
        try {
            $this->expectException(ValidationException::class);
            $this->importar("numero_cliente,nombre,codigo_lista\n990001,Nuevo,3\n");
        } finally {
            $lock->release();
        }
    }

    public function test_fallo_fatal_conserva_filas_confirmadas_y_reporta_interrupcion(): void
    {
        $c = $this->cliente();
        $fallar = true;
        Event::listen('eloquent.updating: '.Cliente::class, function ($cliente) use (&$fallar) {
            if ($fallar && $cliente->numero_cliente === '990001') {
                $fallar = false;
                throw new \Error('Interrupción ficticia');
            }
        });
        try {
            $this->importar("numero_cliente,nombre,codigo_lista,monto_venta_actual\n990002,Nuevo,2,100\n990001,Cambio,3,6000\n990003,Sin aplicar,2,200\n", true);
            $this->fail('Debió detener la aplicación');
        } catch (\Error $e) {
            $this->assertSame('Interrupción ficticia', $e->getMessage());
        }
        $this->assertTrue(Cliente::where('numero_cliente', '990002')->exists());
        $this->assertFalse(Cliente::where('numero_cliente', '990003')->exists());
        $this->assertSame('1000.00', $c->fresh()->monto_venta_actual);
        $registro = ImportacionCliente::first();
        $this->assertSame('parcial', $registro->estado);
        $this->assertSame(1, $registro->resumen['creados']);
        $this->assertSame(1, $registro->resumen['errores']);
        $this->assertSame(1, $registro->resumen['no_aplicadas']);
    }
}
