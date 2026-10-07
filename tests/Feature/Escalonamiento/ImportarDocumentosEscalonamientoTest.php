<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\DocumentoVentaRevision;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\User;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\ImportarDocumentosEscalonamiento;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ImportarDocumentosEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_repetir_el_csv_no_cambia_el_acumulado_y_una_revision_aplica_la_diferencia(): void
    {
        Storage::fake('local');
        [$cliente, $lista] = $this->cliente('9101', 250);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $primera = $importar->previsualizar($periodo, $this->csv('remisiones.csv', [
            ['remision', 'A-100', 'MTY', '01', '9101', 'Cliente Remision', 'MXN', '1000.00', '2026-10-03', 'activo', ''],
        ]), null, 'remision');
        $importar->confirmar($primera->id, null);

        $segunda = $importar->previsualizar($periodo, $this->csv('remisiones.csv', [
            ['remision', 'A-100', 'MTY', '01', '9101', 'Cliente Remision', 'MXN', '1000.00', '2026-10-03', 'activo', ''],
        ]), null, 'remision');
        $importar->confirmar($segunda->id, null);

        $this->assertSame(1, DocumentoVenta::count());
        $this->assertSame(1, EscalonamientoMovimiento::count());
        $this->assertEquals('1000.00', (string) EscalonamientoResumenCliente::first()->acumulado);

        $tercera = $importar->previsualizar($periodo, $this->csv('remisiones.csv', [
            ['remision', 'A-100', 'MTY', '01', '9101', 'Cliente Remision', 'MXN', '1500.00', '2026-10-03', 'activo', ''],
        ]), null, 'remision');
        $this->assertSame('revision', $tercera->filas->first()->resultado);
        $importar->confirmar($tercera->id, null);

        $this->assertSame(1, DocumentoVenta::count());
        $this->assertSame(1, DocumentoVentaRevision::count());
        $this->assertEquals('1500.00', (string) EscalonamientoResumenCliente::first()->acumulado);
        $resumen = EscalonamientoResumenCliente::first();
        $this->assertSame($lista->id, $resumen->clasificacion_mes_id);
        $this->assertSame($lista->id, $resumen->lista_vigente_id);
        $cliente->refresh();
        $this->assertEquals(250.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($lista->id, $cliente->lista_actual_id);
    }

    public function test_moneda_ajena_otro_mes_y_cliente_desconocido_no_suman(): void
    {
        Storage::fake('local');
        $this->cliente('9102', 80);
        Cliente::create([
            'numero_cliente' => '9103',
            'nombre' => 'Ana López',
            'lista_actual_id' => CatalogoListaDescuento::query()->value('id'),
            'monto_venta_actual' => 10,
        ]);
        Cliente::create([
            'numero_cliente' => '9104',
            'nombre' => 'Ana Ruiz',
            'lista_actual_id' => CatalogoListaDescuento::query()->value('id'),
            'monto_venta_actual' => 10,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $importacion = $importar->previsualizar($periodo, $this->csv('mezcla.csv', [
            ['remision', 'USD-1', '', '', '9102', '', 'USD', '400.00', '2026-10-02', 'activo', ''],
            ['remision', 'SEP-1', '', '', '9102', '', 'MXN', '300.00', '2026-09-30', 'activo', ''],
            ['remision', 'ANA-1', '', '', '', 'Ana', 'MXN', '200.00', '2026-10-02', 'activo', ''],
        ]), null, 'remision');
        $importar->confirmar($importacion->id, null);

        $this->assertSame(1, DocumentoVenta::where('estado', 'excluido')->count());
        $this->assertSame(1, EscalonamientoMovimiento::count());
        $this->assertSame(1, EscalonamientoResumenCliente::count());
        $this->assertTrue(EscalonamientoIncidencia::where('codigo', 'moneda_no_soportada')->exists());
        $this->assertFalse(EscalonamientoIncidencia::where('codigo', 'otro_periodo')->exists());
        $periodoSep = EscalonamientoPeriodo::where('anio', 2026)->where('mes', 9)->first();
        $this->assertNotNull($periodoSep);
        $this->assertSame(EscalonamientoPeriodo::ESTADO_HISTORIAL, $periodoSep->estado);
        $this->assertTrue(DocumentoVenta::where('folio', 'SEP-1')->exists());
        $this->assertEquals('300.00', (string) EscalonamientoResumenCliente::where('escalonamiento_periodo_id', $periodoSep->id)->value('acumulado'));
        $incidencia = EscalonamientoIncidencia::where('codigo', 'cliente_no_identificado')->first();
        $this->assertNotNull($incidencia);
        $this->assertStringContainsString('9103', $incidencia->motivo);
        $this->assertStringContainsString('9104', $incidencia->motivo);
        $this->assertNull(DocumentoVenta::where('folio', 'ANA-1')->first());
    }

    public function test_la_devolucion_se_guarda_sin_descontar_el_acumulado(): void
    {
        Storage::fake('local');
        $this->cliente('9105', 90);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $importacion = $importar->previsualizar($periodo, $this->csv('devolucion.csv', [
            ['remision', 'R-1500', 'MTY', '01', '9105', '', 'MXN', '1500.00', '2026-10-04', 'activo', ''],
            ['devolucion', 'D-1000', '', '', '9105', '', 'MXN', '1000.00', '2026-10-04', 'activo', 'R-1500'],
        ]), null, 'remision');
        $importar->confirmar($importacion->id, null);

        $this->assertEquals('1500.00', (string) EscalonamientoResumenCliente::first()->acumulado);
        $this->assertSame(1, EscalonamientoMovimiento::count());
        $devolucion = DocumentoVenta::where('tipo', 'devolucion')->first();
        $this->assertSame('pendiente_de_vinculacion', $devolucion->estado);
        $this->assertSame('R-1500', $devolucion->remision_original);
        $this->assertNull($devolucion->movimiento);
        $this->assertTrue(EscalonamientoIncidencia::where('codigo', 'pendiente_vinculacion')->exists());
    }

    public function test_el_reporte_de_remisiones_registra_el_nombre_unico_y_conserva_el_expediente(): void
    {
        Storage::fake('local');
        $pagada = $this->clienteConNombre('9201', 'ZAMORA CADENA NALLELY');
        $vigente = $this->clienteConNombre('9202', 'RUIZ MENDEZ JUANA');
        $cancelada = $this->clienteConNombre('9203', 'CLIENTE PRUEBA 1');
        $this->clienteConNombre('9204', 'Público General');
        $this->clienteConNombre('9205', 'PUBLICO GENERAL');
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 9);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $previa = $importar->previsualizar($periodo, $this->reporteRemisiones(), null, 'remision');
        $this->assertCount(124, $previa->filas);
        foreach ($previa->filas as $fila) {
            $motivo = (string) $fila->motivo;
            $this->assertStringNotContainsString('El tipo debe ser', $motivo);
            $this->assertStringNotContainsString('La fecha no es válida', $motivo);
        }

        $importar->confirmar($previa->id, null);

        $docPagada = DocumentoVenta::where('folio', '62704')->first();
        $this->assertNotNull($docPagada);
        $this->assertSame($pagada->id, $docPagada->cliente_id);
        $this->assertSame('activo', $docPagada->estado);
        $this->assertSame('archivo', $docPagada->origen);
        $this->assertSame('Matriz', $docPagada->sucursal);
        $this->assertNull($docPagada->remision_original);
        $this->assertEquals('1387.76', (string) $docPagada->total);
        $this->assertSame('1387.76', $docPagada->datos_fuente['subtotal']);
        $this->assertSame('Pagada', $docPagada->datos_fuente['status_pago']);
        $this->assertSame('Activa', $docPagada->datos_fuente['status']);
        $this->assertStringContainsString('18:06', $docPagada->datos_fuente['fecha_hora']);
        $this->assertSame('POS', $docPagada->datos_fuente['origen_documento']);
        $this->assertSame('ZAMORA CADENA NALLELY', $docPagada->datos_fuente['nombre']);
        $this->assertEquals('22551.91', (string) EscalonamientoResumenCliente::where('cliente_id', $pagada->id)->value('acumulado'));
        $pagada->refresh();
        $this->assertEquals(40.0, (float) $pagada->monto_venta_actual);

        $docVigente = DocumentoVenta::where('folio', '62686')->first();
        $this->assertNotNull($docVigente);
        $this->assertSame($vigente->id, $docVigente->cliente_id);
        $this->assertSame('Vigente', $docVigente->datos_fuente['status_pago']);
        $this->assertSame('Crédito', $docVigente->datos_fuente['condicion_pago']);
        $this->assertEquals('3760.03', (string) EscalonamientoResumenCliente::where('cliente_id', $vigente->id)->value('acumulado'));

        $docCancelada = DocumentoVenta::where('folio', '18')->where('sucursal', 'CITY CENTER')->first();
        $this->assertNotNull($docCancelada);
        $this->assertSame($cancelada->id, $docCancelada->cliente_id);
        $this->assertSame('cancelado', $docCancelada->estado);
        $this->assertEquals('45.77', (string) $docCancelada->total);
        $this->assertNull($docCancelada->movimiento);
        $this->assertNull(EscalonamientoResumenCliente::where('cliente_id', $cancelada->id)->first());

        $this->assertNull(DocumentoVenta::where('folio', '23')->first());
        $this->assertNull(DocumentoVenta::where('folio', '62609')->first());
        $ambigua = EscalonamientoIncidencia::query()
            ->where('codigo', 'cliente_no_identificado')
            ->where('motivo', 'like', '%Público General%')
            ->first();
        $this->assertNotNull($ambigua);
        $this->assertStringContainsString('9204', $ambigua->motivo);
        $this->assertStringContainsString('9205', $ambigua->motivo);
        $desconocida = EscalonamientoIncidencia::query()
            ->where('codigo', 'cliente_no_identificado')
            ->where('motivo', 'like', '%CC PG%')
            ->first();
        $this->assertNotNull($desconocida);
        $this->assertStringNotContainsString('coincide con varios', $desconocida->motivo);

        $movimientos = EscalonamientoMovimiento::count();
        $segunda = $importar->previsualizar($periodo, $this->reporteRemisiones(), null, 'remision');
        $importar->confirmar($segunda->id, null);

        $this->assertEquals('22551.91', (string) EscalonamientoResumenCliente::where('cliente_id', $pagada->id)->value('acumulado'));
        $this->assertEquals('3760.03', (string) EscalonamientoResumenCliente::where('cliente_id', $vigente->id)->value('acumulado'));
        $this->assertSame($movimientos, EscalonamientoMovimiento::count());
        $this->assertSame(1, DocumentoVenta::where('folio', '62704')->count());
    }

    public function test_la_pantalla_confirma_una_carga_y_bloquea_sin_permiso(): void
    {
        Storage::fake('local');
        $this->cliente('9106', 40);
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['escalonamiento.ver', 'escalonamiento.operar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs($user)->post(route('escalonamiento.periodos.abrir'), ['anio' => 2026, 'mes' => 10]);

        $this->actingAs($user)
            ->post(route('escalonamiento.importaciones.previsualizar'), [
                'tipo' => 'remision',
                'archivo' => $this->csv('carga.csv', [
                    ['remision', 'M-1', '', '02', '9106', '', 'MXN', '800.00', '2026-10-05', 'activo', ''],
                ]),
            ])
            ->assertRedirect();

        $importacionId = \App\Models\Escalonamiento\EscalonamientoImportacion::query()->value('id');

        $this->actingAs($user)
            ->post(route('escalonamiento.importaciones.confirmar'), ['importacion_id' => $importacionId])
            ->assertRedirect(route('escalonamiento.index'));

        $this->assertEquals('800.00', (string) EscalonamientoResumenCliente::first()->acumulado);

        $sinPermiso = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('escalonamiento.ver', 'web');
        $sinPermiso->givePermissionTo('escalonamiento.ver');

        $this->actingAs($sinPermiso)
            ->post(route('escalonamiento.capturas.store'), [
                'tipo' => 'remision',
                'folio' => 'M-2',
                'numero_cliente' => '9106',
                'total' => '50',
                'fecha_emision' => '2026-10-06',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Cliente, 1: CatalogoListaDescuento}
     */
    private function cliente(string $numero, float $monto): array
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO '.$numero,
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente '.$numero,
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => $monto,
        ]);

        return [$cliente, $lista];
    }

    private function clienteConNombre(string $numero, string $nombre): Cliente
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO '.$numero,
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);

        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => $nombre,
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 40,
        ]);
    }

    public function test_reporte_erp_con_fila_titulo_y_tipo_seleccionado(): void
    {
        Storage::fake('local');
        $ruta = base_path('docs/operaciones/Remisiones (2).xlsx');
        if (! is_readable($ruta)) {
            $this->markTestSkipped('Falta el archivo de operaciones Remisiones (2).xlsx');
        }

        $this->clienteConNombre('9300', 'GUTIERREZ ORTEGA ULISES');
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $archivo = new UploadedFile(
            $ruta,
            'Remisiones (2).xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $previa = $importar->previsualizar($periodo, $archivo, null, 'remision');
        $this->assertGreaterThan(0, $previa->filas->count());
        foreach ($previa->filas as $fila) {
            $this->assertStringNotContainsString('El tipo debe ser', (string) $fila->motivo);
        }
        $this->assertTrue($previa->filas->contains(fn ($fila) => in_array($fila->resultado, ['alta', 'identico', 'incidencia', 'excluido'], true)));
    }

    public function test_csv_con_fila_titulo_detecta_cabecera_en_segunda_linea(): void
    {
        Storage::fake('local');
        $this->cliente('9310', 50);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $contenido = "Remisiones\n";
        $contenido .= "tipo,folio,serie,sucursal,numero_cliente,nombre,moneda,total,fecha,estado,remision_original\n";
        $contenido .= "remision,TIT-1,,,9310,,MXN,120.00,2026-10-08,activo,\n";

        $archivo = UploadedFile::fake()->createWithContent('titulo.csv', $contenido);
        $previa = $importar->previsualizar($periodo, $archivo, null, 'remision');
        $this->assertSame('alta', $previa->filas->first()->resultado);
        $this->assertSame('TIT-1', $previa->filas->first()->interpretacion['folio'] ?? null);
    }

    public function test_tipo_devolucion_en_reporte_erp_marca_pendiente(): void
    {
        Storage::fake('local');
        $this->cliente('9311', 60);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $contenido = "Devoluciones\n";
        $contenido .= "Folio,Fecha,Cliente,Sucursal,Moneda,Subtotal,Descuento,I.V.A.,Imp. IEPS,Ret. I.V.A.,Ret. I.S.R.,Ret. IEPS,Total\n";
        $contenido .= "9001,2026-10-04 12:00,Cliente 9311,Matriz,MXN,0,0,0,0,0,0,0,80.00\n";

        $archivo = UploadedFile::fake()->createWithContent('dev-erp.csv', $contenido);
        $previa = $importar->previsualizar($periodo, $archivo, null, 'devolucion');
        $this->assertSame('pendiente', $previa->filas->first()->resultado);
    }

    private function reporteRemisiones(): UploadedFile
    {
        return new UploadedFile(
            base_path('docs/operaciones/Remisiones1.xlsx'),
            'Remisiones1.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    /**
     * @param  list<list<string>>  $filas
     */
    private function csv(string $nombre, array $filas): UploadedFile
    {
        $lineas = ['tipo,folio,serie,sucursal,numero_cliente,nombre,moneda,total,fecha,estado,remision_original'];
        foreach ($filas as $fila) {
            $lineas[] = implode(',', $fila);
        }

        return UploadedFile::fake()->createWithContent($nombre, implode("\n", $lineas));
    }

    public function test_remision_historica_duplicada_marca_historial_identico(): void
    {
        Storage::fake('local');
        $this->cliente('9110', 120);
        $oct = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $primera = $importar->previsualizar($oct, $this->csv('hist.csv', [
            ['remision', 'H-1', '', '', '9110', '', 'MXN', '200.00', '2026-09-15', 'activo', ''],
        ]), null, 'remision');
        $importar->confirmar($primera->id, null);

        $segunda = $importar->previsualizar($oct, $this->csv('hist2.csv', [
            ['remision', 'H-1', '', '', '9110', '', 'MXN', '200.00', '2026-09-15', 'activo', ''],
        ]), null, 'remision');
        $this->assertSame('historial_identico', $segunda->filas->first()->resultado);
    }

    public function test_fecha_futura_genera_incidencia_fecha_futura(): void
    {
        Storage::fake('local');
        $this->cliente('9111', 130);
        $oct = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $importacion = $importar->previsualizar($oct, $this->csv('futuro.csv', [
            ['remision', 'F-1', '', '', '9111', '', 'MXN', '100.00', '2026-11-02', 'activo', ''],
        ]), null, 'remision');
        $importar->confirmar($importacion->id, null);

        $nov = EscalonamientoPeriodo::where('anio', 2026)->where('mes', 11)->first();
        $this->assertNotNull($nov);
        $doc = DocumentoVenta::where('folio', 'F-1')->first();
        $this->assertNotNull($doc);
        $this->assertSame('pendiente_revision', $doc->estado);
        $this->assertNull($doc->movimiento);
        $this->assertTrue(EscalonamientoIncidencia::where('codigo', 'documento_anticipado')->exists());
    }

    public function test_periodo_cerrado_genera_documento_tardio(): void
    {
        Storage::fake('local');
        $this->cliente('9112', 140);
        $sep = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 9);
        $sep->estado = EscalonamientoPeriodo::ESTADO_CERRADO;
        $sep->save();

        $oct = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $importacion = $importar->previsualizar($oct, $this->csv('tardio.csv', [
            ['remision', 'T-1', '', '', '9112', '', 'MXN', '100.00', '2026-09-10', 'activo', ''],
        ]), null, 'remision');
        $importar->confirmar($importacion->id, null);

        $this->assertTrue(EscalonamientoIncidencia::where('codigo', 'documento_tardio')->exists());
        $this->assertSame(0, DocumentoVenta::where('folio', 'T-1')->count());
    }
}
