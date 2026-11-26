<?php

namespace Tests\Feature\Solicitudes;

use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use App\Services\Solicitudes\CancelarSolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CancelacionRollbackListaTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $pg;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    private CatalogoProceso $proceso;

    private User $vendedorAnterior;

    private User $vendedor;

    private User $supervision;

    private int $respondida;

    private int $cancelada;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        app(EscalonamientoAutoridadConfig::class)->guardar(false);

        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta', 'Cancelada'] as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $this->cancelada = (int) CatalogoEstadoSolicitud::idDe('Cancelada');

        $this->pg = CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->bronce = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->plata = CatalogoListaDescuento::create(['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5104, 'activo' => true, 'visible_en_solicitudes' => true]);
        $this->proceso = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG Y CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);

        foreach (['solicitudes.solicitar_cancelacion', 'solicitudes.cancelar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->vendedorAnterior = User::factory()->create(['name' => 'Tag previo']);
        $this->vendedor = User::factory()->create(['name' => 'Ana Ventas']);
        $this->vendedor->givePermissionTo('solicitudes.solicitar_cancelacion');
        $this->supervision = User::factory()->create(['name' => 'Auxiliar supervision']);
        $this->supervision->givePermissionTo('solicitudes.cancelar');
    }

    public function test_solicitar_cancelacion_no_exige_lista_inferior(): void
    {
        $solicitud = $this->solicitudAprobada();

        $this->actingAs($this->vendedor)
            ->post(route('solicitudes.solicitar_cancelacion', $solicitud), [
                'motivo_cancelacion' => 'El cliente pospuso la compra.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $solicitud->refresh();
        $this->assertNotNull($solicitud->cancelacion_solicitada_at);
        $this->assertNull($solicitud->catalogo_lista_rebaja_id);
        $this->assertSame($this->plata->id, $solicitud->cliente->fresh()->lista_actual_id);

        $motivo = AuditoriaSolicitud::query()->where('solicitud_id', $solicitud->id)->latest('id')->value('motivo_reporte');
        $this->assertStringContainsString('Lista de regreso: MAYOREO BRONCE', (string) $motivo);
        $this->assertStringContainsString('TAG se retira: Ana Ventas', (string) $motivo);
    }

    public function test_confirmar_cancelacion_regresa_a_la_lista_previa_y_retira_el_tag(): void
    {
        $solicitud = $this->solicitudAprobada();
        $this->actingAs($this->vendedor)->post(route('solicitudes.solicitar_cancelacion', $solicitud), [
            'motivo_cancelacion' => 'El cliente pospuso la compra.',
        ])->assertRedirect();

        $this->actingAs($this->supervision)
            ->put(route('solicitudes.cancelar', $solicitud), [])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cliente = $solicitud->cliente->fresh();
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertSame($this->vendedorAnterior->id, $cliente->vendedor_id);
        $this->assertSame($this->cancelada, (int) $solicitud->fresh()->catalogo_estado_solicitud_id);
        $this->assertSame(0.0, (float) $solicitud->fresh()->monto_aplicado_al_cliente);
    }

    public function test_una_lista_de_rebaja_guardada_no_pisa_el_rollback(): void
    {
        $solicitud = $this->solicitudAprobada();
        $solicitud->update([
            'cancelacion_solicitada_at' => now(),
            'motivo_cancelacion' => 'Cierre de prueba con lista de rebaja legado.',
            'catalogo_lista_rebaja_id' => $this->pg->id,
        ]);

        $this->actingAs($this->supervision);
        app(CancelarSolicitudService::class)->ejecutar($solicitud->fresh(), 'Cierre de prueba con lista de rebaja legado.');

        $this->assertSame($this->bronce->id, $solicitud->cliente->fresh()->lista_actual_id);
        $this->assertNotSame($this->pg->id, $solicitud->cliente->fresh()->lista_actual_id);
    }

    private function solicitudAprobada(): SolicitudTag
    {
        $cliente = Cliente::create([
            'numero_cliente' => '8801',
            'nombre' => 'Cliente de cancelación',
            'lista_actual_id' => $this->bronce->id,
            'monto_venta_actual' => 1000,
            'vendedor_id' => $this->vendedorAnterior->id,
        ]);

        $solicitud = SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => $this->respondida,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'monto_cotizado' => 6000,
            'pago_confirmado' => false,
        ]);

        AuditoriaSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'usuario_id' => $this->vendedor->id,
            'estado_anterior_id' => null,
            'estado_nuevo_id' => CatalogoEstadoSolicitud::idDe('Pendiente'),
            'motivo_reporte' => 'Creación original de la solicitud.',
            'datos_snapshot' => [
                'antes' => [
                    'monto_venta' => 1000,
                    'lista_id' => $this->bronce->id,
                    'lista_nombre' => $this->bronce->nombre,
                    'tag_vendedor_id' => $this->vendedorAnterior->id,
                    'tag_vendedor_nombre' => $this->vendedorAnterior->name,
                ],
            ],
        ]);

        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud->fresh());
        $this->assertSame($this->plata->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame($this->vendedor->id, $cliente->fresh()->vendedor_id);

        return $solicitud->fresh(['cliente', 'proceso', 'listaDescuento', 'vendedor']);
    }
}
