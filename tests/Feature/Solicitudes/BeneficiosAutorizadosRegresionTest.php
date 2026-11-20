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

class BeneficiosAutorizadosRegresionTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    private CatalogoProceso $proceso;

    private CatalogoTipoCliente $tipo;

    private CatalogoTipoCliente $activo;

    private CatalogoTipoCliente $nuevo;

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
        $this->activo = CatalogoTipoCliente::create(['nombre' => 'ACTIVO', 'activo' => true]);
        $this->nuevo = CatalogoTipoCliente::create(['nombre' => 'NUEVO', 'activo' => true]);
        CatalogoTipoCliente::reiniciarCache();
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


    // ---- F1: NUEVO nunca degrada a un cliente activo con historial -------------------------

    public function test_f1_nuevo_explicito_se_registra_activo_y_la_ficha_no_se_degrada(): void
    {
        $cliente = $this->cliente('5000.00');
        $this->actingAs($this->vendedor)->post(route('solicitudes.store'), [
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'catalogo_tipo_cliente_id' => $this->nuevo->id,
            'monto_cotizado' => 30000,
            'confirmo_informacion_escalonamiento' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $solicitud = SolicitudTag::query()->where('cliente_id', $cliente->id)->firstOrFail();
        $this->assertSame($this->activo->id, $solicitud->catalogo_tipo_cliente_id);

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $this->ajuste()->aplicarBeneficios($solicitud->fresh());
        $this->assertSame($this->activo->id, $cliente->fresh()->catalogo_tipo_cliente_id);
    }

    public function test_f1_una_solicitud_heredada_con_nuevo_no_pisa_la_ficha_al_aprobarse(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $solicitud->update(['catalogo_tipo_cliente_id' => $this->nuevo->id]);

        $this->ajuste()->aplicarBeneficios($solicitud->fresh());

        $this->assertSame($this->activo->id, $cliente->fresh()->catalogo_tipo_cliente_id);
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_f1_nuevo_se_conserva_en_un_cliente_sin_historial(): void
    {
        $cliente = $this->cliente('0.00');
        $cliente->update(['catalogo_tipo_cliente_id' => null]);
        $solicitud = $this->solicitud($cliente, '3000.00', $this->respondida);
        $solicitud->update(['catalogo_tipo_cliente_id' => $this->nuevo->id]);

        $this->ajuste()->aplicarBeneficios($solicitud->fresh());

        $this->assertSame($this->nuevo->id, $cliente->fresh()->catalogo_tipo_cliente_id);
    }

    public function test_f1_la_reversion_conserva_un_cambio_de_tipo_posterior_legitimo(): void
    {
        $otro = CatalogoTipoCliente::create(['nombre' => 'MAYORISTA']);
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $solicitud->update(['catalogo_tipo_cliente_id' => $this->tipo->id]);
        $this->ajuste()->aplicarBeneficios($solicitud->fresh());
        $this->assertSame($this->tipo->id, $cliente->fresh()->catalogo_tipo_cliente_id);

        $cliente->update(['catalogo_tipo_cliente_id' => $otro->id]);
        $solicitud->update(['catalogo_estado_solicitud_id' => $this->incorrecta]);
        $this->ajuste()->revertirBeneficios($solicitud->fresh(), $this->vendedor->id);

        $this->assertSame($otro->id, $cliente->fresh()->catalogo_tipo_cliente_id);
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
    }

    // ---- F2/H3: una cotización sin lista destino es una operación válida que "mantiene lista" ----

    public function test_f2_h3_cotizacion_sin_lista_mantiene_la_lista_y_conserva_su_provisional(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = app(\App\Services\Solicitudes\CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 100,
        ], $this->vendedor->id);
        $this->assertNull($solicitud->catalogo_lista_descuento_id);

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $this->ajuste()->aplicarBeneficios($solicitud->fresh());

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('5100.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
    }

    // ---- H4: sin estado aprobatorio no hay beneficios --------------------------------------

    public function test_h4_aplicar_beneficios_en_una_solicitud_pendiente_no_concede_nada(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->pendiente);

        $this->assertSame([], $this->ajuste()->aplicarBeneficios($solicitud));

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->count());
        $this->assertSame('5000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
    }

    // ---- H2: ningún job recupera beneficios de una solicitud cancelada, rechazada o vencida ----

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function estadosSinAutorizacion(): array
    {
        return [
            'cancelada' => ['Cancelada', null],
            'rechazada (incorrecta)' => ['Incorrecta', 'error_reportado'],
            'vencida' => ['Incorrecta', 'vencimiento_pago'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('estadosSinAutorizacion')]
    public function test_h2_el_job_no_recupera_beneficios_de_una_solicitud_no_autorizada(string $estado, ?string $motivo): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        // Sin pasar por el modelo: simula una aplicación que quedó fallida antes de la cancelación.
        SolicitudTag::query()->whereKey($solicitud->id)->update([
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe($estado),
            'motivo_incorrecta' => $motivo,
            'aplicacion_cliente' => 'fallida',
        ]);

        (new \App\Jobs\Solicitudes\RecuperarAplicacionClienteJob($solicitud->id))->handle($this->ajuste());

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->count());
        $this->assertSame(0, \App\Models\SolicitudBeneficio::query()->count());
        $this->assertSame('no_aplica', $solicitud->fresh()->aplicacion_cliente);
    }

    public function test_h2_cancelar_o_rechazar_cierra_la_aplicacion_pendiente(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $solicitud->forceFill(['aplicacion_cliente' => 'fallida'])->save();

        $solicitud->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);

        $this->assertSame('no_aplica', $solicitud->fresh()->aplicacion_cliente);
    }

    public function test_h2_la_recuperacion_manual_rechaza_una_solicitud_no_autorizada(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        SolicitudTag::query()->whereKey($solicitud->id)->update([
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada'),
            'aplicacion_cliente' => 'fallida',
        ]);
        Permission::findOrCreate('clientes.recuperar_aplicacion_autorizada', 'web');
        $this->vendedor->givePermissionTo('clientes.recuperar_aplicacion_autorizada');

        $this->actingAs($this->vendedor)
            ->post(route('admin.clientes.recuperacion.aplicar', $solicitud))
            ->assertSessionHasErrors('solicitud');

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame(0, ImporteProvisionalSolicitud::query()->count());
    }

    public function test_h2_una_solicitud_autorizada_si_se_recupera(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $solicitud->forceFill(['aplicacion_cliente' => 'fallida'])->save();

        (new \App\Jobs\Solicitudes\RecuperarAplicacionClienteJob($solicitud->id))->handle($this->ajuste());

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('aplicada', $solicitud->fresh()->aplicacion_cliente);
        $this->assertSame('35000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
    }

    // ---- H1: un pago final que no respalda la lista no la sostiene --------------------------

    public function test_h1_pago_final_insuficiente_retira_solo_la_lista_y_conserva_el_pago(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);

        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $solicitud), [
            'monto_final_pagado' => 1000,
        ])->assertRedirect();

        $solicitud->refresh();
        $cliente->refresh();
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertSame($this->respondida, $solicitud->catalogo_estado_solicitud_id);
        $this->assertTrue($solicitud->pago_confirmado);
        $this->assertSame($this->tipo->id, (int) $cliente->catalogo_tipo_cliente_id);
        $this->assertSame('5000.00', $cliente->monto_venta_actual);
        $vigente = ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->sole();
        $this->assertSame('pago_confirmado', $vigente->tipo);
        $this->assertSame('1000.00', (string) $vigente->importe);
        $this->assertSame('6000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente));
        $lista = \App\Models\SolicitudBeneficio::query()->where('solicitud_tag_id', $solicitud->id)->where('campo', \App\Models\SolicitudBeneficio::CAMPO_LISTA)->sole();
        $this->assertSame(\App\Models\SolicitudBeneficio::ESTADO_REVOCADO, $lista->estado);
    }

    public function test_h1_pago_final_que_respalda_la_lista_la_conserva(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);

        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $solicitud), [
            'monto_final_pagado' => 30000,
        ])->assertRedirect();

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('35000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
    }

    public function test_h1_pago_parcial_es_un_abono_y_no_sustituye_la_cotizacion(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($solicitud);

        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $solicitud), [
            'monto_final_pagado' => 1000,
            'pago_parcial' => true,
        ])->assertRedirect();

        $solicitud->refresh();
        $this->assertFalse($solicitud->pago_confirmado);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $vigente = ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->sole();
        $this->assertSame('cotizacion_aprobada', $vigente->tipo);
        $this->assertSame('30000.00', (string) $vigente->importe);
        $this->assertSame(1, AuditoriaSolicitud::query()->where('solicitud_id', $solicitud->id)->where('motivo_reporte', 'like', 'ABONO PARCIAL%')->count());
    }

    public function test_h1_otra_autorizacion_vigente_sostiene_la_lista(): void
    {
        $cliente = $this->cliente('5000.00');
        $otra = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($otra);
        $propia = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($propia);

        // Un pago de 1,000 deja 'propia' sin respaldo, pero 'otra' sigue sosteniendo Plata con su cotización.
        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $propia), [
            'monto_final_pagado' => 1000,
        ])->assertRedirect();

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
    }

    // ---- Escenario comercial completo: dos cotizaciones, pagos, cancelación e importación ----

    public function test_operativo_con_dos_cotizaciones_pagos_cancelacion_e_importacion_normal(): void
    {
        $op = app(AcumuladoOperativoCliente::class);
        $cliente = $this->cliente('5000.00');

        $a = $this->solicitud($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($a);
        $this->assertSame('35000.00', $op->operativo($cliente->fresh()));

        $b = $this->solicitud($cliente, '10000.00', $this->respondida, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($b);
        $this->assertSame('45000.00', $op->operativo($cliente->fresh()));
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);

        // El pago de A sustituye su cotización (28,000 en lugar de 30,000), sin sumarse otra vez.
        $this->ajuste()->aplicarPagoConfirmado($a->fresh(), 28000, $this->vendedor->id);
        $this->assertSame('43000.00', $op->operativo($cliente->fresh()));
        $this->assertSame(2, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());

        // Cancelar B revierte solo lo atribuible a B.
        $b->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);
        $this->ajuste()->revertirBeneficios($b->fresh(), $this->vendedor->id);
        $this->assertSame('33000.00', $op->operativo($cliente->fresh()));
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);

        // La carga normal actualiza el oficial y conserva los provisionales vigentes; no cierra nada ni asciende.
        $vista = $this->revisar($this->plantilla($cliente, '40000'), ImportacionCliente::MODALIDAD_NORMAL);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_NORMAL, null);
        $cliente->refresh();
        $this->assertSame('40000.00', $cliente->monto_venta_actual);
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
        $this->assertSame('68000.00', $op->operativo($cliente));
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
    }

    // ---- H5: una sola definición de lista efectiva -----------------------------------------

    public function test_h5_la_lista_vigente_de_un_menudeo_o_inactivo_es_publico_general(): void
    {
        $pg = (int) CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->value('id');
        $proyeccion = app(\App\Services\Escalonamiento\ProyeccionClienteEscalonamiento::class);

        $menudeo = $this->cliente('1000.00', '8810', true);
        $menudeo->update(['lista_actual_id' => $this->plata->id]);
        $this->assertSame($pg, $proyeccion->listaVigenteId($menudeo->fresh()));

        $mayoreo = $this->cliente('1000.00', '8811');
        $mayoreo->update(['lista_actual_id' => $this->plata->id]);
        $this->assertSame($this->plata->id, $proyeccion->listaVigenteId($mayoreo->fresh()));
    }

    // ---- H6: el oficial solo lo escribe la importación ------------------------------------------

    public function test_h6_la_edicion_administrativa_ignora_el_monto_oficial_sin_permiso(): void
    {
        $cliente = $this->cliente('5000.00');

        $this->actingAs($this->vendedor)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.clientes.update', $cliente), [
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => 'Nombre corregido',
                'monto_venta_actual' => 999999,
                'lista_actual_id' => $this->bronce->id,
            ]);

        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('Nombre corregido', $cliente->fresh()->nombre, 'La edición se aplicó; solo el oficial se ignora.');
    }

    public function test_h6_con_permiso_editar_aplica_el_monto_oficial(): void
    {
        $cliente = $this->cliente('5000.00');
        Permission::findOrCreate('clientes.editar', 'web');
        $this->vendedor->givePermissionTo(['clientes.editar']);

        $this->actingAs($this->vendedor)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.clientes.update', $cliente), [
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => 'Nombre corregido',
                'monto_venta_actual' => 999999,
                'lista_actual_id' => $this->bronce->id,
            ]);

        $this->assertSame('999999.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('Nombre corregido', $cliente->fresh()->nombre);
    }

    // ---- Lista automática por acumulado operativo ----------------------------------------------

    public function test_la_cotizacion_sin_lista_que_alcanza_un_escalon_refleja_la_lista_operativa_al_autorizarse(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->cotizacionSinLista($cliente, '30000.00', $this->pendiente);

        // Pendiente: no concede descuentos ni escribe la lista del cliente.
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertNull($solicitud->fresh()->catalogo_lista_descuento_id);

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $this->ajuste()->aplicarBeneficios($solicitud->fresh());

        $this->assertSame($this->plata->id, $solicitud->fresh()->catalogo_lista_descuento_id);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame('35000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
        $beneficio = \App\Models\SolicitudBeneficio::query()->where('solicitud_tag_id', $solicitud->id)->where('campo', \App\Models\SolicitudBeneficio::CAMPO_LISTA)->sole();
        $this->assertSame((string) $this->bronce->id, (string) $beneficio->valor_anterior);
    }

    public function test_la_cotizacion_que_no_alcanza_otro_escalon_conserva_la_lista(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->cotizacionSinLista($cliente, '3000.00', $this->respondida);

        $this->ajuste()->aplicarBeneficios($solicitud);

        $this->assertNull($solicitud->fresh()->catalogo_lista_descuento_id);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('8000.00', app(AcumuladoOperativoCliente::class)->operativo($cliente->fresh()));
    }

    public function test_dos_cotizaciones_consecutivas_usan_el_operativo_con_la_anterior_autorizada_y_su_pago(): void
    {
        $oro = $this->oro();
        $op = app(AcumuladoOperativoCliente::class);
        $cliente = $this->cliente('5000.00');
        $a = $this->cotizacionSinLista($cliente, '30000.00', $this->pendiente);
        $b = $this->cotizacionSinLista($cliente, '50000.00', $this->pendiente);

        $a->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $this->ajuste()->aplicarBeneficios($a->fresh());
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);

        // B se capturó cuando A aún no sumaba. Al autorizarse se calcula con A ya incluida: 35,000 + 50,000.
        $b->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $this->ajuste()->aplicarBeneficios($b->fresh());
        $this->assertSame($oro->id, $b->fresh()->catalogo_lista_descuento_id);
        $this->assertSame($oro->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('85000.00', $op->operativo($cliente->fresh()));

        // El pago de A (5,000) sustituye su cotización de 30,000: las nuevas cotizaciones parten de 60,000.
        $this->ajuste()->aplicarPagoConfirmado($a->fresh(), 5000, $this->vendedor->id);
        $this->assertSame('60000.00', $op->operativo($cliente->fresh()));
        $this->assertSame('60000.00', $op->paraLista($cliente->fresh()));
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_un_pago_insuficiente_devuelve_la_lista_administrativa_y_no_asciende_por_monto(): void
    {
        $oro = $this->oro();
        // El oficial ya alcanza Plata, pero la lista administrativa del cliente sigue en Bronce (sin código de lista).
        $cliente = $this->cliente('25000.00');
        $solicitud = $this->solicitud($cliente, '60000.00', $this->respondida, $oro->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->assertSame($oro->id, $cliente->fresh()->lista_actual_id);

        $this->actingAs($this->vendedor)->put(route('solicitudes.confirmar_pago', $solicitud), [
            'monto_final_pagado' => 1000,
        ])->assertRedirect();

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('25000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_cancelar_una_solicitud_sin_concesion_no_asciende_al_cliente(): void
    {
        $cliente = $this->cliente('25000.00');
        // Autorizada pero nunca aplicada al cliente: no hay concesión que revertir.
        $solicitud = $this->cotizacionSinLista($cliente, '100.00', $this->respondida);
        $solicitud->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);

        $this->ajuste()->revertirBeneficios($solicitud->fresh(), $this->vendedor->id);

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_la_carga_normal_marca_doble_conteo_posible_y_no_asciende_con_el_provisional_dudoso(): void
    {
        $oro = $this->oro();
        $op = app(AcumuladoOperativoCliente::class);
        $cliente = $this->cliente('5000.00');
        $a = $this->cotizacionSinLista($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($a);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertFalse($op->dobleConteoPosible($cliente->fresh()));

        // La carga normal ya incluye la venta de A (35,000) y conserva su provisional vigente.
        $vista = $this->revisar($this->plantilla($cliente, '35000'), ImportacionCliente::MODALIDAD_NORMAL);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_NORMAL, null);
        $cliente->refresh();
        $this->assertSame('35000.00', $cliente->monto_venta_actual);
        $this->assertSame('65000.00', $op->operativo($cliente));
        $this->assertTrue($op->dobleConteoPosible($cliente));
        $this->assertSame('35000.00', $op->paraLista($cliente));
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
        $this->assertTrue($op->presentar($cliente)['doble_conteo_posible']);

        // Con el provisional dudoso sumado serían 105,000 (Oro); sin él, 75,000: no asciende.
        $b = $this->cotizacionSinLista($cliente, '40000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($b);

        $this->assertNull($b->fresh()->catalogo_lista_descuento_id);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertNotSame($oro->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_el_corte_aclara_el_doble_conteo_cerrando_el_provisional_con_evidencia(): void
    {
        $cliente = $this->cliente('5000.00');
        $a = $this->cotizacionSinLista($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($a);
        $this->retroceder($a, 2);
        $vista = $this->revisar($this->plantilla($cliente, '35000'), ImportacionCliente::MODALIDAD_CORTE, now()->subDay()->format('Y-m-d H:i:s'));
        $fecha = ImportacionCliente::find($vista['importacion_id'])->resumen['revision']['fecha_corte'];
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_CORTE, $fecha);

        $op = app(AcumuladoOperativoCliente::class);
        $this->assertFalse($op->dobleConteoPosible($cliente->fresh()));
        $this->assertSame('35000.00', $op->operativo($cliente->fresh()));
    }

    // ---- Rollback: sin ascensos automáticos; solo una autoridad independiente sostiene la lista ----

    public function test_cancelar_la_unica_autorizacion_regresa_a_bronce_aunque_el_oficial_alcance_plata(): void
    {
        $cliente = $this->cliente('25000.00');
        $solicitud = $this->solicitud($cliente, '3000.00', $this->respondida, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);

        $solicitud->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);
        $this->ajuste()->revertirBeneficios($solicitud->fresh(), $this->vendedor->id);

        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame('25000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_una_carga_posterior_con_codigo_explicito_sostiene_la_lista_concedida(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);

        $csv = "numero_cliente,nombre,codigo_lista,monto_venta_actual,vendedor_id\n{$cliente->numero_cliente},Cliente,3,5000,\n";
        $vista = $this->revisar($csv, ImportacionCliente::MODALIDAD_NORMAL);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_NORMAL, null);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);

        $solicitud->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);
        $this->ajuste()->revertirBeneficios($solicitud->fresh(), $this->vendedor->id);

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_otra_autorizacion_vigente_y_un_cambio_posterior_legitimo_sostienen_la_lista(): void
    {
        $oro = $this->oro();
        $cliente = $this->cliente('5000.00');
        $a = $this->solicitud($cliente, '30000.00', $this->respondida, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($a);
        $b = $this->solicitud($cliente, '30000.00', $this->respondida, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($b);

        $a->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);
        $this->ajuste()->revertirBeneficios($a->fresh(), $this->vendedor->id);
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);

        // Un cambio administrativo posterior (Oro) no se deshace al cancelar la solicitud que concedió Plata.
        $cliente->update(['lista_actual_id' => $oro->id]);
        $b->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);
        $this->ajuste()->revertirBeneficios($b->fresh(), $this->vendedor->id);
        $this->assertSame($oro->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_una_lista_protegida_no_se_reescribe_en_el_rollback(): void
    {
        $cliente = $this->cliente('5000.00');
        $solicitud = $this->solicitud($cliente, '30000.00', $this->respondida, $this->plata->id);
        $this->ajuste()->aplicarBeneficios($solicitud);
        $cliente->update(['lista_bloqueada' => true]);

        $solicitud->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Cancelada')]);
        $this->ajuste()->revertirBeneficios($solicitud->fresh(), $this->vendedor->id);

        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
    }

    // ---- Ejercicio de escalonamiento: mismo acumulado operativo y lista que usa Ventas --------------

    public function test_el_modulo_de_escalonamiento_muestra_el_operativo_y_la_lista_proyectada_sin_mezclar_su_acumulado(): void
    {
        $cliente = $this->cliente('5000.00');
        $a = $this->cotizacionSinLista($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($a);
        $pendiente = $this->solicitud($cliente, '1000.00', $this->pendiente, $this->plata->id);

        $contexto = app(\App\Services\Escalonamiento\ContextoVentasEscalonamiento::class)
            ->paraClientes(collect([$cliente->fresh()]))[$cliente->id];
        $this->assertSame('5000.00', $contexto['acumulado_oficial']);
        $this->assertSame('30000.00', $contexto['acumulado_provisional']);
        $this->assertSame('35000.00', $contexto['acumulado_operativo']);
        $this->assertFalse($contexto['doble_conteo_posible']);
        $this->assertSame('MAYOREO PLATA', $contexto['lista_proyectada']);

        $ficha = app(\App\Services\Escalonamiento\ArmarFichaClienteEscalonamiento::class)->armar($cliente->fresh());
        $this->assertSame('35000.00', $ficha['cabecera']['acumulado_operativo']);
        $this->assertSame('MAYOREO PLATA', $ficha['cabecera']['lista_operativa']);
        // El acumulado del módulo sale de documentos del período: las cotizaciones provisionales no lo alteran.
        $this->assertSame('0.00', $ficha['cabecera']['acumulado']);
        $this->assertNotNull($pendiente->id);
    }

    public function test_el_modulo_advierte_doble_conteo_sin_cerrar_ni_conciliar(): void
    {
        $cliente = $this->cliente('5000.00');
        $a = $this->cotizacionSinLista($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($a);
        $vista = $this->revisar($this->plantilla($cliente, '35000'), ImportacionCliente::MODALIDAD_NORMAL);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_NORMAL, null);

        $contexto = app(\App\Services\Escalonamiento\ContextoVentasEscalonamiento::class)
            ->paraClientes(collect([$cliente->fresh()]))[$cliente->id];
        $this->assertTrue($contexto['doble_conteo_posible']);
        $this->assertSame('65000.00', $contexto['acumulado_operativo']);
        $this->assertSame('35000.00', $contexto['acumulado_para_lista']);
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
        $this->assertSame(0, \App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud::query()->count());
    }

    // ---- Menudeo ⇄ Mayoreo: importación PG+0, acción explícita en solicitudes y corrección administrativa ----

    public function test_pg_con_monto_cero_deja_solo_inactivo_y_corrige_un_menudeo_previo(): void
    {
        $cliente = $this->cliente('0.00', '9416', true);
        $cliente->update(['es_inactivo' => true, 'lista_actual_id' => CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->value('id')]);

        $csv = "numero_cliente,nombre,codigo_lista,monto_venta_actual,vendedor_id\n9416,Cliente,pg,0,\n";
        $vista = $this->revisar($csv, ImportacionCliente::MODALIDAD_NORMAL);
        $this->confirmar($vista, ImportacionCliente::MODALIDAD_NORMAL, null);

        $cliente->refresh();
        $this->assertTrue($cliente->es_inactivo);
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame('0.00', $cliente->monto_venta_actual);
        $this->assertSame(1, \App\Models\ClienteModalidadEvento::query()->where('cliente_id', $cliente->id)->where('es_menudeo', false)->count());
    }

    public function test_menudeo_que_pide_conversion_muestra_la_lista_calificada_y_la_aplica_solo_al_autorizar(): void
    {
        $cliente = $this->cliente('5000.00', '8820', true);
        $solicitud = app(\App\Services\Solicitudes\CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 30000,
            'convertir_menudeo_mayoreo' => true,
        ], $this->vendedor->id);

        $this->assertTrue((bool) $solicitud->convertir_menudeo_mayoreo);
        $this->assertSame($this->plata->id, $solicitud->catalogo_lista_descuento_id);
        $this->assertTrue($cliente->fresh()->es_menudeo);

        $this->ajuste()->aplicarBeneficios($solicitud);
        $this->assertTrue($cliente->fresh()->es_menudeo, 'Pendiente no convierte.');

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $this->ajuste()->aplicarBeneficios($solicitud->fresh());

        $cliente->refresh();
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame('5000.00', $cliente->monto_venta_actual);
    }

    public function test_aprobar_un_cambio_de_lista_de_menudeo_sin_conversion_pide_la_decision(): void
    {
        $cliente = $this->cliente('5000.00', '8821', true);
        $solicitud = $this->cotizacionSinLista($cliente, '30000.00', $this->pendiente);

        $this->actingAs($this->vendedor)->put(route('solicitudes.actualizar_estado', $solicitud), [
            'catalogo_estado_solicitud_id' => $this->respondida,
        ])->assertSessionHasErrors('decision_modalidad');
        $this->assertSame($this->pendiente, $solicitud->fresh()->catalogo_estado_solicitud_id);
        $this->assertTrue($cliente->fresh()->es_menudeo);

        $this->actingAs($this->vendedor)->put(route('solicitudes.actualizar_estado', $solicitud), [
            'catalogo_estado_solicitud_id' => $this->respondida,
            'decision_modalidad' => 'convertir',
        ])->assertSessionHasNoErrors();

        $cliente->refresh();
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame($this->plata->id, $solicitud->fresh()->catalogo_lista_descuento_id);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame('5000.00', $cliente->monto_venta_actual);
    }

    public function test_aprobar_manteniendo_menudeo_no_cambia_la_modalidad_ni_la_lista(): void
    {
        $cliente = $this->cliente('5000.00', '8822', true);
        $solicitud = $this->cotizacionSinLista($cliente, '30000.00', $this->pendiente);

        $this->actingAs($this->vendedor)->put(route('solicitudes.actualizar_estado', $solicitud), [
            'catalogo_estado_solicitud_id' => $this->respondida,
            'decision_modalidad' => 'mantener',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($cliente->fresh()->es_menudeo);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_la_correccion_administrativa_de_modalidad_exige_permiso_y_motivo_y_deja_evento(): void
    {
        $cliente = $this->cliente('0.00', '8823', true);
        $cliente->update(['es_inactivo' => true]);
        $url = route('admin.clientes.modalidad', $cliente);

        $this->actingAs($this->vendedor)->post($url, ['es_menudeo' => false, 'motivo' => 'Corrección por error de carga'])->assertForbidden();

        Permission::findOrCreate('clientes.corregir_modalidad', 'web');
        $this->vendedor->givePermissionTo('clientes.corregir_modalidad');
        $this->actingAs($this->vendedor)->post($url, ['es_menudeo' => false, 'motivo' => ''])->assertSessionHasErrors('motivo');
        $this->assertTrue($cliente->fresh()->es_menudeo);

        $this->actingAs($this->vendedor)->post($url, ['es_menudeo' => false, 'motivo' => 'Corrección por error de carga'])->assertSessionHasNoErrors();

        $cliente->refresh();
        $this->assertFalse($cliente->es_menudeo);
        $this->assertTrue($cliente->es_inactivo, 'La corrección no cambia el estado comercial.');
        $this->assertSame('0.00', $cliente->monto_venta_actual);
        $evento = \App\Models\ClienteModalidadEvento::query()->where('cliente_id', $cliente->id)->latest('id')->first();
        $this->assertSame('administracion', $evento->origen);
        $this->assertSame($this->vendedor->id, (int) $evento->usuario_id);
        $this->assertSame('Corrección por error de carga', $evento->motivo);
    }

    // ---- Ejercicio de escalonamiento (herramienta): operación temporal sin esperar la carga masiva ----

    public function test_la_api_del_ejercicio_entrega_acumulado_temporal_lista_proyectada_y_operaciones(): void
    {
        $cliente = $this->cliente('5000.00', '8830');
        $autorizada = $this->cotizacionSinLista($cliente, '30000.00', $this->respondida);
        $this->ajuste()->aplicarBeneficios($autorizada);
        $pendiente = $this->solicitud($cliente, '1000.00', $this->pendiente, $this->plata->id);

        $show = $this->actingAs($this->vendedor)->getJson('/api/clientes/8830')->assertOk();
        $show->assertJsonPath('acumulado_oficial', '5000.00')
            ->assertJsonPath('acumulado_provisional', '30000.00')
            ->assertJsonPath('acumulado_operativo', '35000.00')
            ->assertJsonPath('acumulado_para_lista', '35000.00')
            ->assertJsonPath('lista_proyectada', 'MAYOREO PLATA')
            ->assertJsonPath('lista_almacenada_id', $this->plata->id);
        $operaciones = collect($show->json('operaciones'))->keyBy('folio');
        $this->assertSame('30000.00', $operaciones['FOL-'.$autorizada->id]['provisional_vigente']);
        $this->assertNull($operaciones['FOL-'.$pendiente->id]['provisional_vigente']);

        $busqueda = $this->actingAs($this->vendedor)->getJson('/api/clientes?q=8830')->assertOk();
        $busqueda->assertJsonPath('0.lista_proyectada', 'MAYOREO PLATA')->assertJsonPath('0.acumulado_operativo', '35000.00');
    }

    public function test_un_cliente_reactivado_por_una_autorizacion_se_ve_activo_con_su_lista_en_la_api(): void
    {
        $cliente = $this->cliente('0.00', '8831');
        $cliente->update(['es_inactivo' => true, 'lista_actual_id' => CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->value('id')]);
        $solicitud = $this->cotizacionSinLista($cliente, '8699.00', $this->respondida);
        $solicitud->update(['catalogo_lista_descuento_id' => $this->plata->id]);

        $this->ajuste()->aplicarBeneficios($solicitud->fresh());

        $api = $this->actingAs($this->vendedor)->getJson('/api/clientes/8831')->assertOk();
        $api->assertJsonPath('es_inactivo', false)
            ->assertJsonPath('lista_almacenada_id', $this->plata->id)
            ->assertJsonPath('acumulado_operativo', '8699.00')
            ->assertJsonPath('acumulado_oficial', '0.00');
    }

    private function oro(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::create(['nombre' => 'MAYOREO ORO', 'monto_requerido' => 80000, 'activo' => true, 'visible_en_solicitudes' => true]);
    }

    private function cotizacionSinLista(Cliente $cliente, string $monto, int $estado): SolicitudTag
    {
        return SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => $estado,
            'catalogo_lista_descuento_id' => null,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => $monto,
            'pago_confirmado' => false,
        ]);
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
            'catalogo_tipo_cliente_id' => $this->activo->id,
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
        return "numero_cliente,nombre,monto_venta_actual,vendedor_id\n{$cliente->numero_cliente},Cliente,{$monto},\n";
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
