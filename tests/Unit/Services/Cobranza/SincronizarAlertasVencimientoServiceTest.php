<?php

namespace Tests\Unit\Services\Cobranza;

use App\Models\Cliente;
use App\Models\CobranzaAlerta;
use App\Models\CobranzaFactura;
use App\Services\Cobranza\SincronizarAlertasVencimientoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizarAlertasVencimientoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resuelve_alertas_obsoletas_y_crea_alertas_para_folios_vencidos_activos(): void
    {
        $cliente = Cliente::factory()->create();

        $facturaPagada = CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => 'COB-LEGACY-1',
            'monto' => 0,
            'fecha_vencimiento' => Carbon::now()->subDays(10)->toDateString(),
            'pagada' => true,
        ]);

        $alertaObsoleta = CobranzaAlerta::create([
            'cliente_id' => $cliente->id,
            'factura_id' => $facturaPagada->id,
            'tipo' => 'vencimiento',
            'dias_atraso' => 10,
            'fecha_alerta' => now()->toDateString(),
            'estado' => 'pendiente',
        ]);

        $facturaVencida = CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => '4301',
            'monto' => 4452.19,
            'fecha_vencimiento' => Carbon::now()->subDays(5)->toDateString(),
            'pagada' => false,
        ]);

        $resultado = app(SincronizarAlertasVencimientoService::class)->ejecutar();

        $this->assertSame(1, $resultado['resueltas']);
        $this->assertSame(1, $resultado['creadas']);

        $alertaObsoleta->refresh();
        $this->assertSame('resuelta', $alertaObsoleta->estado);

        $this->assertTrue(
            CobranzaAlerta::query()
                ->where('factura_id', $facturaVencida->id)
                ->where('tipo', 'vencimiento')
                ->where('estado', 'pendiente')
                ->exists()
        );
    }

    public function test_recrea_alerta_cuando_solo_existe_resuelta_con_mismo_dias_atraso(): void
    {
        $cliente = Cliente::factory()->create();

        $factura = CobranzaFactura::create([
            'cliente_id' => $cliente->id,
            'folio' => '4301',
            'monto' => 4452.19,
            'fecha_vencimiento' => Carbon::now()->subDays(5)->toDateString(),
            'pagada' => false,
        ]);

        CobranzaAlerta::create([
            'cliente_id' => $cliente->id,
            'factura_id' => $factura->id,
            'tipo' => 'vencimiento',
            'dias_atraso' => 5,
            'fecha_alerta' => now()->subDays(10)->toDateString(),
            'estado' => 'resuelta',
        ]);

        $resultado = app(SincronizarAlertasVencimientoService::class)->ejecutar();

        $this->assertSame(1, $resultado['creadas']);
        $this->assertTrue(
            CobranzaAlerta::query()
                ->where('factura_id', $factura->id)
                ->where('tipo', 'vencimiento')
                ->where('estado', 'pendiente')
                ->exists()
        );
    }
}
