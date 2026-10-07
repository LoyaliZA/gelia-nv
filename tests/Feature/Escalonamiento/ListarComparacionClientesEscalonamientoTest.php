<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\User;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\ListarComparacionClientesEscalonamiento;
use App\Services\Escalonamiento\RegistrarMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ListarComparacionClientesEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    private function permisos(User $user): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('escalonamiento.ver', 'web');
        $user->givePermissionTo('escalonamiento.ver');
    }

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
        $oro = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO ORO',
            'monto_requerido' => 5000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);

        return compact('publico', 'plata', 'oro');
    }

    public function test_ordena_numero_cliente_de_forma_numerica(): void
    {
        $listas = $this->listasBase();
        $listaId = $listas['plata']->id;

        foreach (['10', '2', '100'] as $numero) {
            Cliente::create([
                'numero_cliente' => $numero,
                'nombre' => "Cliente {$numero}",
                'lista_actual_id' => $listaId,
            ]);
        }

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $request = Request::create('/', 'GET', ['per_page' => 50, 'orden' => 'numero', 'direccion' => 'asc']);
        $resultado = app(ListarComparacionClientesEscalonamiento::class)->listar($periodo, $request);

        $numeros = array_column($resultado['data'], 'numero_cliente');
        $this->assertSame(['2', '10', '100'], $numeros);
    }

    public function test_incluye_cliente_sin_resumen_en_el_indice(): void
    {
        $user = User::factory()->create();
        $this->permisos($user);
        $listas = $this->listasBase();

        Cliente::create([
            'numero_cliente' => '100',
            'nombre' => 'Sin movimiento',
            'lista_actual_id' => $listas['plata']->id,
            'monto_venta_actual' => 0,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        $this->actingAs($user)
            ->get(route('escalonamiento.index', ['periodo_id' => $periodo->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('clientes.data', 1)
                ->where('clientes.data.0.numero_cliente', '100'));

        $this->assertSame(0, EscalonamientoResumenCliente::count());
    }

    public function test_ocultar_inactivos_excluye_clientes_inactivos(): void
    {
        $user = User::factory()->create();
        $this->permisos($user);
        $listas = $this->listasBase();

        Cliente::create([
            'numero_cliente' => '200',
            'nombre' => 'Activo',
            'lista_actual_id' => $listas['plata']->id,
            'es_inactivo' => false,
        ]);
        Cliente::create([
            'numero_cliente' => '201',
            'nombre' => 'Inactivo',
            'lista_actual_id' => $listas['plata']->id,
            'es_inactivo' => true,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        $this->actingAs($user)
            ->get(route('escalonamiento.index', [
                'periodo_id' => $periodo->id,
                'ocultar_inactivos' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('clientes.data', 1)
                ->where('clientes.data.0.numero_cliente', '200'));
    }

    public function test_descenso_oro_a_bronce_no_se_marca_ascenso_por_id_mayor(): void
    {
        $publico = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $oro = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO ORO',
            'monto_requerido' => 5000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $bronce = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO BRONCE',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);

        $this->assertTrue($bronce->id > $oro->id, 'El ID de bronce suele ser mayor que el de oro en catálogo');

        $cliente = Cliente::create([
            'numero_cliente' => '305',
            'nombre' => 'Oro a bronce',
            'lista_actual_id' => $oro->id,
            'monto_venta_actual' => 0,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-OB',
            'total' => '500.00',
            'efecto' => '500.00',
            'fecha_emision' => '2026-10-02',
        ]);

        $request = Request::create('/', 'GET', ['per_page' => 50]);
        $resultado = app(ListarComparacionClientesEscalonamiento::class)->listar($periodo, $request);
        $fila = collect($resultado['data'])->firstWhere('cliente_id', $cliente->id);

        $this->assertNotNull($fila);
        $this->assertSame('descenso', $fila['resultado']);
        $this->assertNotSame('ascenso', $fila['resultado']);
    }

    public function test_resultado_descenso_cuando_no_mantiene_lista_vigente(): void
    {
        $listas = $this->listasBase();
        $cliente = Cliente::create([
            'numero_cliente' => '300',
            'nombre' => 'Descenso',
            'lista_actual_id' => $listas['plata']->id,
            'monto_venta_actual' => 0,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-D',
            'total' => '500.00',
            'efecto' => '500.00',
            'fecha_emision' => '2026-10-02',
        ]);

        $request = Request::create('/', 'GET', ['per_page' => 50]);
        $resultado = app(ListarComparacionClientesEscalonamiento::class)->listar($periodo, $request);
        $fila = collect($resultado['data'])->firstWhere('cliente_id', $cliente->id);

        $this->assertNotNull($fila);
        $this->assertTrue($fila['participa']);
        $this->assertSame('descenso', $fila['resultado']);
    }

    public function test_resultado_descenso_sin_resumen_ni_ventas_en_periodo(): void
    {
        $publico = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
            'participa_escalonamiento' => false,
        ]);
        $plata = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        config(['escalonamiento.lista_publico_general_id' => $publico->id]);

        $cliente = Cliente::create([
            'numero_cliente' => '302',
            'nombre' => 'Sin resumen',
            'lista_actual_id' => $plata->id,
            'monto_venta_actual' => 0,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        $request = Request::create('/', 'GET', ['per_page' => 50, 'q' => '302']);
        $resultado = app(ListarComparacionClientesEscalonamiento::class)->listar($periodo, $request);
        $fila = collect($resultado['data'])->firstWhere('cliente_id', $cliente->id);

        $this->assertNotNull($fila);
        $this->assertSame('descenso', $fila['resultado']);
        $this->assertNotNull($fila['lista_siguiente']);
    }

    public function test_resultado_se_mantiene_con_mantenimiento_cumplido(): void
    {
        $listas = $this->listasBase();
        $cliente = Cliente::create([
            'numero_cliente' => '301',
            'nombre' => 'Mantiene',
            'lista_actual_id' => $listas['plata']->id,
            'monto_venta_actual' => 0,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-M',
            'total' => '1500.00',
            'efecto' => '1500.00',
            'fecha_emision' => '2026-10-03',
        ]);

        $request = Request::create('/', 'GET', ['per_page' => 50]);
        $resultado = app(ListarComparacionClientesEscalonamiento::class)->listar($periodo, $request);
        $fila = collect($resultado['data'])->firstWhere('cliente_id', $cliente->id);

        $this->assertNotNull($fila);
        $this->assertSame('sin_cambio', $fila['resultado']);
    }
}
