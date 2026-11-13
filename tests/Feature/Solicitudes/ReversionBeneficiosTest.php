<?php

namespace Tests\Feature\Solicitudes;

use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\SolicitudBeneficio;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use App\Services\Solicitudes\EscalonamientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReversionBeneficiosTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    private CatalogoProceso $procesoLista;

    private CatalogoProceso $procesoTag;

    private CatalogoTipoCliente $tipo;

    private User $vendedorAnterior;

    private User $vendedorNuevo;

    private int $respondida;

    private int $incorrecta;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('solicitudes.verificar', 'web');
        Permission::findOrCreate('solicitudes.reportar', 'web');

        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta', 'Cancelada'] as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $this->incorrecta = (int) CatalogoEstadoSolicitud::idDe('Incorrecta');

        CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true]);
        $this->bronce = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true]);
        $this->plata = CatalogoListaDescuento::create(['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5104, 'activo' => true]);
        $this->procesoLista = CatalogoProceso::create([
            'nombre' => 'CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $this->procesoTag = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $this->tipo = CatalogoTipoCliente::create(['nombre' => 'MOSTRADOR', 'activo' => true]);
        $this->vendedorAnterior = User::factory()->create(['name' => 'Vendedor anterior']);
        $this->vendedorNuevo = User::factory()->create(['name' => 'Vendedor asignado']);
    }

    public function test_revertir_la_unica_solicitud_restaura_la_condicion_oficial(): void
    {
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, $this->plata->id, $this->tipo->id);
        $this->ajuste()->aplicarBeneficios($solicitud);

        $this->ajuste()->revertirBeneficios($solicitud);

        $cliente->refresh();
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertNull($cliente->catalogo_tipo_cliente_id);
        $this->assertSame($this->vendedorAnterior->id, $cliente->vendedor_id);
        $this->assertSame(0.0, (float) $solicitud->fresh()->monto_aplicado_al_cliente);
        $this->assertSame(3, SolicitudBeneficio::query()->where('estado', SolicitudBeneficio::ESTADO_REVOCADO)->count());
    }

    public function test_otra_solicitud_vigente_conserva_el_beneficio(): void
    {
        $cliente = $this->cliente(1000);
        $primera = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $segunda = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($primera);
        $this->ajuste()->aplicarBeneficios($segunda);

        $this->ajuste()->revertirBeneficios($primera);

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
        $this->assertSame(
            1,
            SolicitudBeneficio::query()->where('solicitud_tag_id', $segunda->id)->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)->count()
        );
    }

    public function test_el_oficial_que_ya_califica_la_lista_no_se_rebaja(): void
    {
        $cliente = $this->cliente(13000);
        $solicitud = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);

        $this->ajuste()->revertirBeneficios($solicitud);

        $cliente->refresh();
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertEquals(13000.0, (float) $cliente->monto_venta_actual);
    }

    public function test_repetir_la_reversion_es_idempotente(): void
    {
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->ajuste()->revertirBeneficios($solicitud);
        $filas = SolicitudBeneficio::count();
        $revocadas = SolicitudBeneficio::query()->where('estado', SolicitudBeneficio::ESTADO_REVOCADO)->count();

        $this->ajuste()->revertirBeneficios($solicitud);

        $cliente->refresh();
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($filas, SolicitudBeneficio::count());
        $this->assertSame($revocadas, SolicitudBeneficio::query()->where('estado', SolicitudBeneficio::ESTADO_REVOCADO)->count());
    }

    public function test_revertir_la_primera_no_deshace_la_segunda(): void
    {
        $cliente = $this->cliente(1000);
        $primera = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, null);
        $segunda = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, null);
        $this->ajuste()->aplicarBeneficios($primera);
        $this->ajuste()->aplicarBeneficios($segunda);

        $this->ajuste()->revertirBeneficios($primera);

        $this->assertSame($this->vendedorNuevo->id, $cliente->fresh()->vendedor_id);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
    }

    public function test_aprobacion_y_cancelacion_concurrentes_respetan_la_vigente(): void
    {
        $cliente = $this->cliente(1000);
        $primera = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $segunda = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id, null, CatalogoEstadoSolicitud::idDe('Pendiente'));
        $this->ajuste()->aplicarBeneficios($primera);

        $this->ajuste()->revertirBeneficios($primera);
        $segunda->catalogo_estado_solicitud_id = $this->respondida;
        $segunda->save();
        $this->ajuste()->aplicarBeneficios($segunda);

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
        $this->assertSame(
            1,
            SolicitudBeneficio::query()->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)->count()
        );
    }

    public function test_la_marca_historica_y_la_conciliacion_anulada_permanecen(): void
    {
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $periodo = EscalonamientoPeriodo::create([
            'anio' => 2026,
            'mes' => 11,
            'zona_horaria' => 'America/Mexico_City',
            'estado' => 'abierto',
        ]);
        $fila = EscalonamientoConciliacionSolicitud::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'solicitud_tag_id' => $solicitud->id,
            'importe_asignado' => 40,
            'estado' => EscalonamientoConciliacionSolicitud::ESTADO_ANULADA,
            'procedencia' => EscalonamientoConciliacionSolicitud::PROCEDENCIA_HISTORICA,
            'inclusion_acumulado' => EscalonamientoConciliacionSolicitud::INCLUSION_INDETERMINADA,
            'clave_asignacion' => 'sin-documento:'.$solicitud->id,
            'evidencia' => 'nota previa',
        ]);
        $solicitud->cubierto_por_carga_masiva = true;
        $solicitud->save();

        $this->ajuste()->revertirBeneficios($solicitud);

        $this->assertTrue((bool) $solicitud->fresh()->cubierto_por_carga_masiva);
        $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_ANULADA, $fila->fresh()->estado);
        $this->assertSame('nota previa', $fila->fresh()->evidencia);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
        $this->assertSame(1, SolicitudBeneficio::query()->where('estado', SolicitudBeneficio::ESTADO_REVOCADO)->count());
    }

    public function test_la_autoridad_apagada_no_cambia_y_la_encendida_no_queda_activa(): void
    {
        $config = app(EscalonamientoAutoridadConfig::class);
        $this->assertFalse($config->estaActiva());
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $desglose = $this->ajuste()->desglose($cliente->fresh(), $solicitud, 1000);
        $this->assertEquals(1000.0, (float) $desglose['venta_oficial']);

        $config->guardar(true);
        try {
            $this->assertTrue($config->estaActiva());
            $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
        } finally {
            $config->guardar(false);
        }

        $this->assertFalse($config->estaActiva());
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
    }

    public function test_vencer_la_solicitud_que_concedio_el_beneficio_restaura_la_lista_oficial(): void
    {
        Notification::fake();
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $solicitud->created_at = now()->subHours(25);
        $solicitud->save();

        Artisan::call('pagos:rechazar-vencidos');

        $cliente->refresh();
        $solicitud->refresh();
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertSame($this->incorrecta, (int) $solicitud->catalogo_estado_solicitud_id);
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
    }

    public function test_un_cambio_posterior_legitimo_no_se_deshace_al_vencer(): void
    {
        Notification::fake();
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $cliente->refresh();
        $cliente->lista_actual_id = $this->bronce->id;
        $cliente->save();
        $solicitud->created_at = now()->subHours(25);
        $solicitud->save();

        Artisan::call('pagos:rechazar-vencidos');

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame(
            SolicitudBeneficio::ESTADO_REEMPLAZADO,
            SolicitudBeneficio::query()->where('solicitud_tag_id', $solicitud->id)->value('estado')
        );
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
    }

    public function test_el_tag_ratificado_no_se_quita_y_el_no_ratificado_vuelve_al_anterior(): void
    {
        $cliente = $this->cliente(1000);
        $ratificada = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, null);
        $this->ajuste()->aplicarBeneficios($ratificada);
        $tercero = User::factory()->create(['name' => 'Vendedor ratificado']);
        $cliente->vendedor_id = $tercero->id;
        $cliente->save();

        $this->ajuste()->revertirBeneficios($ratificada);

        $this->assertSame($tercero->id, $cliente->fresh()->vendedor_id);

        $cliente->vendedor_id = $this->vendedorAnterior->id;
        $cliente->save();
        $pendiente = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, null);
        $this->ajuste()->aplicarBeneficios($pendiente);
        $this->ajuste()->revertirBeneficios($pendiente);

        $this->assertSame($this->vendedorAnterior->id, $cliente->fresh()->vendedor_id);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
    }

    public function test_lista_bloqueada_e_inactivo_no_reciben_ni_reescriben_un_beneficio(): void
    {
        $bloqueado = $this->cliente(1000);
        $bloqueado->update(['lista_bloqueada' => true]);
        $solicitudBloqueada = $this->solicitud($bloqueado, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitudBloqueada);
        $this->ajuste()->revertirBeneficios($solicitudBloqueada);
        $this->assertSame($this->bronce->id, $bloqueado->fresh()->lista_actual_id);

        $inactivo = $this->cliente(1000, '4402');
        $solicitudInactiva = $this->solicitud($inactivo, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitudInactiva);
        $inactivo->update(['es_inactivo' => true, 'lista_actual_id' => $this->plata->id]);
        SolicitudBeneficio::query()->create([
            'cliente_id' => $inactivo->id,
            'solicitud_tag_id' => $solicitudInactiva->id,
            'campo' => SolicitudBeneficio::CAMPO_LISTA,
            'valor_anterior' => (string) $this->bronce->id,
            'valor_aplicado' => (string) $this->plata->id,
            'estado' => SolicitudBeneficio::ESTADO_VIGENTE,
            'motivo' => 'Concesión previa al cambio de inactividad.',
        ]);

        $this->ajuste()->revertirBeneficios($solicitudInactiva);

        $this->assertSame($this->plata->id, $inactivo->fresh()->lista_actual_id);
        $this->assertTrue((bool) $inactivo->fresh()->es_inactivo);
        $this->assertEquals(1000.0, (float) $inactivo->fresh()->monto_venta_actual);
    }

    public function test_el_vencimiento_guarda_auditoria_sin_usuario_uno(): void
    {
        Notification::fake();
        if ((int) $this->vendedorAnterior->id === 1) {
            $this->vendedorAnterior->forceDelete();
            $this->vendedorAnterior = User::factory()->create(['name' => 'Vendedor anterior']);
        }
        if ((int) $this->vendedorNuevo->id === 1) {
            $this->vendedorNuevo->forceDelete();
            $this->vendedorNuevo = User::factory()->create(['name' => 'Vendedor asignado']);
        }
        User::withTrashed()->whereKey(1)->forceDelete();
        $admin = User::factory()->create(['name' => 'Admin existente']);
        $admin->givePermissionTo('solicitudes.verificar');
        $this->assertNull(User::withTrashed()->find(1));
        $this->assertNotSame(1, $admin->id);

        $cliente = $this->cliente(1000);
        $concedida = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($concedida);
        $concedida->created_at = now()->subHours(25);
        $concedida->save();

        $historica = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->bronce->id, null, $this->respondida);
        $historica->created_at = now()->subHours(30);
        $historica->save();
        $this->assertSame(0, SolicitudBeneficio::query()->where('solicitud_tag_id', $historica->id)->count());

        Artisan::call('pagos:rechazar-vencidos');
        Artisan::call('pagos:rechazar-vencidos');

        $cliente->refresh();
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($this->incorrecta, (int) $concedida->fresh()->catalogo_estado_solicitud_id);
        $this->assertSame($this->incorrecta, (int) $historica->fresh()->catalogo_estado_solicitud_id);

        $auditorias = AuditoriaSolicitud::query()->orderBy('id')->get();
        $this->assertCount(2, $auditorias);
        foreach ($auditorias as $auditoria) {
            $this->assertNull($auditoria->usuario_id);
            $this->assertSame('SISTEMA AUTOMÁTICO: Plazo de pago expirado (24h).', $auditoria->motivo_reporte);
            $this->assertSame('vencimiento_automatico', $auditoria->datos_snapshot['origen']);
            $this->assertNull($auditoria->datos_snapshot['actor']);
            $this->assertNotSame($admin->id, $auditoria->usuario_id);
        }

        $cliente->lista_actual_id = $this->plata->id;
        $cliente->save();
        $this->ajuste()->revertirBeneficios($concedida->fresh());
        $this->ajuste()->revertirBeneficios($historica->fresh());

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
        $this->assertCount(2, AuditoriaSolicitud::query()->get());
    }

    public function test_una_solicitud_historica_sin_constancia_no_degrada_la_lista(): void
    {
        $cliente = $this->cliente(1000);
        $cliente->lista_actual_id = $this->plata->id;
        $cliente->catalogo_tipo_cliente_id = $this->tipo->id;
        $cliente->save();
        $historica = $this->solicitud($cliente, $this->procesoLista, $this->vendedorNuevo, $this->plata->id, $this->tipo->id);

        $this->ajuste()->revertirBeneficios($historica);
        $this->ajuste()->revertirBeneficios($historica->fresh());

        $cliente->refresh();
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame($this->tipo->id, $cliente->catalogo_tipo_cliente_id);
        $this->assertSame($this->vendedorAnterior->id, $cliente->vendedor_id);
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
        $this->assertSame(0, SolicitudBeneficio::query()->where('solicitud_tag_id', $historica->id)->count());
    }

    public function test_otra_solicitud_vigente_sin_constancia_sostiene_lista_y_tag(): void
    {
        $cliente = $this->cliente(1000);
        $concedida = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($concedida);
        $historica = $this->solicitud($cliente, $this->procesoLista, $this->vendedorAnterior, $this->plata->id);
        $this->assertSame(0, SolicitudBeneficio::query()->where('solicitud_tag_id', $historica->id)->count());

        $this->ajuste()->revertirBeneficios($concedida);

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame(
            SolicitudBeneficio::ESTADO_REEMPLAZADO,
            SolicitudBeneficio::query()->where('solicitud_tag_id', $concedida->id)->value('estado')
        );

        $tag = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, null);
        $this->ajuste()->aplicarBeneficios($tag);
        $otraTag = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, null);
        $this->ajuste()->revertirBeneficios($tag);

        $this->assertSame($this->vendedorNuevo->id, $cliente->fresh()->vendedor_id);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);

        $tag->catalogo_estado_solicitud_id = $this->incorrecta;
        $tag->save();
        $otraTag->catalogo_estado_solicitud_id = $this->incorrecta;
        $otraTag->save();
        $cliente->refresh();
        $cliente->vendedor_id = $this->vendedorAnterior->id;
        $cliente->save();
        $cambioLista = $this->solicitud($cliente, $this->procesoLista, $this->vendedorNuevo, $this->plata->id);
        $tagSola = $this->solicitud($cliente, $this->procesoTag, $this->vendedorNuevo, null);
        $this->ajuste()->aplicarBeneficios($tagSola);
        $this->ajuste()->revertirBeneficios($tagSola);

        $this->assertSame($this->vendedorAnterior->id, $cliente->fresh()->vendedor_id);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertNotNull($cambioLista->id);
    }

    public function test_la_cotizacion_no_escribe_el_acumulado_oficial(): void
    {
        $cliente = $this->cliente(1000);
        $resultado = app(EscalonamientoService::class)->evaluarCompraCliente(
            $cliente,
            6000,
            $this->plata->id,
            CatalogoListaDescuento::query()->where('activo', true)->get(),
        );

        $cliente->refresh();
        $this->assertEquals(1000.0, $resultado['monto_historico']);
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
        $this->assertSame(0, SolicitudBeneficio::count());
    }

    private function ajuste(): AjustarMontoPorSolicitudService
    {
        return app(AjustarMontoPorSolicitudService::class);
    }

    private function cliente(float $monto, string $numero = '4401'): Cliente
    {
        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente de prueba',
            'lista_actual_id' => $this->bronce->id,
            'monto_venta_actual' => $monto,
            'vendedor_id' => $this->vendedorAnterior->id,
        ]);
    }

    private function solicitud(
        Cliente $cliente,
        CatalogoProceso $proceso,
        User $vendedor,
        ?int $listaId,
        ?int $tipoId = null,
        ?int $estadoId = null,
    ): SolicitudTag {
        return SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $estadoId ?? $this->respondida,
            'catalogo_lista_descuento_id' => $listaId,
            'catalogo_tipo_cliente_id' => $tipoId,
            'monto_cotizado' => 6000,
            'pago_confirmado' => false,
        ]);
    }
}
