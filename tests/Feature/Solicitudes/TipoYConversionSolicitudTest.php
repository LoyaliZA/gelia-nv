<?php

namespace Tests\Feature\Solicitudes;

use App\Models\Area;
use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\ClienteModalidadEvento;
use App\Models\SolicitudBeneficio;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Clientes\ClasificacionComercialCliente;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\TipoClienteSolicitudResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TipoYConversionSolicitudTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $pg;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    private CatalogoProceso $proceso;

    private CatalogoTipoCliente $activo;

    private CatalogoTipoCliente $nuevo;

    private CatalogoTipoCliente $reactivado;

    private User $vendedor;

    private int $pendiente;

    private int $respondida;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Cancelada'] as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->pendiente = (int) CatalogoEstadoSolicitud::idDe('Pendiente');
        $this->respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $this->pg = CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->bronce = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->plata = CatalogoListaDescuento::create(['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5001, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->activo = CatalogoTipoCliente::create(['nombre' => 'ACTIVO', 'activo' => true]);
        $this->nuevo = CatalogoTipoCliente::create(['nombre' => 'NUEVO', 'activo' => true]);
        $this->reactivado = CatalogoTipoCliente::create(['nombre' => 'REACTIVADO', 'activo' => true]);
        CatalogoTipoCliente::reiniciarCache();
        $this->proceso = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG Y CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        foreach (['solicitudes.crear', 'solicitudes.reportar', 'solicitudes.verificar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Administrador', 'web');
        $departamento = Departamento::create(['nombre' => 'Ventas']);
        $area = Area::create(['departamento_id' => $departamento->id, 'nombre' => 'Mostrador']);
        $this->vendedor = User::factory()->create(['area_id' => $area->id]);
        $this->vendedor->givePermissionTo(['solicitudes.crear', 'solicitudes.reportar']);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }

    public function test_inactivo_fuerza_reactivado_aunque_el_formulario_mande_activo(): void
    {
        $cliente = $this->cliente(['es_inactivo' => true, 'catalogo_tipo_cliente_id' => $this->nuevo->id]);

        $this->actingAs($this->vendedor)->post(route('solicitudes.store'), [
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 100,
            'confirmo_informacion_escalonamiento' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $solicitud = SolicitudTag::query()->where('cliente_id', $cliente->id)->firstOrFail();
        $this->assertSame($this->reactivado->id, $solicitud->catalogo_tipo_cliente_id);
        $this->assertTrue($cliente->fresh()->es_inactivo);
        $snapshot = AuditoriaSolicitud::query()->where('solicitud_id', $solicitud->id)->first()?->datos_snapshot ?? [];
        $this->assertTrue((bool) ($snapshot['antes']['es_inactivo'] ?? false));
    }

    public function test_cliente_activo_no_hereda_reactivado_de_la_ficha(): void
    {
        $cliente = $this->cliente(['es_inactivo' => false, 'catalogo_tipo_cliente_id' => $this->reactivado->id]);

        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'monto_cotizado' => 100,
        ], $this->vendedor->id);

        $this->assertSame($this->activo->id, $solicitud->catalogo_tipo_cliente_id);
        $this->assertSame($this->reactivado->id, $cliente->fresh()->catalogo_tipo_cliente_id);
    }

    public function test_resolver_prefiere_nuevo_explicito_en_cliente_activo(): void
    {
        $cliente = $this->cliente(['es_inactivo' => false, 'catalogo_tipo_cliente_id' => $this->reactivado->id]);

        $this->assertSame(
            $this->nuevo->id,
            TipoClienteSolicitudResolver::resolver($this->nuevo->id, $cliente),
        );
    }

    public function test_solicitud_pendiente_no_reactiva(): void
    {
        $cliente = $this->cliente(['es_inactivo' => true, 'es_menudeo' => true]);
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $this->reactivado->id,
            'monto_cotizado' => 100,
        ], $this->vendedor->id);

        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);

        $this->assertTrue($cliente->fresh()->es_inactivo);
        $this->assertSame($this->pendiente, $solicitud->catalogo_estado_solicitud_id);
    }

    public function test_solicitud_autorizada_reactiva_cuando_corresponde(): void
    {
        $cliente = $this->cliente(['es_inactivo' => true, 'es_menudeo' => true]);
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 100,
        ], $this->vendedor->id);
        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);

        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud->fresh());

        $cliente->refresh();
        $this->assertFalse($cliente->es_inactivo);
        $this->assertTrue($cliente->es_menudeo);
        $this->assertSame($this->reactivado->id, $cliente->catalogo_tipo_cliente_id);
        $this->assertSame($this->reactivado->id, $solicitud->fresh()->catalogo_tipo_cliente_id);
    }

    public function test_menudeo_con_lista_mayorista_sin_casilla_convierte_al_autorizar(): void
    {
        $cliente = $this->cliente(['es_menudeo' => true, 'es_inactivo' => false]);
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 6000,
            'convertir_menudeo_mayoreo' => false,
        ], $this->vendedor->id);

        $this->assertTrue($solicitud->convertir_menudeo_mayoreo);
        $this->assertSame($this->plata->id, $solicitud->catalogo_lista_descuento_id);
        $this->assertTrue($cliente->fresh()->es_menudeo);

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud->fresh());

        $cliente->refresh();
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame(ClasificacionComercialCliente::ORIGEN_CONVERSION, $cliente->modalidad_origen);
        $this->assertSame('100.00', $cliente->monto_venta_actual);
        $this->assertFalse($solicitud->fresh()->pago_confirmado);
    }

    public function test_cotizacion_sin_lista_mayorista_no_convierte(): void
    {
        $cliente = $this->cliente(['es_menudeo' => true]);
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 8000,
            'convertir_menudeo_mayoreo' => false,
        ], $this->vendedor->id);

        $this->assertFalse($solicitud->convertir_menudeo_mayoreo);
        $this->assertNull($solicitud->catalogo_lista_descuento_id);

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud->fresh());

        $cliente->refresh();
        $this->assertTrue($cliente->es_menudeo);
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertSame('100.00', $cliente->monto_venta_actual);
    }

    public function test_inactivo_menudeo_con_lista_mayorista_reactiva_y_convierte(): void
    {
        $cliente = $this->cliente(['es_inactivo' => true, 'es_menudeo' => true]);
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_lista_descuento_id' => $this->bronce->id,
            'monto_cotizado' => 200,
        ], $this->vendedor->id);

        $this->assertSame($this->reactivado->id, $solicitud->catalogo_tipo_cliente_id);
        $this->assertTrue($solicitud->convertir_menudeo_mayoreo);

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud->fresh());

        $cliente->refresh();
        $this->assertFalse($cliente->es_inactivo);
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertSame($this->reactivado->id, $cliente->catalogo_tipo_cliente_id);
    }

    public function test_cancelar_revierte_solo_la_conversion_autorizada(): void
    {
        $cliente = $this->cliente(['es_menudeo' => true]);
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 6000,
        ], $this->vendedor->id);
        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $ajuste = app(AjustarMontoPorSolicitudService::class);
        $ajuste->aplicarBeneficios($solicitud->fresh());
        $this->assertFalse($cliente->fresh()->es_menudeo);

        $ajuste->revertirBeneficios($solicitud->fresh());

        $cliente->refresh();
        $this->assertTrue($cliente->es_menudeo);
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertSame('100.00', $cliente->monto_venta_actual);
        $this->assertFalse($solicitud->fresh()->pago_confirmado);
    }

    public function test_reintentar_aplicar_beneficios_no_duplica_modalidad(): void
    {
        $cliente = $this->cliente(['es_menudeo' => true]);
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'monto_cotizado' => 6000,
        ], $this->vendedor->id);
        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        $ajuste = app(AjustarMontoPorSolicitudService::class);
        $ajuste->aplicarBeneficios($solicitud->fresh());
        $ajuste->aplicarBeneficios($solicitud->fresh());

        $this->assertSame(
            1,
            SolicitudBeneficio::query()->where('solicitud_tag_id', $solicitud->id)->where('campo', 'es_menudeo')->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)->count()
        );
        $this->assertSame(1, ClienteModalidadEvento::query()->where('cliente_id', $cliente->id)->count());
        $this->assertFalse($cliente->fresh()->es_menudeo);
        $this->assertSame('100.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_reporte_de_conversiones_omitidas_es_solo_lectura(): void
    {
        $cliente = $this->cliente(['es_menudeo' => true, 'numero_cliente' => '9200']);
        SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => $this->respondida,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'catalogo_tipo_cliente_id' => $this->activo->id,
            'convertir_menudeo_mayoreo' => false,
            'monto_cotizado' => 8000,
            'aplicacion_cliente' => 'aplicada',
        ]);

        Artisan::call('solicitudes:reportar-conversiones-omitidas');

        $this->assertStringContainsString('9200', Artisan::output());
        $this->assertTrue($cliente->fresh()->es_menudeo);
        $this->assertSame($this->pg->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_api_clientes_expone_tipo_e_inactivo(): void
    {
        $cliente = $this->cliente([
            'es_inactivo' => true,
            'catalogo_tipo_cliente_id' => $this->nuevo->id,
            'numero_cliente' => '9100',
        ]);

        $this->actingAs($this->vendedor)->getJson('/api/clientes?q=9100')
            ->assertOk()
            ->assertJsonPath('0.es_inactivo', true)
            ->assertJsonPath('0.catalogo_tipo_cliente_id', $this->nuevo->id)
            ->assertJsonPath('0.tipo_cliente_nombre', 'NUEVO');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function cliente(array $datos = []): Cliente
    {
        return Cliente::create(array_merge([
            'numero_cliente' => (string) random_int(8000, 8999),
            'nombre' => 'Cliente prueba',
            'lista_actual_id' => $this->pg->id,
            'monto_venta_actual' => '100.00',
            'es_inactivo' => false,
            'es_menudeo' => true,
            'catalogo_tipo_cliente_id' => $this->activo->id,
        ], $datos));
    }
}
