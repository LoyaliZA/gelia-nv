<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoCambioLista;
use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\User;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\AsegurarPeriodoHistoricoEscalonamiento;
use App\Services\Escalonamiento\AplicarCierreEscalonamiento;
use App\Services\Escalonamiento\AutorizarCierreEscalonamiento;
use App\Services\Escalonamiento\CancelarSimulacionCierreEscalonamiento;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;
use App\Services\Escalonamiento\RegistrarMovimiento;
use App\Services\Escalonamiento\SimularCierreEscalonamiento;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CierreEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['escalonamiento.ver', 'escalonamiento.operar', 'escalonamiento.autorizar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->operador = User::factory()->create();
        $this->operador->givePermissionTo(['escalonamiento.ver', 'escalonamiento.operar']);

        $this->supervisor = User::factory()->create();
        $this->supervisor->givePermissionTo(['escalonamiento.ver', 'escalonamiento.operar', 'escalonamiento.autorizar']);
    }

    public function test_aplicar_cierre_es_idempotente_y_abre_el_mes_siguiente(): void
    {
        [$publico, $plata] = $this->listasBase();
        $cliente = Cliente::create([
            'numero_cliente' => '8801',
            'nombre' => 'Cliente Cierre',
            'lista_actual_id' => $plata->id,
            'monto_venta_actual' => 1500,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 5);
        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'M-1',
            'total' => '200.00',
            'efecto' => '200.00',
            'fecha_emision' => '2026-05-10',
        ]);

        $cierre = $this->cerrarPeriodo($periodo, $this->supervisor);

        $cliente->refresh();
        $this->assertSame('0.00', (string) $cliente->monto_venta_actual);
        $this->assertSame(1, EscalonamientoCambioLista::query()->where('escalonamiento_cierre_id', $cierre->id)->count());

        $junio = EscalonamientoPeriodo::query()->where('anio', 2026)->where('mes', 6)->first();
        $this->assertNotNull($junio);
        $this->assertSame(EscalonamientoPeriodo::ESTADO_ABIERTO, $junio->estado);

        $resumenJunio = EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $junio->id)
            ->where('cliente_id', $cliente->id)
            ->first();
        $this->assertNotNull($resumenJunio);
        $this->assertSame('0.00', (string) $resumenJunio->acumulado);

        app(AplicarCierreEscalonamiento::class)->aplicar($periodo, $this->supervisor);
        $this->assertSame(1, EscalonamientoCambioLista::query()->where('escalonamiento_cierre_id', $cierre->id)->count());
    }

    public function test_tercer_mes_sin_compra_propone_inactividad_y_pg(): void
    {
        [$publico, $plata] = $this->listasBase();
        config(['escalonamiento.lista_publico_general_id' => $publico->id]);

        $cliente = Cliente::create([
            'numero_cliente' => '8802',
            'nombre' => 'Cliente Inactivo',
            'lista_actual_id' => $plata->id,
            'escalonamiento_meses_sin_compra' => 2,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 8);
        $cierre = app(SimularCierreEscalonamiento::class)->simular($periodo, $this->operador)['cierre'];

        $detalle = $cierre->detalles()->where('cliente_id', $cliente->id)->first();
        $this->assertNotNull($detalle);
        $this->assertTrue($detalle->propone_inactivo);
        $this->assertSame(3, $detalle->meses_sin_compra);
        $this->assertSame($publico->id, $detalle->lista_siguiente_id);

        app(AutorizarCierreEscalonamiento::class)->autorizar($periodo->fresh(), $this->supervisor);
        app(AplicarCierreEscalonamiento::class)->aplicar($periodo->fresh(), $this->supervisor);

        $cliente->refresh();
        $this->assertTrue($cliente->es_inactivo);
        $this->assertSame($publico->id, $cliente->lista_actual_id);
    }

    public function test_cliente_bloqueado_no_baja_a_pg_por_inactividad(): void
    {
        [$publico, $plata] = $this->listasBase();
        config(['escalonamiento.lista_publico_general_id' => $publico->id]);

        $cliente = Cliente::create([
            'numero_cliente' => '8803',
            'nombre' => 'Colaborador',
            'lista_actual_id' => $plata->id,
            'lista_bloqueada' => true,
            'escalonamiento_meses_sin_compra' => 2,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 9);
        $cierre = app(SimularCierreEscalonamiento::class)->simular($periodo, $this->operador)['cierre'];
        $detalle = $cierre->detalles()->where('cliente_id', $cliente->id)->first();

        $this->assertNotNull($detalle);
        $this->assertFalse($detalle->propone_inactivo);
        $this->assertSame('bloqueado', $detalle->motivo);
    }

    public function test_cierre_de_historial_no_altera_el_periodo_operativo(): void
    {
        [, $plata] = $this->listasBase();
        $cliente = Cliente::create([
            'numero_cliente' => '8804',
            'nombre' => 'Cliente Historial',
            'lista_actual_id' => $plata->id,
            'monto_venta_actual' => 1500,
        ]);

        $octubre = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $octubre->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'OCT-1',
            'total' => '300.00',
            'efecto' => '300.00',
            'fecha_emision' => '2026-10-04',
        ]);

        $septiembre = app(AsegurarPeriodoHistoricoEscalonamiento::class)->asegurar(2026, 9);
        $this->assertSame(EscalonamientoPeriodo::ESTADO_HISTORIAL, $septiembre->estado);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $septiembre->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'SEP-1',
            'total' => '200.00',
            'efecto' => '200.00',
            'fecha_emision' => '2026-09-12',
        ]);

        $acumuladoOctubre = (string) EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $octubre->id)
            ->where('cliente_id', $cliente->id)
            ->value('acumulado');

        $cierre = $this->cerrarPeriodo($septiembre, $this->supervisor);

        $septiembre->refresh();
        $octubre->refresh();
        $cliente->refresh();

        $this->assertSame(EscalonamientoPeriodo::ESTADO_CERRADO, $septiembre->estado);
        $this->assertSame(EscalonamientoPeriodo::ESTADO_ABIERTO, $octubre->estado);
        $this->assertTrue($cierre->reconstruccion);
        $this->assertSame(0, EscalonamientoCambioLista::query()->where('escalonamiento_cierre_id', $cierre->id)->count());
        $this->assertSame('1500.00', number_format((float) $cliente->monto_venta_actual, 2, '.', ''));
        $this->assertSame($plata->id, $cliente->lista_actual_id);

        $resumenOctubre = EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $octubre->id)
            ->where('cliente_id', $cliente->id)
            ->first();
        $this->assertNotNull($resumenOctubre);
        $this->assertSame($acumuladoOctubre, (string) $resumenOctubre->acumulado);
        $this->assertNotNull($resumenOctubre->lista_base_id);
    }

    public function test_cancelar_simulacion_de_historial_restaura_historial(): void
    {
        $this->listasBase();
        app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $septiembre = app(AsegurarPeriodoHistoricoEscalonamiento::class)->asegurar(2026, 9);

        app(SimularCierreEscalonamiento::class)->simular($septiembre, $this->operador);
        $this->assertSame(EscalonamientoPeriodo::ESTADO_EN_REVISION, $septiembre->fresh()->estado);

        app(CancelarSimulacionCierreEscalonamiento::class)->cancelar($septiembre->fresh());

        $this->assertSame(EscalonamientoPeriodo::ESTADO_HISTORIAL, $septiembre->fresh()->estado);
        $this->assertSame(
            EscalonamientoPeriodo::ESTADO_ABIERTO,
            EscalonamientoPeriodo::query()->where('anio', 2026)->where('mes', 10)->value('estado'),
        );
    }

    public function test_incidencia_abierta_bloquea_cierre_de_historial(): void
    {
        $this->listasBase();
        $septiembre = app(AsegurarPeriodoHistoricoEscalonamiento::class)->asegurar(2026, 9);
        EscalonamientoIncidencia::query()->create([
            'escalonamiento_periodo_id' => $septiembre->id,
            'gravedad' => 'bloqueo',
            'codigo' => 'irregularidad_mantenimiento',
            'motivo' => 'Irregularidad abierta en el mes histórico.',
            'estado' => 'abierta',
        ]);

        $this->expectException(CierreEscalonamientoException::class);
        app(SimularCierreEscalonamiento::class)->simular($septiembre, $this->operador);
    }

    public function test_pantalla_cierre_responde_ok(): void
    {
        app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        $this->actingAs($this->operador)
            ->get(route('escalonamiento.cierre'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Escalonamiento/Cierre', false));
    }

    /**
     * @return array{0: CatalogoListaDescuento, 1: CatalogoListaDescuento}
     */
    private function listasBase(): array
    {
        $publico = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $plata = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);

        return [$publico, $plata];
    }

    private function cerrarPeriodo(EscalonamientoPeriodo $periodo, User $supervisor): EscalonamientoCierre
    {
        $cierre = app(SimularCierreEscalonamiento::class)->simular($periodo, $this->operador)['cierre'];
        app(AutorizarCierreEscalonamiento::class)->autorizar($periodo->fresh(), $supervisor);
        app(AplicarCierreEscalonamiento::class)->aplicar($periodo->fresh(), $supervisor);

        return EscalonamientoCierre::query()->findOrFail($cierre->id);
    }
}
