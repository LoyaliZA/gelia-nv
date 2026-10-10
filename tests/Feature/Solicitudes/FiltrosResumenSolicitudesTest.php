<?php

namespace Tests\Feature\Solicitudes;

use App\Models\{CatalogoEstadoSolicitud, CatalogoListaDescuento, CatalogoProceso, CatalogoTipoCliente, Cliente, SolicitudTag, User};
use App\Services\Solicitudes\{CrearSolicitudService, ListarSolicitudesService, ResolverDestinatariosAlertaSolicitudService, SnapshotCotizacionSolicitudService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FiltrosResumenSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;
    private Cliente $cliente;
    private CatalogoProceso $proceso;
    private CatalogoListaDescuento $lista;
    private CatalogoTipoCliente $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta', 'Cancelada'] as $nombre) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $nombre]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->vendedor = User::factory()->create();
        $this->proceso = CatalogoProceso::create(['nombre' => 'ASIGNAR TAG Y CAMBIO DE LISTA', 'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO]);
        $this->lista = CatalogoListaDescuento::create(['nombre' => 'Plata', 'monto_requerido' => 5000]);
        $this->tipo = CatalogoTipoCliente::create(['nombre' => 'Distribuidor']);
        $this->cliente = Cliente::create(['numero_cliente' => '7001', 'nombre' => 'Cliente con TAG', 'vendedor_id' => $this->vendedor->id, 'lista_actual_id' => $this->lista->id]);
    }

    private function solicitud(string $estado = 'Pendiente', array $datos = [], string $fecha = '2026-09-15 12:00:00'): SolicitudTag
    {
        $solicitud = SolicitudTag::create(array_merge([
            'cliente_id' => $this->cliente->id, 'vendedor_id' => $this->vendedor->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe($estado),
            'catalogo_lista_descuento_id' => $this->lista->id,
            'catalogo_tipo_cliente_id' => $this->tipo->id, 'monto_cotizado' => 6000,
        ], $datos));
        $solicitud->forceFill(['created_at' => $fecha])->save();
        return $solicitud;
    }

    public function test_filtros_y_resumen_cuentan_todo_el_periodo_sin_duplicar_clientes(): void
    {
        for ($i = 0; $i < 16; $i++) $this->solicitud('Respondida');
        $error = $this->solicitud('Incorrecta', [], '2026-09-30 23:59:59');
        $this->solicitud('Respondida', ['cancelacion_solicitada_at' => now()]);
        $this->solicitud('Verificada', ['rollback_confirmado_at' => now()]);
        $this->solicitud('Respondida', [], '2026-10-01 00:00:00');
        $this->solicitud('Respondida', ['catalogo_tipo_cliente_id' => null]);
        $this->solicitud()->delete();
        $operativo = CatalogoProceso::create(['nombre' => 'Operativa', 'categoria_flujo' => CatalogoProceso::CATEGORIA_OPERATIVO]);
        $this->solicitud('Respondida', ['catalogo_proceso_id' => $operativo->id]);
        $filtros = ['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30', 'lista_id' => $this->lista->id, 'tipo_cliente_id' => $this->tipo->id, 'tag' => 'con_tag'];
        $service = app(ListarSolicitudesService::class);
        $page = $service->ejecutar(null, $filtros);
        $this->assertCount(15, $page->items());
        $this->assertSame(19, $page->total());
        $resumen = $service->resumenFiltrado(null, $filtros);
        $this->assertSame(19, $resumen['solicitudes']);
        $this->assertSame(1, $resumen['clientes']);
        $this->assertSame(16, $resumen['vigentes']);
        $this->assertSame(1, $resumen['errores']);
        $this->assertSame('Distribuidor', $resumen['tipos'][0]['nombre']);
        $this->assertCount(16, $service->ejecutar(null, $filtros + ['tab' => 'VIGENTES'], false));
        $this->assertSame([$error->id], $service->ejecutar(null, $filtros + ['tab' => 'INCORRECTAS'], false)->pluck('id')->all());
        $this->assertCount(0, $service->ejecutar(null, array_replace($filtros, ['tag' => 'sin_tag']), false));
        $this->cliente->update(['vendedor_id' => null]);
        $this->assertSame(19, $service->resumenFiltrado(null, array_replace($filtros, ['tag' => 'sin_tag']))['solicitudes']);
    }

    public function test_resumen_respeta_el_alcance_del_vendedor_y_los_tipos_no_registrados(): void
    {
        foreach (['solicitudes.verificar', 'solicitudes.reportar', 'solicitudes.cancelar'] as $nombre) Permission::findOrCreate($nombre, 'web');
        $this->solicitud('Pendiente', ['catalogo_tipo_cliente_id' => null]);
        $otro = User::factory()->create();
        $this->solicitud('Respondida', ['vendedor_id' => $otro->id]);
        $resumen = app(ListarSolicitudesService::class)->resumenFiltrado($this->vendedor, ['tipo_cliente_id' => 'SIN_TIPO']);
        $this->assertSame(1, $resumen['solicitudes']);
        $this->assertSame('Sin tipo registrado', $resumen['tipos'][0]['nombre']);
        $this->assertSame(1, app(ListarSolicitudesService::class)->resumenFiltrado($this->vendedor)['solicitudes']);
    }

    public function test_cotizacion_conserva_lista_tipo_y_tag_solicitados_independientemente_del_cliente(): void
    {
        $solicitud = $this->solicitud();
        $antes = ['monto_venta' => 1000, 'lista_nombre' => 'Bronce', 'tipo_cliente_nombre' => 'Normal', 'tag_vendedor_nombre' => null];
        $snapshot = app(SnapshotCotizacionSolicitudService::class)->construir($solicitud, $antes);
        $this->assertSame('Plata', $snapshot['cotizado']['lista_nombre']);
        $this->assertSame('Distribuidor', $snapshot['cotizado']['tipo_cliente_nombre']);
        $this->assertSame($this->vendedor->name, $snapshot['cotizado']['tag_vendedor_nombre']);
        $this->assertSame(7000.0, $snapshot['cotizado']['monto_venta']);
        $this->assertSame($antes, $snapshot['antes']);
        $solicitud->update(['catalogo_tipo_cliente_id' => null, 'catalogo_lista_descuento_id' => null]);
        $nuevo = app(SnapshotCotizacionSolicitudService::class)->construir($solicitud, $antes);
        $this->assertSame('Bronce', $nuevo['cotizado']['lista_nombre']);
        $this->assertSame('Normal', $nuevo['cotizado']['tipo_cliente_nombre']);
        $this->assertSame('Plata', $snapshot['cotizado']['lista_nombre']);
    }

    public function test_creacion_guarda_la_propuesta_completa_en_la_auditoria(): void
    {
        $this->mock(ResolverDestinatariosAlertaSolicitudService::class)
            ->shouldReceive('porDepartamento')->once()->andReturn(collect());
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $this->cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_lista_descuento_id' => $this->lista->id,
            'catalogo_tipo_cliente_id' => $this->tipo->id,
            'monto_cotizado' => 6000,
        ], $this->vendedor->id);
        $snapshot = $solicitud->auditorias()->firstOrFail()->datos_snapshot;
        $this->assertSame('Plata', $snapshot['cotizado']['lista_nombre']);
        $this->assertSame('Distribuidor', $snapshot['cotizado']['tipo_cliente_nombre']);
        $this->assertSame($this->vendedor->name, $snapshot['cotizado']['tag_vendedor_nombre']);
        $this->assertSame($this->tipo->id, $snapshot['tipo_cliente_id']);
        $this->tipo->update(['nombre' => 'Nombre actualizado']);
        $this->assertSame('Distribuidor', $solicitud->auditorias()->first()->datos_snapshot['cotizado']['tipo_cliente_nombre']);
    }

    public function test_correccion_guarda_su_propia_propuesta_sin_reescribir_la_creacion(): void
    {
        $solicitud = $this->solicitud('Incorrecta');
        $solicitud->auditorias()->create([
            'usuario_id' => $this->vendedor->id,
            'estado_nuevo_id' => CatalogoEstadoSolicitud::idDe('Pendiente'),
            'motivo_reporte' => 'Creación original',
            'datos_snapshot' => ['cotizado' => ['tipo_cliente_nombre' => 'Distribuidor']],
        ]);
        $otroTipo = CatalogoTipoCliente::create(['nombre' => 'Mayorista']);
        $this->mock(ResolverDestinatariosAlertaSolicitudService::class)
            ->shouldReceive('conVendedorOpcional')->once()->andReturn(collect());
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\ActualizarActividadSesion::class,
        ])->actingAs($this->vendedor)->put(route('solicitudes.update', $solicitud), [
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $otroTipo->id,
            'catalogo_lista_descuento_id' => $this->lista->id,
            'monto_cotizado' => 6000,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $auditorias = $solicitud->auditorias()->orderBy('id')->get();
        $this->assertCount(2, $auditorias);
        $this->assertSame('Distribuidor', $auditorias[0]->datos_snapshot['cotizado']['tipo_cliente_nombre']);
        $this->assertSame('Mayorista', $auditorias[1]->datos_snapshot['cotizado']['tipo_cliente_nombre']);
        $this->assertSame('Plata', $auditorias[1]->datos_snapshot['cotizado']['lista_nombre']);
    }
}
