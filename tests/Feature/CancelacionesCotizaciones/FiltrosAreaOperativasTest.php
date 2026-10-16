<?php

namespace Tests\Feature\CancelacionesCotizaciones;

use App\Models\Area;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\CancelacionesCotizaciones\ListarSolicitudesOperativasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FiltrosAreaOperativasTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoProceso $proceso;
    private CatalogoListaDescuento $lista;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Administrador', 'web');
        $this->proceso = CatalogoProceso::updateOrCreate(['nombre' => 'CANCELACIÓN DE PEDIDO'], ['categoria_flujo' => CatalogoProceso::CATEGORIA_OPERATIVO]);
        $this->lista = CatalogoListaDescuento::create(['nombre' => 'Plata', 'monto_requerido' => 5000]);
    }

    private function vendedor(Departamento $departamento, Area $area): User
    {
        $usuario = User::factory()->create(['area_id' => $area->id]);
        $usuario->departamentos()->attach($departamento);

        return $usuario;
    }

    private function solicitud(User $vendedor, Departamento $departamento): SolicitudTag
    {
        $cliente = Cliente::create(['numero_cliente' => (string) random_int(10000, 99999), 'nombre' => 'Cliente', 'vendedor_id' => $vendedor->id, 'lista_actual_id' => $this->lista->id]);

        return SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'departamento_id' => $departamento->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => 1, // ponytail: el servicio cuenta "pendientes" por id literal 1, independiente del nombre.
            'catalogo_lista_descuento_id' => $this->lista->id,
            'monto_cotizado' => 0,
        ]);
    }

    public function test_opciones_filtros_y_listado_se_acotan_por_departamento_y_area_principal(): void
    {
        $d1 = Departamento::create(['nombre' => 'Ventas A', 'activo' => true]);
        $d2 = Departamento::create(['nombre' => 'Ventas B', 'activo' => true]);
        $a1 = Area::create(['nombre' => 'Mayoreo A', 'departamento_id' => $d1->id]);
        $a1b = Area::create(['nombre' => 'Menudeo A', 'departamento_id' => $d1->id]);
        $a2 = Area::create(['nombre' => 'Mayoreo B', 'departamento_id' => $d2->id]);
        $sinSolicitudes = Area::create(['nombre' => 'Sin solicitudes', 'departamento_id' => $d1->id]);
        $u1 = $this->vendedor($d1, $a1);
        $u1b = $this->vendedor($d1, $a1b);
        $u2 = $this->vendedor($d2, $a2);
        $this->vendedor($d1, $sinSolicitudes);
        $u1->areas()->attach($a2); // Asignación secundaria: no debe ser opción de área principal.
        $s1 = $this->solicitud($u1, $d1);
        $s1b = $this->solicitud($u1b, $d1);
        $this->solicitud($u2, $d2);

        $service = app(ListarSolicitudesOperativasService::class);
        $opciones = $service->opcionesFiltros($u1);

        $this->assertSame([$d1->id], $opciones['departamentos']->pluck('id')->all());
        $this->assertSame([$a1->id], $opciones['areas']->pluck('id')->all());
        $this->assertSame([$u1->id], $opciones['vendedores']->pluck('id')->all());
        $this->assertEmpty($service->ejecutar($u1, ['departamento_id' => $d2->id], false));
        $this->assertEmpty($service->ejecutar($u1, ['area_id' => $a2->id], false));

        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $this->assertEqualsCanonicalizing([$a1->id, $a1b->id, $a2->id], $service->opcionesFiltros($admin)['areas']->pluck('id')->all());
        $this->assertSame([$s1->id], $service->ejecutar($admin, ['departamento_id' => $d1->id, 'area_id' => $a1->id], false)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$s1->id, $s1b->id], $service->ejecutar($admin, ['departamento_id' => $d1->id], false)->pluck('id')->all());
        $this->assertSame(1, $service->metricas($admin, ['area_id' => $a1->id, 'tab' => 'RESPONDIDAS'])['pendientes']);
        $this->assertSame(2, $service->metricas($admin, ['departamento_id' => $d1->id])['pendientes']);
    }
}
