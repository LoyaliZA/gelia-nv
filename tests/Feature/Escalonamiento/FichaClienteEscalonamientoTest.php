<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\RegistrarMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FichaClienteEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ficha_mensual_muestra_listas_y_movimientos(): void
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '9200',
            'nombre' => 'Ficha Test',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 100,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'F-1',
            'total' => '1200.00',
            'efecto' => '1200.00',
            'fecha_emision' => '2026-10-02',
        ]);

        $user = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('escalonamiento.ver', 'web');
        $user->givePermissionTo('escalonamiento.ver');

        $this->actingAs($user)
            ->get(route('escalonamiento.clientes.ficha', $cliente))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Escalonamiento/FichaCliente', false)
                ->where('cliente.id', $cliente->id)
                ->has('ficha.movimientos', 1)
                ->where('ficha.cabecera.lista_base', 'MAYOREO PLATA')
                ->where('ficha.cabecera.acumulado', '1200.00'));
    }
}
