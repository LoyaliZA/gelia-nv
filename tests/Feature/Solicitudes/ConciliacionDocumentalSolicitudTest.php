<?php

namespace Tests\Feature\Solicitudes;

use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use App\Services\Solicitudes\CancelarSolicitudService;
use App\Services\Solicitudes\ListarSolicitudesService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConciliacionDocumentalSolicitudTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    private CatalogoListaDescuento $nivelMedio;

    private CatalogoProceso $proceso;

    private User $vendedor;

    private int $respondida;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('solicitudes.confirmar_pago', 'web');
        foreach (['Pendiente', 'Respondida', 'Incorrecta', 'Cancelada', 'Verificada'] as $estado) {
            CatalogoEstadoSolicitud::query()->firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true]);
        $this->bronce = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true]);
        $this->plata = CatalogoListaDescuento::create(['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5001, 'activo' => true]);
        $this->nivelMedio = CatalogoListaDescuento::create(['nombre' => 'NIVEL MEDIO', 'monto_requerido' => 8000, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->proceso = CatalogoProceso::create(['nombre' => 'CAMBIO DE LISTA', 'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO, 'activo' => true]);
        $this->vendedor = User::factory()->create();
        app(EscalonamientoAutoridadConfig::class)->guardar(false);
    }

    public function test_csv_antes_del_pago_no_duplica_ni_cubre_la_solicitud(): void
    {
        $cliente = $this->cliente('1000.00');
        $solicitud = $this->solicitud($cliente, '6000.00');

        $this->importar("numero_cliente,nombre,monto_venta_actual\n{$cliente->numero_cliente},Cliente,7000\n");

        $cliente->refresh();
        $solicitud->refresh();
        $proyeccion = $this->ajuste()->proyectarMonto($cliente, $solicitud, 6000);

        $this->assertSame('7000.00', $cliente->monto_venta_actual);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertNotSame($this->nivelMedio->id, $cliente->lista_actual_id);
        $this->assertFalse((bool) $solicitud->cubierto_por_carga_masiva);
        $this->assertSame(0, EscalonamientoConciliacionSolicitud::count());
        $this->assertSame('7000.00', $proyeccion['venta_oficial']);
        $this->assertSame('6000.00', $proyeccion['pago_confirmado_sin_conciliar']);
        $this->assertSame('0.00', $proyeccion['cotizacion_pendiente']);
        $this->assertSame(7000.0, $proyeccion['proyectado']);
        $this->assertFalse($proyeccion['aplicara_monto']);
        $this->assertSame('retenida', $proyeccion['efecto_lista']);
    }

    public function test_csv_despues_del_pago_deja_el_mismo_resultado(): void
    {
        $cliente = $this->cliente('1000.00');
        $solicitud = $this->solicitud($cliente, '6000.00', $this->respondida);
        $this->ajuste()->aplicarPagoConfirmado($solicitud, 6000, $this->vendedor->id);

        $this->importar("numero_cliente,nombre,monto_venta_actual\n{$cliente->numero_cliente},Cliente,7000\n");

        $cliente->refresh();
        $solicitud->refresh();
        $proyeccion = $this->ajuste()->desglose($cliente);

        $this->assertSame('7000.00', $cliente->monto_venta_actual);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame('6000.00', (string) $solicitud->monto_aplicado_al_cliente);
        $this->assertFalse((bool) $solicitud->cubierto_por_carga_masiva);
        $this->assertSame(0, EscalonamientoConciliacionSolicitud::count());
        $this->assertSame('7000.00', $proyeccion['venta_oficial']);
        $this->assertSame('6000.00', $proyeccion['pago_confirmado_sin_conciliar']);
        $this->assertSame('0.00', $proyeccion['venta_respaldada_en_acumulado']);
    }

    public function test_pago_antes_y_despues_de_conciliar_no_toca_el_acumulado(): void
    {
        $cliente = $this->cliente('1000.00');
        $solicitud = $this->solicitud($cliente, '80.00', $this->respondida);
        $solicitud->update(['numero_remision' => 'R-80']);
        $this->documento($cliente, 'R-80', '80.00');

        $antes = $this->ajuste()->proyectarMonto($cliente, $solicitud, 80);
        $this->ajuste()->aplicarPagoConfirmado($solicitud, 80, $this->vendedor->id);
        $pagada = $this->ajuste()->desglose($cliente->fresh(), $solicitud->fresh(), 80);
        $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud->fresh());
        $despues = $this->ajuste()->proyectarMonto($cliente->fresh(), $solicitud->fresh(), 80);

        $this->assertSame('retenida', $antes['efecto_lista']);
        $this->assertSame('80.00', $pagada['pago_confirmado_sin_conciliar']);
        $this->assertSame('0.00', $pagada['venta_documentada_inclusion_indeterminada']);
        $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA, $fila->estado);
        $this->assertSame(EscalonamientoConciliacionSolicitud::INCLUSION_INDETERMINADA, $fila->inclusion_acumulado);
        $this->assertSame('80.00', (string) $fila->importe_asignado);
        $this->assertSame('0.00', $despues['pago_confirmado_sin_conciliar']);
        $this->assertSame('80.00', $despues['venta_documentada_inclusion_indeterminada']);
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('retenida', $despues['efecto_lista']);
    }

    public function test_solicitudes_simultaneas_no_se_cuentan_dos_veces(): void
    {
        $cliente = $this->cliente('1000.00');
        $primera = $this->solicitud($cliente, '300.00');
        $segunda = $this->solicitud($cliente, '450.00');
        $proyeccion = $this->ajuste()->proyectarMonto($cliente, $primera, 300);

        $this->assertSame('450.00', $proyeccion['cotizacion_pendiente']);
        $this->assertSame('300.00', $proyeccion['pago_confirmado_sin_conciliar']);
        $this->assertSame('1000.00', $proyeccion['venta_oficial']);
        $this->assertSame(1000.0, $proyeccion['proyectado']);
        $this->assertNotSame($primera->id, $segunda->id);
    }

    public function test_un_documento_cubre_varias_solicitudes_sin_excederse(): void
    {
        $cliente = $this->cliente('1000.00');
        $documento = $this->documento($cliente, 'R-100', '100.00');
        $primera = $this->pagada($cliente, '70.00', 'R-100');
        $segunda = $this->pagada($cliente, '30.00', 'R-100');
        $tercera = $this->pagada($cliente, '20.00', 'R-100');
        $svc = app(ConciliacionSolicitudEscalonamiento::class);

        $a = $svc->conciliar($primera);
        $b = $svc->conciliar($segunda);
        $c = $svc->conciliar($tercera);
        $suma = $this->sumaAsignada($documento);

        $this->assertSame('70.00', (string) $a->importe_asignado);
        $this->assertSame('30.00', (string) $b->importe_asignado);
        $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_SIN_DISPONIBILIDAD, $c->estado);
        $this->assertSame('0.00', (string) $c->importe_asignado);
        $this->assertSame('100.00', $suma);
        $this->assertSame('20.00', $this->ajuste()->desglose($cliente)['pago_confirmado_sin_conciliar']);
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_documento_con_monto_insuficiente_solo_asigna_el_disponible(): void
    {
        $cliente = $this->cliente('500.00');
        $this->documento($cliente, 'R-30', '30.00');
        $solicitud = $this->pagada($cliente, '100.00', 'R-30');

        $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
        $desglose = $this->ajuste()->desglose($cliente);

        $this->assertSame('30.00', (string) $fila->importe_asignado);
        $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA, $fila->estado);
        $this->assertSame('30.00', $desglose['venta_documentada_inclusion_indeterminada']);
        $this->assertSame('70.00', $desglose['pago_confirmado_sin_conciliar']);
        $this->assertSame('500.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_confirmacion_de_pago_repetida_no_duplica(): void
    {
        $cliente = $this->cliente('1000.00');
        $solicitud = $this->solicitud($cliente, '6000.00', $this->respondida);
        $ajuste = $this->ajuste();

        $primera = $ajuste->aplicarPagoConfirmado($solicitud, 6000, $this->vendedor->id);
        $segunda = $ajuste->aplicarPagoConfirmado($solicitud->fresh(), 9000, $this->vendedor->id);

        $this->assertFalse($primera['repetido']);
        $this->assertTrue($segunda['repetido']);
        $this->assertSame('6000.00', (string) $solicitud->fresh()->monto_aplicado_al_cliente);
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_conciliacion_repetida_no_duplica(): void
    {
        $cliente = $this->cliente('1000.00');
        $this->documento($cliente, 'R-25', '25.00');
        $solicitud = $this->pagada($cliente, '25.00', 'R-25');
        $svc = app(ConciliacionSolicitudEscalonamiento::class);

        $primera = $svc->conciliar($solicitud);
        $segunda = $svc->conciliar($solicitud->fresh());

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, EscalonamientoConciliacionSolicitud::query()->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)->count());
        $this->assertSame('25.00', (string) $segunda->fresh()->importe_asignado);
    }

    public function test_cancelacion_libera_el_documento_sin_restar_venta(): void
    {
        $cliente = $this->cliente('1000.00');
        $documento = $this->documento($cliente, 'R-100', '100.00');
        $solicitud = $this->pagada($cliente, '100.00', 'R-100');
        $solicitud->update(['cancelacion_solicitada_at' => now(), 'catalogo_lista_descuento_id' => $this->plata->id]);
        app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
        $this->actingAs($this->vendedor);

        app(CancelarSolicitudService::class)->ejecutar($solicitud->fresh(), 'Cierre de prueba');
        $reemplazo = $this->pagada($cliente, '100.00', 'R-100');
        $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($reemplazo);

        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame(
            0,
            EscalonamientoConciliacionSolicitud::query()
                ->where('solicitud_tag_id', $solicitud->id)
                ->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)
                ->count()
        );
        $this->assertSame('100.00', (string) $fila->importe_asignado);
        $this->assertSame('100.00', $this->sumaAsignada($documento));
        $desglose = $this->ajuste()->desglose($cliente);
        $this->assertSame('100.00', $desglose['excluidas']);
        $this->assertSame('0.00', $desglose['cotizacion_pendiente']);
    }

    public function test_vencimiento_libera_la_asignacion_sin_restar_venta(): void
    {
        Notification::fake();
        Permission::findOrCreate('solicitudes.verificar', 'web');
        Permission::findOrCreate('solicitudes.reportar', 'web');
        $cliente = $this->cliente('1000.00');
        $solicitud = $this->pagada($cliente, '40.00', 'R-40');
        $this->documento($cliente, 'R-40', '40.00');
        app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
        $solicitud->pago_confirmado = false;
        $solicitud->created_at = now()->subHours(25);
        $solicitud->save();

        $this->artisan('pagos:rechazar-vencidos');

        $solicitud->refresh();
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame(CatalogoEstadoSolicitud::idDe('Incorrecta'), (int) $solicitud->catalogo_estado_solicitud_id);
        $this->assertSame(
            EscalonamientoConciliacionSolicitud::ESTADO_ANULADA,
            EscalonamientoConciliacionSolicitud::query()->where('solicitud_tag_id', $solicitud->id)->value('estado')
        );
        $this->assertSame('40.00', $this->ajuste()->desglose($cliente)['excluidas']);
    }

    public function test_marca_historica_sin_documento_queda_indeterminada_y_no_se_reescribe(): void
    {
        $cliente = $this->cliente('1000.00');
        $solicitud = $this->pagada($cliente, '3000.00', null);
        $solicitud->update(['cubierto_por_carga_masiva' => true]);

        $this->importar("numero_cliente,nombre,monto_venta_actual\n{$cliente->numero_cliente},Cliente,4000\n");
        $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud->fresh());
        $desglose = $this->ajuste()->desglose($cliente->fresh());

        $this->assertTrue((bool) $solicitud->fresh()->cubierto_por_carga_masiva);
        $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_INDETERMINADA, $fila->estado);
        $this->assertSame(EscalonamientoConciliacionSolicitud::PROCEDENCIA_HISTORICA, $fila->procedencia);
        $this->assertNull($fila->documento_venta_id);
        $this->assertSame('0.00', (string) $fila->importe_asignado);
        $this->assertSame('3000.00', $desglose['historica_sin_respaldo']);
        $this->assertSame('0.00', $desglose['pago_confirmado_sin_conciliar']);
        $this->assertSame('4000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('4000.00', $desglose['venta_oficial']);
    }

    public function test_autoridad_apagada_no_trata_el_documento_como_venta_oficial(): void
    {
        app(EscalonamientoAutoridadConfig::class)->guardar(false);
        $cliente = $this->cliente('1000.00');
        $periodo = $this->periodo();
        $documento = $this->documento($cliente, 'R-10', '10.00', $periodo);
        EscalonamientoResumenCliente::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'acumulado' => '10.00',
        ]);
        $solicitud = $this->pagada($cliente, '10.00', 'R-10');

        $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
        $desglose = $this->ajuste()->desglose($cliente);

        $this->assertSame(EscalonamientoConciliacionSolicitud::INCLUSION_INDETERMINADA, $fila->inclusion_acumulado);
        $this->assertSame('1000.00', $desglose['venta_oficial']);
        $this->assertSame('10.00', $desglose['venta_documentada_inclusion_indeterminada']);
        $this->assertSame('0.00', $desglose['venta_respaldada_en_acumulado']);
        $this->assertSame(1000.0, $this->ajuste()->proyectarMonto($cliente, $solicitud->fresh(), 10)['proyectado']);
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertNotNull($documento->id);
    }

    public function test_autoridad_encendida_no_vuelve_a_sumar_un_documento_incluido(): void
    {
        $cliente = $this->cliente('1000.00');
        $periodo = $this->periodo();
        $documento = $this->documento($cliente, 'R-100', '100.00', $periodo);
        EscalonamientoMovimiento::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'documento_venta_id' => $documento->id,
            'efecto' => '100.00',
            'operacion' => 'remision',
        ]);
        EscalonamientoResumenCliente::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'acumulado' => '100.00',
        ]);
        app(EscalonamientoAutoridadConfig::class)->guardar(true);
        $solicitud = $this->pagada($cliente, '100.00', 'R-100');
        $solicitud->update(['catalogo_lista_descuento_id' => $this->plata->id]);

        $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
        $desglose = $this->ajuste()->proyectarMonto($cliente, $solicitud->fresh(), 100);

        $this->assertSame(EscalonamientoConciliacionSolicitud::INCLUSION_INCLUIDA, $fila->inclusion_acumulado);
        $this->assertSame('100.00', $desglose['venta_oficial']);
        $this->assertSame('100.00', $desglose['venta_respaldada_en_acumulado']);
        $this->assertSame(100.0, $desglose['proyectado']);
        $this->assertSame('pago_insuficiente', $desglose['efecto_lista']);
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);

        $publica = CatalogoListaDescuento::query()->where('nombre', 'PUBLICO GENERAL')->firstOrFail();
        $solicitud->update(['catalogo_lista_descuento_id' => $publica->id]);
        $cumple = $this->ajuste()->proyectarMonto($cliente->fresh(), $solicitud->fresh(), 100);
        $this->assertSame('sin_cambio', $cumple['efecto_lista']);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_autoridad_encendida_informa_fuera_del_acumulado_sin_sumarlo(): void
    {
        $cliente = $this->cliente('1000.00');
        $periodo = $this->periodo();
        $incluido = $this->documento($cliente, 'R-BASE', '100.00', $periodo);
        EscalonamientoMovimiento::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'documento_venta_id' => $incluido->id,
            'efecto' => '100.00',
            'operacion' => 'remision',
        ]);
        EscalonamientoResumenCliente::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'acumulado' => '100.00',
        ]);
        $this->documento($cliente, 'R-FUERA', '80.00', $periodo);
        app(EscalonamientoAutoridadConfig::class)->guardar(true);
        $solicitud = $this->pagada($cliente, '80.00', 'R-FUERA');

        $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
        $desglose = $this->ajuste()->proyectarMonto($cliente, $solicitud->fresh(), 80);

        $this->assertSame(EscalonamientoConciliacionSolicitud::INCLUSION_FUERA, $fila->inclusion_acumulado);
        $this->assertSame('100.00', $desglose['venta_oficial']);
        $this->assertSame('80.00', $desglose['venta_documentada_fuera']);
        $this->assertSame(100.0, $this->ajuste()->proyectarMonto($cliente, $solicitud->fresh(), 80)['proyectado']);
        $this->assertSame('retenida', $desglose['efecto_lista']);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_identidad_insuficiente_o_ambigua_no_inventa_asociacion(): void
    {
        $cliente = $this->cliente('1000.00');
        $sinFolio = $this->pagada($cliente, '20.00', null);
        $ajeno = $this->cliente('50.00', '880002');
        $this->documento($ajeno, 'R-OTRO', '20.00');
        $conFolio = $this->pagada($cliente, '20.00', 'R-OTRO');
        $this->documento($cliente, 'R-DUP', '20.00');
        $this->documento($cliente, 'R-DUP', '30.00');
        $ambigua = $this->pagada($cliente, '20.00', 'R-DUP');
        $svc = app(ConciliacionSolicitudEscalonamiento::class);

        $vacia = $svc->conciliar($sinFolio);
        $noEsSuya = $svc->conciliar($conFolio);
        $doble = $svc->conciliar($ambigua);

        $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_PENDIENTE, $vacia->estado);
        $this->assertSame(EscalonamientoConciliacionSolicitud::PROCEDENCIA_IDENTIDAD, $vacia->procedencia);
        $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_INDETERMINADA, $noEsSuya->estado);
        $this->assertNull($noEsSuya->documento_venta_id);
        $this->assertSame(EscalonamientoConciliacionSolicitud::PROCEDENCIA_AMBIGUA, $doble->procedencia);
        $this->assertSame('0.00', $this->sumaAsignada($this->documentoExistente('R-DUP')));
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_la_asignacion_manual_no_supera_el_disponible(): void
    {
        $cliente = $this->cliente('0.00');
        $periodo = $this->periodo();
        $documento = $this->documento($cliente, 'R-100', '100.00', $periodo);
        $primera = $this->pagada($cliente, '100.00', 'R-100');
        $segunda = $this->pagada($cliente, '100.00', 'R-100');
        $svc = app(ConciliacionSolicitudEscalonamiento::class);
        $svc->asignar($periodo, $primera, $documento, '60.00', 'cotejo', null);

        try {
            $svc->asignar($periodo, $segunda, $documento, '50.00', 'cotejo', null);
            $this->fail('La segunda asignación superaba el disponible.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('disponible', $e->getMessage());
        }
        $this->assertSame('60.00', $this->sumaAsignada($documento));
    }

    public function test_reversion_de_la_conciliacion_no_deja_filas(): void
    {
        $cliente = $this->cliente('1000.00');
        $this->documento($cliente, 'R-15', '15.00');
        $solicitud = $this->pagada($cliente, '15.00', 'R-15');

        try {
            DB::transaction(function () use ($solicitud) {
                app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
                throw new RuntimeException('forzar reversa');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('forzar reversa', $e->getMessage());
        }

        $this->assertSame(0, EscalonamientoConciliacionSolicitud::count());
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_confirmar_pago_no_asciende_la_lista_y_repetirlo_no_audita_de_nuevo(): void
    {
        Notification::fake();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Administrador', 'web');
        $this->vendedor->givePermissionTo('solicitudes.confirmar_pago');
        $cliente = $this->cliente('1000.00');
        $solicitud = $this->solicitud($cliente, '6000.00', $this->respondida);
        $solicitud->update(['catalogo_lista_descuento_id' => $this->plata->id]);

        $this->actingAs($this->vendedor)
            ->put(route('solicitudes.confirmar_pago', $solicitud), ['monto_final_pagado' => 6000])
            ->assertRedirect();
        $this->actingAs($this->vendedor)
            ->put(route('solicitudes.confirmar_pago', $solicitud), ['monto_final_pagado' => 6000])
            ->assertRedirect();

        $cliente->refresh();
        $solicitud->refresh();
        $visibles = app(ListarSolicitudesService::class)->ejecutar($this->vendedor, [], false);

        $this->assertSame('1000.00', $cliente->monto_venta_actual);
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertTrue($solicitud->pago_confirmado);
        $this->assertSame('6000.00', (string) $solicitud->monto_aplicado_al_cliente);
        $this->assertSame($this->respondida, (int) $solicitud->catalogo_estado_solicitud_id);
        $this->assertSame(1, AuditoriaSolicitud::query()->where('solicitud_id', $solicitud->id)->count());
        $this->assertSame('retenida', AuditoriaSolicitud::query()->first()->datos_snapshot['proyeccion']['efecto_lista']);
        $this->assertCount(1, $visibles);
        $this->assertSame($solicitud->id, $visibles->first()->id);
    }

    private function ajuste(): AjustarMontoPorSolicitudService
    {
        return app(AjustarMontoPorSolicitudService::class);
    }

    private function cliente(string $monto, string $numero = '880001'): Cliente
    {
        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $this->bronce->id,
            'monto_venta_actual' => $monto,
        ]);
    }

    private function solicitud(Cliente $cliente, string $monto, ?int $estado = null): SolicitudTag
    {
        return SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => $estado ?? CatalogoEstadoSolicitud::idDe('Pendiente'),
            'catalogo_lista_descuento_id' => $this->bronce->id,
            'monto_cotizado' => $monto,
            'pago_confirmado' => false,
        ]);
    }

    private function pagada(Cliente $cliente, string $monto, ?string $folio): SolicitudTag
    {
        $solicitud = $this->solicitud($cliente, $monto, $this->respondida);
        $solicitud->update([
            'pago_confirmado' => true,
            'monto_aplicado_al_cliente' => $monto,
            'numero_remision' => $folio,
        ]);

        return $solicitud->fresh();
    }

    private function periodo(): EscalonamientoPeriodo
    {
        return EscalonamientoPeriodo::query()->firstOrCreate(
            ['anio' => 2026, 'mes' => 11],
            ['zona_horaria' => 'America/Mexico_City', 'estado' => EscalonamientoPeriodo::ESTADO_ABIERTO],
        );
    }

    private function documento(Cliente $cliente, string $folio, string $total, ?EscalonamientoPeriodo $periodo = null): DocumentoVenta
    {
        $periodo ??= $this->periodo();

        return DocumentoVenta::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => $folio,
            'moneda' => 'MXN',
            'total' => $total,
            'estado' => 'activo',
            'fecha_emision' => '2026-11-01',
            'origen' => 'prueba',
            'clave_documento' => 'REMISION|||'.$folio.'|'.$total.'|'.$cliente->id.'|'.DocumentoVenta::query()->count(),
        ]);
    }

    private function documentoExistente(string $folio): DocumentoVenta
    {
        return DocumentoVenta::query()->where('folio', $folio)->firstOrFail();
    }

    private function sumaAsignada(DocumentoVenta $documento): string
    {
        $total = '0.00';
        $filas = EscalonamientoConciliacionSolicitud::query()
            ->where('documento_venta_id', $documento->id)
            ->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)
            ->get();
        foreach ($filas as $fila) {
            $total = bcadd($total, (string) $fila->importe_asignado, 2);
        }

        return $total;
    }

    private function importar(string $csv): void
    {
        $path = tempnam(sys_get_temp_dir(), 'conciliacion-');
        file_put_contents($path, $csv);
        app(ImportarClientesWizerpService::class)->ejecutar(new UploadedFile($path, 'test.csv', 'text/csv', null, true));
    }
}
