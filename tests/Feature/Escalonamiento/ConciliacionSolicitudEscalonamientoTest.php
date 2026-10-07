<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento;
use App\Services\Escalonamiento\RegistrarMovimiento;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConciliacionSolicitudEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_asignar_conciliacion_crea_incidencia_si_falta_cobertura(): void
    {
        [$cliente, $periodo, $documento, $solicitud] = $this->escenario();

        $svc = app(ConciliacionSolicitudEscalonamiento::class);
        $svc->asignar($periodo, $solicitud, $documento, '50.00', 'cotejo', null);

        $this->assertTrue(
            EscalonamientoIncidencia::query()
                ->where('codigo', 'cobertura_parcial')
                ->where('solicitud_tag_id', $solicitud->id)
                ->where('estado', 'abierta')
                ->exists()
        );

        $svc->asignar($periodo, $solicitud, $documento, '100.00', 'cobertura completa', null);
        $this->assertFalse(
            EscalonamientoIncidencia::query()
                ->where('codigo', 'cobertura_parcial')
                ->where('estado', 'abierta')
                ->exists()
        );
    }

    public function test_pantalla_conciliacion_requiere_permiso_ver(): void
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('escalonamiento.ver', 'web');
        $user->givePermissionTo('escalonamiento.ver');

        $this->actingAs($user)
            ->get(route('escalonamiento.conciliacion'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Escalonamiento/Conciliacion', false));
    }

    public function test_operador_puede_asignar_por_http(): void
    {
        [$cliente, $periodo, $documento, $solicitud] = $this->escenario();
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['escalonamiento.ver', 'escalonamiento.operar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($user)
            ->post(route('escalonamiento.conciliacion.asignar'), [
                'solicitud_tag_id' => $solicitud->id,
                'documento_venta_id' => $documento->id,
                'importe_asignado' => '100.00',
            ])
            ->assertRedirect(route('escalonamiento.conciliacion'));

        $cliente->refresh();
        $this->assertEquals(0.0, (float) $cliente->monto_venta_actual);
    }

    /**
     * @return array{0: Cliente, 1: EscalonamientoPeriodo, 2: DocumentoVenta, 3: SolicitudTag}
     */
    private function escenario(): array
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '9300',
            'nombre' => 'Conciliación',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'C-100',
            'total' => '100.00',
            'efecto' => '100.00',
            'fecha_emision' => '2026-10-03',
        ]);
        $documento = DocumentoVenta::query()->where('folio', 'C-100')->firstOrFail();

        $vendedor = User::factory()->create();
        $estado = CatalogoEstadoSolicitud::create(['nombre' => 'Pagada', 'activo' => true]);
        $proceso = CatalogoProceso::create(['nombre' => 'Tag', 'activo' => true]);
        $solicitud = SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $estado->id,
            'monto_cotizado' => 100,
            'monto_aplicado_al_cliente' => 100,
            'pago_confirmado' => true,
            'fecha_operacion' => '2026-10-03',
            'numero_remision' => 'C-100',
        ]);

        return [$cliente, $periodo, $documento, $solicitud];
    }
}
