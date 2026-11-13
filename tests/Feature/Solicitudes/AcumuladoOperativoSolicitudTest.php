<?php

namespace Tests\Feature\Solicitudes;

use App\Models\Area;
use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\ErroresImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\ImporteProvisionalSolicitud;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\ApiExterna\ApiFieldFilterService;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Solicitudes\AcumuladoOperativoCliente;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use App\Services\Solicitudes\ImporteProvisionalSolicitudService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcumuladoOperativoSolicitudTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    private CatalogoProceso $proceso;

    private CatalogoTipoCliente $tipo;

    private User $vendedor;

    private int $pendiente;

    private int $respondida;

    private int $verificada;

    private int $incorrecta;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        app(EscalonamientoAutoridadConfig::class)->guardar(false);
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta', 'Cancelada'] as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->pendiente = (int) CatalogoEstadoSolicitud::idDe('Pendiente');
        $this->respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $this->verificada = (int) CatalogoEstadoSolicitud::idDe('Verificada');
        $this->incorrecta = (int) CatalogoEstadoSolicitud::idDe('Incorrecta');
        CatalogoListaDescuento::firstOrCreate(['nombre' => 'PUBLICO GENERAL'], ['monto_requerido' => 0, 'activo' => true]);
        $this->bronce = CatalogoListaDescuento::firstOrCreate(['nombre' => 'MAYOREO BRONCE'], ['monto_requerido' => 0.01, 'activo' => true]);
        $this->plata = CatalogoListaDescuento::firstOrCreate(['nombre' => 'MAYOREO PLATA'], ['monto_requerido' => 20000, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->plata->update(['monto_requerido' => 20000, 'activo' => true]);
        $this->proceso = CatalogoProceso::create(['nombre' => 'CAMBIO DE LISTA', 'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO, 'activo' => true]);
        $this->tipo = CatalogoTipoCliente::create(['nombre' => 'Comercial']);
        foreach (['solicitudes.crear', 'solicitudes.reportar', 'solicitudes.verificar', 'solicitudes.confirmar_pago', 'solicitudes.ver_listado', 'clientes.ver', 'clientes.carga_masiva'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Administrador', 'web');
        $departamento = Departamento::create(['nombre' => 'Comercial']);
        $area = Area::create(['departamento_id' => $departamento->id, 'nombre' => 'Mostrador']);
        $this->vendedor = User::factory()->create(['area_id' => $area->id]);
        $this->vendedor->givePermissionTo(['solicitudes.crear', 'solicitudes.reportar', 'solicitudes.confirmar_pago', 'solicitudes.ver_listado', 'clientes.ver', 'clientes.carga_masiva']);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }

    public function test_la_cotizacion_suma_el_operativo_y_deja_el_oficial(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00');
        $this->ajuste()->aplicarBeneficios($solicitud);

        $cliente->refresh();
        $presentado = app(AcumuladoOperativoCliente::class)->presentar($cliente);

        $this->assertSame('5000.00', $cliente->monto_venta_actual);
        $this->assertSame('5000.00', $presentado['acumulado_oficial']);
        $this->assertSame('30000.00', $presentado['acumulado_provisional']);
        $this->assertSame('35000.00', $presentado['acumulado_operativo']);
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
    }

    public function test_http_mantiene_la_lista_cuando_el_operativo_alcanza_el_umbral(): void
    {
        $cliente = $this->cliente('5000.00');
        $this->actingAs($this->vendedor)->post(route('solicitudes.store'), [
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'catalogo_tipo_cliente_id' => $this->tipo->id,
            'monto_cotizado' => 30000,
            'confirmo_informacion_escalonamiento' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $solicitud = SolicitudTag::query()->where('cliente_id', $cliente->id)->firstOrFail();
        $solicitud->update(['catalogo_lista_descuento_id' => $this->plata->id, 'monto_cotizado' => 30000]);
        $this->actingAs($this->vendedor)->put(route('solicitudes.actualizar_estado', $solicitud), [
            'catalogo_estado_solicitud_id' => $this->respondida,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $solicitud), [
            'monto_final_pagado' => 30000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $cliente->refresh();
        $solicitud->refresh();
        $visible = $this->actingAs($this->vendedor)->getJson('/api/clientes/'.$cliente->numero_cliente)->assertOk();

        $this->assertSame('5000.00', $cliente->monto_venta_actual);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertTrue($solicitud->pago_confirmado);
        $this->assertEquals(5000.0, (float) $visible->json('monto_venta_actual'));
        $this->assertSame('35000.00', $visible->json('acumulado_operativo'));
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
        $this->assertSame('30000.00', (string) ImporteProvisionalSolicitud::query()->first()->importe);

        $menudeo = $this->cliente('1000.00', '8802', true);
        $menudeo->update(['lista_actual_id' => CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->value('id')]);
        $solicitudMenudeo = $this->solicitud($menudeo, '30000.00', $this->pendiente, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitudMenudeo);
        $this->ajuste()->aplicarPagoConfirmado($solicitudMenudeo, 30000, $this->vendedor->id);
        $menudeo->refresh();
        $this->assertTrue($menudeo->es_menudeo);
        $this->assertSame((int) CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->value('id'), (int) $menudeo->lista_actual_id);
        $this->assertSame('1000.00', $menudeo->monto_venta_actual);
    }

    public function test_el_pago_sustituye_la_cotizacion_y_no_la_vuelve_a_sumar(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->ajuste()->aplicarPagoConfirmado($solicitud, 30000, $this->vendedor->id);
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->count());
        $this->assertSame('35000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));

        $otro = $this->cliente('5000.00', '8803');
        $parcial = $this->solicitud($otro, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($parcial);
        $this->ajuste()->aplicarPagoConfirmado($parcial, 28000, $this->vendedor->id);

        $vigente = ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $parcial->id)->where('estado', 'vigente')->first();
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $parcial->id)->count());
        $this->assertSame('pago_confirmado', $vigente->tipo);
        $this->assertSame('28000.00', (string) $vigente->importe);
        $this->assertSame('33000.00', app(AcumuladoOperativoCliente::class)->operativo($otro->fresh()));
        $this->assertSame('5000.00', $otro->fresh()->monto_venta_actual);
    }

    public function test_actualizacion_normal_conserva_provisionales_y_avisa(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);

        $vista = $this->revisar($this->plantilla($cliente, '8000'));
        $importacion = ImportacionCliente::findOrFail($vista['importacion_id']);
        $this->assertSame(ImportacionCliente::MODALIDAD_NORMAL, $importacion->resumen['revision']['modalidad_carga']);
        $this->assertTrue(collect($importacion->resumen['avisos'])->pluck('codigo')->contains('provisional_vigente'));
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_NORMAL, null);

        $this->assertSame('8000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
        $this->assertSame('38000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
    }

    public function test_el_corte_exige_fecha_y_hora_y_cierra_solo_hasta_ese_instante(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);

        $this->actingAs($this->vendedor)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo($this->plantilla($cliente, '35000')),
            'modalidad_carga' => ImportacionCliente::MODALIDAD_CORTE,
            'fecha_corte' => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors('fecha_corte');
        $this->actingAs($this->vendedor)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo($this->plantilla($cliente, '35000')),
            'modalidad_carga' => ImportacionCliente::MODALIDAD_CORTE,
            'fecha_corte' => now()->addHour()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('fecha_corte');

        $this->retroceder($solicitud, 2);
        $corte = now()->subDay()->format('Y-m-d H:i:s');
        $vista = $this->revisar($this->plantilla($cliente, '35000'), ImportacionCliente::MODALIDAD_CORTE, $corte);
        $fecha = ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte'];
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, $fecha);

        $cliente->refresh();
        $cerrado = ImporteProvisionalSolicitud::query()->where('cliente_id', $cliente->id)->first();
        $this->assertSame('35000.00', $cliente->monto_venta_actual);
        $this->assertSame('cerrado_por_corte', $cerrado->estado);
        $this->assertSame('indeterminada', $cerrado->inclusion);
        $this->assertNull($cerrado->clave_vigente);
        $this->assertSame(0, \App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud::count());
        $this->assertSame('35000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente));
    }

    public function test_una_solicitud_posterior_sigue_vigente_y_no_se_reparte_el_incremento(): void
    {
        $cliente = $this->cliente('5000.00');
        $primera = $this->solicitud($cliente, '30000.00', $this->respondida);
        $segunda = $this->solicitud($cliente, '10000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($primera);
        $this->ajuste()->aplicarBeneficios($segunda);
        $this->retroceder($primera, 2);

        $corte = now()->subDay()->format('Y-m-d H:i:s');
        $vista = $this->revisar($this->plantilla($cliente, '35000'), ImportacionCliente::MODALIDAD_CORTE, $corte);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte']);

        $this->assertSame('cerrado_por_corte', ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $primera->id)->value('estado'));
        $this->assertSame('vigente', ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $segunda->id)->value('estado'));
        $this->assertSame('10000.00', (string) ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $segunda->id)->value('importe'));
        $this->assertSame('45000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
    }

    public function test_repetir_el_corte_no_duplica_ni_vuelve_a_cerrar(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->retroceder($solicitud, 2);
        $corte = now()->subDay()->format('Y-m-d H:i:s');
        $vista = $this->revisar($this->plantilla($cliente, '35000'), ImportacionCliente::MODALIDAD_CORTE, $corte);
        $fecha = ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte'];
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, $fecha);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, $fecha);

        $this->assertSame(1, ImporteProvisionalSolicitud::query()->count());
        $this->assertSame('cerrado_por_corte', ImporteProvisionalSolicitud::query()->value('estado'));
        app(ImporteProvisionalSolicitudService::class)->cerrarPorCorte($cliente->fresh(), now()->subDay(), (int) $vista['importacion_id'], $this->vendedor->id);
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->count());
    }

    public function test_confirmar_el_pago_despues_del_corte_no_vuelve_a_sumar(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->retroceder($solicitud, 2);
        $vista = $this->revisar($this->plantilla($cliente, '35000'), ImportacionCliente::MODALIDAD_CORTE, now()->subDay()->format('Y-m-d H:i:s'));
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte']);

        $this->ajuste()->aplicarPagoConfirmado($solicitud->fresh(), 30000, $this->vendedor->id);
        $solicitud->refresh();
        $presentado = app(AcumuladoOperativoCliente::class)->presentar($cliente->fresh());

        $this->assertTrue($solicitud->pago_confirmado);
        $this->assertSame('35000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('35000.00', $presentado['acumulado_operativo']);
        $this->assertSame('0.00', $presentado['acumulado_provisional']);
        $this->assertTrue($presentado['inclusion_indeterminada']);
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
        $this->assertSame(0, \App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud::count());

        try {
            app(ImporteProvisionalSolicitudService::class)->abrirCorreccionAdministrativa($solicitud, '30000.00', '   ', $this->vendedor->id);
            $this->fail('La corrección sin evidencia no debe abrir un provisional.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('evidencia', $e->errors());
        }
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
    }

    public function test_vencer_despues_del_cierre_conserva_la_lista_y_antes_revoca_el_provisional(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->retroceder($solicitud, 2);
        $vista = $this->revisar($this->plantilla($cliente, '9000'), ImportacionCliente::MODALIDAD_CORTE, now()->subDay()->format('Y-m-d H:i:s'));
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte']);
        $solicitud->created_at = now()->subDays(2);
        $solicitud->save();

        $this->artisan('pagos:rechazar-vencidos')->assertSuccessful();

        $cliente->refresh();
        $this->assertSame('9000.00', $cliente->monto_venta_actual);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame('cerrado_por_corte', ImporteProvisionalSolicitud::query()->value('estado'));
        $this->assertSame($this->incorrecta, (int) $solicitud->fresh()->catalogo_estado_solicitud_id);
        $this->assertNotEmpty(AuditoriaSolicitud::query()->first()->datos_snapshot['incidencia_lista'] ?? null);

        $otro = $this->cliente('5000.00', '8810');
        $vigente = $this->solicitud($otro, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($vigente);
        $this->ajuste()->revertirBeneficios($vigente, $this->vendedor->id);
        $this->assertSame('revocado', ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $vigente->id)->value('estado'));
        $this->assertSame('5000.00', $otro->fresh()->monto_venta_actual);
        $this->assertNotSame($this->plata->id, $otro->fresh()->lista_actual_id);
    }

    public function test_una_importacion_parcial_no_cierra_filas_fallidas_ni_la_previsualizacion(): void
    {
        $bueno = $this->cliente('5000.00', '8820');
        $fallido = $this->cliente('5000.00', '8821');
        $ausente = $this->cliente('5000.00', '8822');
        foreach ([$bueno, $fallido, $ausente] as $clienteFila) {
            $solicitudFila = $this->solicitud($clienteFila, '30000.00', $this->respondida);
            $this->ajuste()->aplicarBeneficios($solicitudFila);
            $this->retroceder($solicitudFila, 2);
        }
        $csv = "numero_cliente,nombre,codigo_lista,monto_venta_actual,vendedor_id\n"
            ."8820,Cliente,,35000,\n"
            ."8821,Cliente,,no-es-monto,\n";
        $vista = $this->revisar($csv, ImportacionCliente::MODALIDAD_CORTE, now()->subHour()->format('Y-m-d H:i:s'));
        $this->assertSame(3, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());

        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte']);

        $this->assertSame('cerrado_por_corte', ImporteProvisionalSolicitud::query()->where('cliente_id', $bueno->id)->value('estado'));
        $this->assertSame('vigente', ImporteProvisionalSolicitud::query()->where('cliente_id', $fallido->id)->value('estado'));
        $this->assertSame('vigente', ImporteProvisionalSolicitud::query()->where('cliente_id', $ausente->id)->value('estado'));
        $this->assertSame('5000.00', $fallido->fresh()->monto_venta_actual);
        $this->assertSame('35000.00', $bueno->fresh()->monto_venta_actual);
    }

    public function test_confirmar_con_otra_modalidad_o_fecha_obliga_a_nueva_revision(): void
    {
        $cliente = $this->cliente('5000.00');
        $this->ajuste()->aplicarBeneficios($this->solicitud($cliente, '30000.00', $this->respondida));
        $normal = $this->revisar($this->plantilla($cliente, '8000'));
        $this->actingAs($this->vendedor)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $normal['importacion_id'],
            'token' => $normal['token'],
            'modalidad_carga' => ImportacionCliente::MODALIDAD_CORTE,
            'fecha_corte' => now()->subHour()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('modalidad_carga');
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('vigente', ImporteProvisionalSolicitud::query()->value('estado'));

        $corte = now()->subHour()->format('Y-m-d H:i:s');
        $vista = $this->revisar($this->plantilla($cliente, '8000'), ImportacionCliente::MODALIDAD_CORTE, $corte);
        $this->actingAs($this->vendedor)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $vista['importacion_id'],
            'token' => $vista['token'],
            'modalidad_carga' => ImportacionCliente::MODALIDAD_CORTE,
            'fecha_corte' => now()->subHours(3)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('fecha_corte');
        $this->assertSame('vigente', ImporteProvisionalSolicitud::query()->value('estado'));
    }

    public function test_tienda_sin_monto_y_atencion_no_crean_provisional_ni_mueven_la_ficha(): void
    {
        $cliente = $this->cliente('5000.00');
        $tienda = $this->solicitud($cliente, '30000.00', $this->respondida);
        $tienda->update(['compra_en_tienda' => true]);
        $this->ajuste()->aplicarBeneficios($tienda->fresh());
        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $tienda), ['modo' => 'pago_sin_monto'])->assertRedirect();
        $atencion = $this->solicitud($cliente, '0.00', $this->respondida);
        $atencion->update(['compra_en_tienda' => true, 'monto_cotizado' => 0]);
        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $atencion), ['modo' => 'atencion_gelia'])->assertRedirect();

        $operativo = CatalogoProceso::create(['nombre' => 'REMISION', 'categoria_flujo' => CatalogoProceso::CATEGORIA_OPERATIVO, 'activo' => true]);
        $operativa = $this->solicitud($cliente, '30000.00', $this->respondida);
        $operativa->update(['catalogo_proceso_id' => $operativo->id]);
        $this->ajuste()->aplicarBeneficios($operativa->fresh());

        $inactivo = $this->cliente('5000.00', '8830');
        $inactivo->update(['es_inactivo' => true]);
        $listaAntes = $inactivo->lista_actual_id;
        $vendedorAntes = $inactivo->vendedor_id;
        $this->ajuste()->aplicarBeneficios($this->solicitud($inactivo, '30000.00', $this->respondida));

        $this->assertSame(0, ImporteProvisionalSolicitud::query()->where('cliente_id', $cliente->id)->count());
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
        $inactivo->refresh();
        $this->assertTrue($inactivo->es_inactivo);
        $this->assertSame($listaAntes, $inactivo->lista_actual_id);
        $this->assertSame($vendedorAntes, $inactivo->vendedor_id);
        $this->assertSame('5000.00', $inactivo->monto_venta_actual);
    }

    public function test_la_api_conserva_el_oficial_y_agrega_las_tres_claves(): void
    {
        $cliente = $this->cliente('5000.00');
        $this->ajuste()->aplicarBeneficios($this->solicitud($cliente, '30000.00', $this->respondida));

        $respuesta = $this->actingAs($this->vendedor)->getJson('/api/clientes?q='.$cliente->numero_cliente)->assertOk();
        $this->assertEquals(5000.0, (float) $respuesta->json('0.monto_venta_actual'));
        $this->assertSame('5000.00', $respuesta->json('0.acumulado_oficial'));
        $this->assertSame('30000.00', $respuesta->json('0.acumulado_provisional'));
        $this->assertSame('35000.00', $respuesta->json('0.acumulado_operativo'));

        $externa = app(ApiFieldFilterService::class)->filtrarCliente($cliente->fresh(), ['monto_venta_actual', 'nombre']);
        $this->assertSame(5000.0, (float) $externa['monto_venta_actual']);
        $this->assertSame('5000.00', $externa['acumulado_oficial']);
        $this->assertSame('30000.00', $externa['acumulado_provisional']);
        $this->assertSame('35000.00', $externa['acumulado_operativo']);
    }

    public function test_la_reconciliacion_no_escribe_en_seco_ni_inserta_inciertas(): void
    {
        $cierta = $this->cliente('5000.00', '8840');
        $incierta = $this->cliente('5000.00', '8841');
        $sinCarga = $this->cliente('5000.00', '8842');
        $tienda = $this->cliente('5000.00', '8843');
        $solicitudCierta = $this->solicitud($cierta, '30000.00', $this->respondida);
        $solicitudCierta->created_at = now();
        $solicitudCierta->save();
        $carga = HistorialMontoCliente::create([
            'cliente_id' => $cierta->id,
            'monto_anterior' => '0.00',
            'monto_nuevo' => '5000.00',
            'diferencia_aplicada' => '5000.00',
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA,
        ]);
        $carga->created_at = now()->subDay();
        $carga->save();
        $posterior = $this->solicitud($incierta, '30000.00', $this->verificada);
        $posterior->created_at = now()->subDays(2);
        $posterior->save();
        HistorialMontoCliente::create([
            'cliente_id' => $incierta->id,
            'monto_anterior' => '0.00',
            'monto_nuevo' => '5000.00',
            'diferencia_aplicada' => '5000.00',
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA,
        ]);
        $this->solicitud($sinCarga, '30000.00', $this->respondida);
        $flujoTienda = $this->solicitud($tienda, '0.00', $this->respondida);
        $flujoTienda->update(['compra_en_tienda' => true, 'monto_cotizado' => 0]);

        $this->artisan('solicitudes:reconciliar-importes-provisionales')->assertSuccessful();
        $this->assertSame(0, ImporteProvisionalSolicitud::count());

        $this->artisan('solicitudes:reconciliar-importes-provisionales', ['--aplicar' => true])->assertSuccessful();
        $this->assertSame(1, ImporteProvisionalSolicitud::count());
        $this->assertSame($solicitudCierta->id, ImporteProvisionalSolicitud::query()->value('solicitud_tag_id'));
        $this->assertSame('vigente', ImporteProvisionalSolicitud::query()->value('estado'));

        $this->artisan('solicitudes:reconciliar-importes-provisionales', ['--aplicar' => true])->assertSuccessful();
        $this->assertSame(1, ImporteProvisionalSolicitud::count());
    }

    public function test_confirmar_cero_revoca_la_cotizacion_vigente(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->pendiente);
        $this->actingAs($this->vendedor)->put(route('solicitudes.actualizar_estado', $solicitud), [
            'catalogo_estado_solicitud_id' => $this->respondida,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $solicitud), [
            'monto_final_pagado' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $cliente->refresh();
        $solicitud->refresh();
        $this->assertSame('5000.00', $cliente->monto_venta_actual);
        $this->assertTrue($solicitud->pago_confirmado);
        $this->assertSame('0.00', (string) $solicitud->monto_aplicado_al_cliente);
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
        $this->assertSame('revocado', ImporteProvisionalSolicitud::query()->value('estado'));
        $this->assertSame('5000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente));
    }

    public function test_el_corte_anterior_al_pago_no_usa_la_fecha_de_la_cotizacion(): void
    {
        $zona = config('app.timezone');
        $cliente = $this->cliente('5000.00', '8811');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        Carbon::setTestNow(Carbon::parse('2026-11-07 10:00:00', $zona));
        try {
            $this->ajuste()->aplicarBeneficios($solicitud);
            Carbon::setTestNow(Carbon::parse('2026-11-11 16:00:00', $zona));
            $this->ajuste()->aplicarPagoConfirmado($solicitud, 28000, $this->vendedor->id);
        } finally {
            Carbon::setTestNow();
        }

        $fila = ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $solicitud->id)->firstOrFail();
        $this->assertSame('2026-11-07 10:00:00', $fila->fecha_operacion->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-11 16:00:00', $fila->fecha_confirmacion->format('Y-m-d H:i:s'));

        $vista = $this->revisar($this->plantilla($cliente, '8000'), ImportacionCliente::MODALIDAD_CORTE, '2026-11-10 12:00:00');
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte']);

        $fila->refresh();
        $this->assertSame('vigente', $fila->estado);
        $this->assertSame('8000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('36000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));

        app(ImporteProvisionalSolicitudService::class)->cerrarPorCorte(
            $cliente->fresh(),
            Carbon::parse('2026-11-11 18:00:00', $zona),
            (int) $vista['importacion_id'],
            $this->vendedor->id,
        );
        $this->assertSame('cerrado_por_corte', $fila->fresh()->estado);
        $this->assertSame('2026-11-07 10:00:00', $fila->fresh()->fecha_operacion->format('Y-m-d H:i:s'));
        $this->assertSame('8000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('8000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
    }

    public function test_dos_cortes_consecutivos_no_se_comparan_con_la_hora_de_carga(): void
    {
        $cliente = $this->cliente('5000.00', '8812');
        $primero = '2026-11-08 09:00:00';
        $segundo = '2026-11-11 15:00:00';
        $vista = $this->revisar($this->plantilla($cliente, '12000'), ImportacionCliente::MODALIDAD_CORTE, $primero);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte']);
        $primeraId = (int) $vista['importacion_id'];
        HistorialMontoCliente::query()->where('importacion_cliente_id', $primeraId)->update(['created_at' => '2026-11-12 18:00:00']);
        ImportacionCliente::query()->whereKey($primeraId)->update(['created_at' => '2026-11-12 10:00:00']);

        $this->assertTrue(
            Carbon::parse(HistorialMontoCliente::query()->where('importacion_cliente_id', $primeraId)->value('created_at'))
                ->greaterThan(Carbon::parse($segundo))
        );

        $siguiente = $this->revisar($this->plantilla($cliente, '18000'), ImportacionCliente::MODALIDAD_CORTE, $segundo);
        $this->confirmar($siguiente, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($siguiente['importacion_id'])->resumen['revision']['fecha_corte']);

        $this->assertSame('18000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertTrue(
            ImportacionCliente::find($siguiente['importacion_id'])->created_at
                ->greaterThan(ImportacionCliente::find($primeraId)->created_at)
        );

        $atras = $this->revisar($this->plantilla($cliente, '9000'), ImportacionCliente::MODALIDAD_CORTE, '2026-11-09 09:00:00');
        $this->confirmar($atras, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($atras['importacion_id'])->resumen['revision']['fecha_corte']);
        $this->assertSame('18000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_el_corte_sin_cambio_de_monto_bloquea_un_corte_anterior(): void
    {
        $cliente = $this->cliente('5000.00', '8815');
        $vista = $this->revisar($this->plantilla($cliente, '5000'), ImportacionCliente::MODALIDAD_CORTE, '2026-11-12 10:00:00');
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte']);

        $cliente->refresh();
        $this->assertSame('5000.00', $cliente->monto_venta_actual);
        $this->assertSame(0, HistorialMontoCliente::query()->where('cliente_id', $cliente->id)->count());
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->where('cliente_id', $cliente->id)->count());
        $this->assertSame('2026-11-12 10:00:00', $cliente->fecha_ultimo_corte_administrativo->format('Y-m-d H:i:s'));

        $atras = $this->revisar($this->plantilla($cliente, '9000'), ImportacionCliente::MODALIDAD_CORTE, '2026-11-12 08:00:00');
        $this->confirmar($atras, ImportacionCliente::MODALIDAD_CORTE, ImportacionCliente::find($atras['importacion_id'])->resumen['revision']['fecha_corte']);

        $cliente->refresh();
        $this->assertSame('5000.00', $cliente->monto_venta_actual);
        $this->assertSame('2026-11-12 10:00:00', $cliente->fecha_ultimo_corte_administrativo->format('Y-m-d H:i:s'));
        $this->assertSame(1, ErroresImportacionCliente::query()->where('importacion_cliente_id', $atras['importacion_id'])->where('campo', 'fecha_corte')->count());
    }

    public function test_la_conciliacion_incluida_usa_el_operativo_sin_duplicar_el_pago(): void
    {
        $periodo = EscalonamientoPeriodo::create([
            'anio' => 2026,
            'mes' => 11,
            'zona_horaria' => 'America/Mexico_City',
            'estado' => 'abierto',
        ]);
        $cliente = $this->cliente('5000.00', '8813');
        $incluida = $this->solicitud($cliente, '3000.00', $this->respondida);
        $otra = $this->solicitud($cliente, '12000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($incluida);
        $this->ajuste()->aplicarBeneficios($otra);
        $cliente->update(['lista_actual_id' => $this->plata->id]);
        EscalonamientoConciliacionSolicitud::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'solicitud_tag_id' => $incluida->id,
            'importe_asignado' => '3000.00',
            'estado' => EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA,
            'procedencia' => EscalonamientoConciliacionSolicitud::PROCEDENCIA_DOCUMENTO,
            'inclusion_acumulado' => EscalonamientoConciliacionSolicitud::INCLUSION_INCLUIDA,
            'clave_asignacion' => 'incluida:'.$incluida->id,
            'evidencia' => 'Documento ya incluido en el oficial.',
        ]);

        $insuficiente = $this->ajuste()->proyectarMonto($cliente->fresh(), $incluida->fresh(), 3000);
        $this->assertSame('pago_insuficiente', $insuficiente['efecto_lista']);
        $this->assertSame('5000.00', $insuficiente['venta_oficial']);
        $this->assertSame(5000.0, $insuficiente['proyectado']);

        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $incluida), [
            'monto_final_pagado' => 3000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $incluida->refresh();
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('pago_insuficiente', $incluida->motivo_incorrecta);
        $this->assertFalse($incluida->pago_confirmado);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertNull(ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $incluida->id)->where('estado', 'vigente')->value('id'));
        $this->assertSame('12000.00', (string) ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $otra->id)->where('estado', 'vigente')->value('importe'));
        $this->assertSame('17000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));

        $cubre = $this->cliente('5000.00', '8814');
        $pago = $this->solicitud($cubre, '3000.00', $this->respondida);
        $apoyo = $this->solicitud($cubre, '16000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($pago);
        $this->ajuste()->aplicarBeneficios($apoyo);
        $cubre->update(['lista_actual_id' => $this->plata->id]);
        EscalonamientoConciliacionSolicitud::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'solicitud_tag_id' => $pago->id,
            'importe_asignado' => '3000.00',
            'estado' => EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA,
            'procedencia' => EscalonamientoConciliacionSolicitud::PROCEDENCIA_DOCUMENTO,
            'inclusion_acumulado' => EscalonamientoConciliacionSolicitud::INCLUSION_INCLUIDA,
            'clave_asignacion' => 'incluida:'.$pago->id,
            'evidencia' => 'Documento ya incluido en el oficial.',
        ]);

        $decision = $this->ajuste()->proyectarMonto($cubre->fresh(), $pago->fresh(), 3000);
        $this->assertSame('sin_cambio', $decision['efecto_lista']);
        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $pago), [
            'monto_final_pagado' => 3000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $cubre->refresh();
        $this->assertSame('5000.00', $cubre->monto_venta_actual);
        $this->assertSame($this->plata->id, $cubre->lista_actual_id);
        $this->assertSame('24000.00', app(AcumuladoOperativoCliente::class)->operativo($cubre));
    }

    private function ajuste(): AjustarMontoPorSolicitudService
    {
        return app(AjustarMontoPorSolicitudService::class);
    }

    private function cliente(string $monto, string $numero = '8801', bool $menudeo = false): Cliente
    {
        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $this->bronce->id,
            'monto_venta_actual' => $monto,
            'es_menudeo' => $menudeo,
            'es_inactivo' => false,
            'vendedor_id' => $this->vendedor->id,
        ]);
    }

    private function solicitud(Cliente $cliente, string $monto, ?int $estado = null, ?int $lista = null): SolicitudTag
    {
        return SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => $estado ?? $this->pendiente,
            'catalogo_lista_descuento_id' => $lista ?? $this->plata->id,
            'catalogo_tipo_cliente_id' => $this->tipo->id,
            'monto_cotizado' => $monto,
            'pago_confirmado' => false,
        ]);
    }

    private function retroceder(SolicitudTag $solicitud, int $dias): void
    {
        ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $solicitud->id)->update([
            'fecha_operacion' => now()->subDays($dias)->format('Y-m-d H:i:s'),
        ]);
    }

    private function plantilla(Cliente $cliente, string $monto): string
    {
        return "numero_cliente,nombre,codigo_lista,monto_venta_actual,vendedor_id\n{$cliente->numero_cliente},Cliente,,{$monto},\n";
    }

    private function archivo(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('clientes.csv', $csv);
    }

    private function revisar(string $csv, string $modalidad = ImportacionCliente::MODALIDAD_NORMAL, ?string $fecha = null): array
    {
        $respuesta = $this->actingAs($this->vendedor)->post(route('admin.clientes.importar.previsualizar'), array_filter([
            'archivo' => $this->archivo($csv),
            'modalidad_carga' => $modalidad,
            'fecha_corte' => $fecha,
        ], fn ($valor) => $valor !== null));
        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('previsualizacion_importacion');

        return session('previsualizacion_importacion');
    }

    private function confirmar(array $vista, string $modalidad, ?string $fecha): void
    {
        $this->actingAs($this->vendedor)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $vista['importacion_id'],
            'token' => $vista['token'],
            'modalidad_carga' => $modalidad,
            'fecha_corte' => $fecha,
        ])->assertRedirect();
    }
}
