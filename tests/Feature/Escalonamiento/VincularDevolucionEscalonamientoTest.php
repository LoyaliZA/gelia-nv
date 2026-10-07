<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\User;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\Excepciones\AcumuladoNegativoException;
use App\Services\Escalonamiento\Excepciones\CapacidadRemisionException;
use App\Services\Escalonamiento\Excepciones\VinculoDevolucionException;
use App\Services\Escalonamiento\ImportarDocumentosEscalonamiento;
use App\Services\Escalonamiento\ListarCandidatasRemisionVinculada;
use App\Services\Escalonamiento\RegistrarMovimiento;
use App\Services\Escalonamiento\RevertirAplicacionDevolucion;
use App\Services\Escalonamiento\VincularDevolucionARemision;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VincularDevolucionEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_octubre_recibe_el_neto_y_septiembre_no_cambia(): void
    {
        $cliente = $this->cliente('9301', participa: true);
        $importar = app(ImportarDocumentosEscalonamiento::class);
        $septiembre = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 9);
        $importar->capturar($septiembre, $this->remision('SEP-1', '2000.00', '2026-09-20'), null);

        $octubre = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar->capturar($octubre, $this->remision('OCT-1500', '1500.00', '2026-10-04'), null);
        $importar->capturar($octubre, $this->devolucion('D-1000', '1000.00', '2026-10-04', 'SEP-1'), null);

        $devolucion = DocumentoVenta::query()->where('folio', 'D-1000')->firstOrFail();
        $remision = DocumentoVenta::query()->where('folio', 'OCT-1500')->firstOrFail();
        $this->assertSame(0, EscalonamientoAplicacionDevolucion::count());
        $this->assertSame([], array_filter(
            app(ListarCandidatasRemisionVinculada::class)->listar($devolucion),
            fn (array $fila) => $fila['folio'] === 'SEP-1',
        ));

        app(VincularDevolucionARemision::class)->vincular($devolucion, $remision, 'Compra de octubre que absorbe la devolución', null);

        $this->assertSame('2000.00', (string) $this->resumen($septiembre, $cliente)->acumulado);
        $this->assertSame('500.00', (string) $this->resumen($octubre, $cliente)->acumulado);
        $this->assertSame('1500.00', (string) $remision->fresh()->total);
        $this->assertSame('aplicada', $devolucion->fresh()->estado);
        $this->assertSame(1, EscalonamientoMovimiento::query()->where('documento_venta_id', $remision->id)->count());
        $this->assertSame('-1000.00', (string) $devolucion->fresh()->movimiento->efecto);
        $this->assertSame(
            DocumentoVenta::query()->where('folio', 'SEP-1')->value('id'),
            EscalonamientoAplicacionDevolucion::query()->value('documento_venta_original_id'),
        );
        $this->assertSame('resuelta', EscalonamientoIncidencia::query()->where('codigo', 'pendiente_vinculacion')->value('estado'));
        $this->assertSame($cliente->lista_actual_id, $cliente->fresh()->lista_actual_id);
    }

    public function test_rechaza_compra_igual_o_menor_y_acepta_un_centavo(): void
    {
        $cliente = $this->cliente('9302', participa: true);
        $periodo = $this->cargarRemision('9302', 'R-1000', '1000.00');
        $igual = $this->cargarDevolucion($periodo, '9302', 'D-IGUAL', '1000.00');

        $this->expectException(CapacidadRemisionException::class);
        try {
            $this->vincular($igual, 'R-1000');
        } finally {
            $this->assertSame('1000.00', (string) $this->resumen($periodo, $cliente)->acumulado);
            $this->assertSame(0, EscalonamientoAplicacionDevolucion::query()->where('estado', 'activa')->count());
        }
    }

    public function test_un_centavo_de_compra_deja_un_centavo_de_neto(): void
    {
        $cliente = $this->cliente('9303', participa: true);
        $periodo = $this->cargarRemision('9303', 'R-CENT', '1000.01');
        $devolucion = $this->cargarDevolucion($periodo, '9303', 'D-CENT', '1000.00');

        $this->vincular($devolucion, 'R-CENT');

        $this->assertSame('0.01', (string) $this->resumen($periodo, $cliente)->acumulado);
    }

    public function test_la_segunda_devolucion_respeta_la_capacidad_aunque_la_primera_ya_ocupo_saldo(): void
    {
        $cliente = $this->cliente('9304', participa: true);
        $periodo = $this->cargarRemision('9304', 'R-1500', '1500.00');
        $primera = $this->cargarDevolucion($periodo, '9304', 'D-1000', '1000.00');
        $segunda = $this->cargarDevolucion($periodo, '9304', 'D-499', '499.99');
        $tercera = $this->cargarDevolucion($periodo, '9304', 'D-500', '500.00');

        $this->vincular($primera, 'R-1500');
        $this->vincular($segunda, 'R-1500');

        try {
            $this->vincular($tercera, 'R-1500');
            $this->fail('La tercera devolución no debió caber.');
        } catch (CapacidadRemisionException) {
            $this->assertSame('0.01', (string) $this->resumen($periodo, $cliente)->acumulado);
            $this->assertSame(2, EscalonamientoAplicacionDevolucion::query()->where('estado', 'activa')->count());
        }
    }

    public function test_dos_devoluciones_del_mismo_importe_no_reutilizan_la_capacidad(): void
    {
        $cliente = $this->cliente('9305', participa: true);
        $periodo = $this->cargarRemision('9305', 'R-1500', '1500.00');
        $primera = $this->cargarDevolucion($periodo, '9305', 'D-A', '1000.00');
        $segunda = $this->cargarDevolucion($periodo, '9305', 'D-B', '1000.00');

        $this->vincular($primera, 'R-1500');

        try {
            $this->vincular($segunda, 'R-1500');
            $this->fail('La segunda devolución no debió reutilizar la capacidad.');
        } catch (CapacidadRemisionException) {
            $this->assertSame('500.00', (string) $this->resumen($periodo, $cliente)->acumulado);
        }
    }

    public function test_sin_remision_vinculada_el_acumulado_sigue_en_cero_y_el_previo_no_sustituye_la_compra(): void
    {
        $cliente = $this->cliente('9306', participa: true);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'ajuste',
            'folio' => 'AJ-1',
            'total' => '5000.00',
            'efecto' => '5000.00',
            'fecha_emision' => '2026-10-02',
        ]);
        $devolucion = $this->cargarDevolucion($periodo, '9306', 'D-SOLA', '1000.00');
        $candidatas = app(ListarCandidatasRemisionVinculada::class)->listar($devolucion);

        $this->assertSame([], $candidatas);
        $this->assertSame('pendiente_de_vinculacion', $devolucion->fresh()->estado);
        $this->assertNull($devolucion->fresh()->movimiento);
        $this->assertSame('5000.00', (string) $this->resumen($periodo, $cliente)->acumulado);
    }

    public function test_una_devolucion_posterior_resta_una_sola_vez_la_compra_ya_contabilizada(): void
    {
        $cliente = $this->cliente('9307', participa: true);
        $periodo = $this->cargarRemision('9307', 'R-YA', '1500.00');
        $antes = EscalonamientoMovimiento::query()->count();
        $devolucion = $this->cargarDevolucion($periodo, '9307', 'D-YA', '1000.00');

        $this->vincular($devolucion, 'R-YA');
        try {
            app(VincularDevolucionARemision::class)->vincular(
                $devolucion->fresh(),
                DocumentoVenta::query()->where('folio', 'R-YA')->firstOrFail(),
                'Evidencia de la compra que absorbe la devolución',
                null,
            );
            $this->fail('Una devolución ya vinculada no debe descontarse otra vez.');
        } catch (VinculoDevolucionException) {
            $this->assertSame($antes + 1, EscalonamientoMovimiento::query()->count());
            $this->assertSame('500.00', (string) $this->resumen($periodo, $cliente)->acumulado);
        }
    }

    public function test_revertir_dos_veces_no_duplica_el_efecto(): void
    {
        $cliente = $this->cliente('9308', participa: true);
        $periodo = $this->cargarRemision('9308', 'R-REV', '1500.00');
        $devolucion = $this->cargarDevolucion($periodo, '9308', 'D-REV', '1000.00');
        $aplicacion = $this->vincular($devolucion, 'R-REV');

        app(RevertirAplicacionDevolucion::class)->revertir($aplicacion, null);
        $this->assertSame('1500.00', (string) $this->resumen($periodo, $cliente)->acumulado);
        $this->assertSame('0.00', (string) $devolucion->fresh()->movimiento->efecto);
        $this->assertSame('pendiente_de_vinculacion', $devolucion->fresh()->estado);

        $this->expectException(VinculoDevolucionException::class);
        app(RevertirAplicacionDevolucion::class)->revertir($aplicacion->fresh(), null);
    }

    public function test_cancelar_la_devolucion_revierte_el_descuento_una_sola_vez(): void
    {
        $cliente = $this->cliente('9309', participa: true);
        $periodo = $this->cargarRemision('9309', 'R-CAN', '1500.00');
        $devolucion = $this->cargarDevolucion($periodo, '9309', 'D-CAN', '1000.00');
        $this->vincular($devolucion, 'R-CAN');
        $importar = app(ImportarDocumentosEscalonamiento::class);

        $cancelada = $this->devolucion('D-CAN', '1000.00', '2026-10-04', 'ORIG', 'cancelado', '9309');
        $importar->capturar($periodo, $cancelada, null);
        $importar->capturar($periodo, $cancelada, null);

        $this->assertSame('1500.00', (string) $this->resumen($periodo, $cliente)->acumulado);
        $this->assertSame('cancelado', $devolucion->fresh()->estado);
        $this->assertSame('cancelada', EscalonamientoAplicacionDevolucion::query()->value('estado'));
    }

    public function test_la_venta_original_no_puede_absorber_la_devolucion(): void
    {
        $this->cliente('9310', participa: true);
        $periodo = $this->cargarRemision('9310', 'R-ORIG', '1500.00');
        $devolucion = $this->cargarDevolucion($periodo, '9310', 'D-ORIG', '1000.00', 'R-ORIG');

        $this->assertSame([], app(ListarCandidatasRemisionVinculada::class)->listar($devolucion));
        $this->expectException(VinculoDevolucionException::class);
        $this->vincular($devolucion, 'R-ORIG');
    }

    public function test_un_descuento_que_baja_la_clasificacion_deja_irregularidad_sin_tocar_la_lista_operativa(): void
    {
        $bronce = CatalogoListaDescuento::create([
            'nombre' => 'BRONCE',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $plata = CatalogoListaDescuento::create([
            'nombre' => 'PLATA',
            'monto_requerido' => 5000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '9311',
            'nombre' => 'Cliente 9311',
            'lista_actual_id' => $bronce->id,
            'monto_venta_actual' => 40,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(ImportarDocumentosEscalonamiento::class)->capturar($periodo, $this->remision('R-ALTA', '6000.00', '2026-10-04', '9311'), null);
        $this->assertSame($plata->id, $this->resumen($periodo, $cliente)->clasificacion_mes_id);

        $devolucion = $this->cargarDevolucion($periodo, '9311', 'D-BAJA', '2000.00');
        $this->vincular($devolucion, 'R-ALTA');

        $resumen = $this->resumen($periodo, $cliente);
        $this->assertSame('4000.00', (string) $resumen->acumulado);
        $this->assertSame($bronce->id, $resumen->clasificacion_mes_id);
        $this->assertSame($plata->id, $resumen->clasificacion_mes_max_id);
        $this->assertTrue(EscalonamientoIncidencia::query()->where('codigo', 'irregularidad_mantenimiento')->exists());
        $this->assertSame($bronce->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_lista_que_no_participa_conserva_el_documento_sin_sumar(): void
    {
        $cliente = $this->cliente('9312', participa: false);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $cliente->listaDescuento->update(['participa_escalonamiento' => true]);

        app(ImportarDocumentosEscalonamiento::class)->capturar(
            $periodo,
            $this->remision('R-FUERA', '800.00', '2026-10-05', '9312'),
            null,
        );

        $documento = DocumentoVenta::query()->where('folio', 'R-FUERA')->firstOrFail();
        $this->assertSame('800.00', (string) $documento->total);
        $this->assertNull($documento->movimiento);
        $this->assertNull(EscalonamientoResumenCliente::query()->where('cliente_id', $cliente->id)->first());
        $this->assertTrue(EscalonamientoIncidencia::query()->where('codigo', 'exclusion_lealtad')->exists());
        $this->assertEquals(10.0, (float) $cliente->fresh()->monto_venta_actual);
    }

    public function test_el_ledger_no_suma_una_remision_directa_de_quien_no_participa(): void
    {
        $cliente = $this->cliente('9313', participa: false);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-DIRECTA',
            'total' => '900.00',
            'efecto' => '900.00',
            'fecha_emision' => '2026-10-06',
        ]);

        $this->assertSame('0.00', (string) EscalonamientoMovimiento::query()->value('efecto'));
        $this->assertNull(EscalonamientoResumenCliente::query()->first());
    }

    public function test_rechaza_el_descuento_si_el_acumulado_quedaria_negativo(): void
    {
        $cliente = $this->cliente('9314', participa: true);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-PARCIAL',
            'total' => '1500.00',
            'efecto' => '400.00',
            'fecha_emision' => '2026-10-04',
        ]);
        $devolucion = $this->cargarDevolucion($periodo, '9314', 'D-NEG', '1000.00');

        $this->expectException(AcumuladoNegativoException::class);
        try {
            $this->vincular($devolucion, 'R-PARCIAL');
        } finally {
            $this->assertSame('400.00', (string) $this->resumen($periodo, $cliente)->acumulado);
            $this->assertSame(0, EscalonamientoAplicacionDevolucion::query()->count());
        }
    }

    public function test_la_pantalla_vincula_con_permiso_y_bloquea_sin_el(): void
    {
        $this->cliente('9315', participa: true);
        $periodo = $this->cargarRemision('9315', 'R-HTTP', '1500.00');
        $devolucion = $this->cargarDevolucion($periodo, '9315', 'D-HTTP', '1000.00');
        $remision = DocumentoVenta::query()->where('folio', 'R-HTTP')->firstOrFail();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $operador = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['escalonamiento.ver', 'escalonamiento.operar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $operador->givePermissionTo($permiso);
        }

        $this->actingAs($operador)
            ->getJson(route('escalonamiento.devoluciones.candidatas', $devolucion))
            ->assertOk()
            ->assertJsonPath('candidatas.0.folio', 'R-HTTP');
        $this->assertSame(0, EscalonamientoAplicacionDevolucion::query()->count());

        $this->actingAs($operador)
            ->post(route('escalonamiento.devoluciones.vincular', $devolucion), [
                'remision_vinculada_id' => $remision->id,
                'evidencia' => 'Selección confirmada en la bandeja',
            ])
            ->assertRedirect(route('escalonamiento.index'));

        $this->assertSame('500.00', (string) EscalonamientoResumenCliente::query()->where('escalonamiento_periodo_id', $periodo->id)->value('acumulado'));

        $consulta = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('escalonamiento.ver', 'web');
        $consulta->givePermissionTo('escalonamiento.ver');

        $this->actingAs($consulta)
            ->post(route('escalonamiento.aplicaciones.revertir', EscalonamientoAplicacionDevolucion::query()->value('id')))
            ->assertForbidden();
    }

    private function cliente(string $numero, bool $participa): Cliente
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'LISTA '.$numero,
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => $participa,
        ]);

        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente '.$numero,
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 10,
        ]);
    }

    private function cargarRemision(string $numero, string $folio, string $total): EscalonamientoPeriodo
    {
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(ImportarDocumentosEscalonamiento::class)->capturar($periodo, $this->remision($folio, $total, '2026-10-04', $numero), null);

        return $periodo;
    }

    private function cargarDevolucion(
        EscalonamientoPeriodo $periodo,
        string $numero,
        string $folio,
        string $total,
        string $original = 'ORIG',
    ): DocumentoVenta {
        app(ImportarDocumentosEscalonamiento::class)->capturar(
            $periodo,
            $this->devolucion($folio, $total, '2026-10-04', $original, 'activo', $numero),
            null,
        );

        return DocumentoVenta::query()->where('folio', $folio)->firstOrFail();
    }

    private function vincular(DocumentoVenta $devolucion, string $folioRemision): EscalonamientoAplicacionDevolucion
    {
        $remision = DocumentoVenta::query()->where('folio', $folioRemision)->firstOrFail();

        try {
            return app(VincularDevolucionARemision::class)->vincular(
                $devolucion,
                $remision,
                'Evidencia de la compra que absorbe la devolución',
                null,
            );
        } catch (VinculoDevolucionException $excepcion) {
            if (! str_contains($excepcion->getMessage(), 'ya tiene un vínculo')) {
                throw $excepcion;
            }

            return EscalonamientoAplicacionDevolucion::query()
                ->where('documento_devolucion_id', $devolucion->id)
                ->where('estado', 'activa')
                ->firstOrFail();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function remision(string $folio, string $total, string $fecha, string $numero = '9301'): array
    {
        return [
            'tipo' => 'remision',
            'folio' => $folio,
            'numero_cliente' => $numero,
            'moneda' => 'MXN',
            'total' => $total,
            'fecha_emision' => $fecha,
            'estado' => 'activo',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function devolucion(
        string $folio,
        string $total,
        string $fecha,
        string $original,
        string $estado = 'activo',
        string $numero = '9301',
    ): array {
        return [
            'tipo' => 'devolucion',
            'folio' => $folio,
            'numero_cliente' => $numero,
            'moneda' => 'MXN',
            'total' => $total,
            'fecha_emision' => $fecha,
            'estado' => $estado,
            'remision_original' => $original,
        ];
    }

    private function resumen(EscalonamientoPeriodo $periodo, Cliente $cliente): EscalonamientoResumenCliente
    {
        return EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->firstOrFail();
    }
}
