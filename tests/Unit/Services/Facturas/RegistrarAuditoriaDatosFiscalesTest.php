<?php

namespace Tests\Unit\Services\Facturas;

use App\Models\AuditoriaConfiguracion;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Facturas\GestionarDatosFiscalesClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrarAuditoriaDatosFiscalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualizar_cliente_registra_auditoria_sin_pii(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $lista = CatalogoListaDescuento::create([
            'nombre' => 'Lista',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'numero_cliente' => 'A-1',
            'nombre' => 'Cliente',
            'lista_actual_id' => $lista->id,
        ]);

        app(GestionarDatosFiscalesClienteService::class)->actualizar($cliente, [
            'rfc' => 'XAXX010101000',
            'nombre_razon_social' => 'EMPRESA SA',
        ]);

        $row = AuditoriaConfiguracion::query()->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('Datos fiscales', $row->modulo);
        $this->assertSame($cliente->id, $row->detalles['cliente_id']);
        $this->assertContains('rfc', $row->detalles['campos']);
        $this->assertArrayNotHasKey('rfc', $row->detalles);
        $this->assertStringNotContainsString('XAXX010101000', json_encode($row->detalles));
    }
}
