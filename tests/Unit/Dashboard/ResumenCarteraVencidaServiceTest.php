<?php

namespace Tests\Unit\Dashboard;

use App\Models\Cliente;
use App\Models\CobranzaFactura;
use App\Services\Cobranza\ResumenCarteraVencidaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumenCarteraVencidaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cartera_vacia_sin_facturas(): void
    {
        $resumen = app(ResumenCarteraVencidaService::class)->ejecutar(Carbon::parse('2026-11-26'));

        $this->assertSame(0.0, $resumen['saldo_vencido']);
        $this->assertSame(ResumenCarteraVencidaService::carteraVacia(), $resumen['cartera']);
        $this->assertSame([], $resumen['top_vencidos']);
    }

    public function test_agrupa_antiguedad_y_ordena_top_por_saldo(): void
    {
        $hoy = Carbon::parse('2026-11-26')->startOfDay();
        $mayor = Cliente::create(['numero_cliente' => '100', 'nombre' => 'Cliente mayor']);
        $menor = Cliente::create(['numero_cliente' => '200', 'nombre' => 'Cliente menor']);

        CobranzaFactura::create([
            'cliente_id' => $mayor->id,
            'folio' => 'A-1',
            'monto' => 800,
            'fecha_vencimiento' => $hoy->copy()->subDays(10)->toDateString(),
            'pagada' => false,
        ]);
        CobranzaFactura::create([
            'cliente_id' => $mayor->id,
            'folio' => 'A-2',
            'monto' => 200,
            'fecha_vencimiento' => $hoy->copy()->subDays(40)->toDateString(),
            'pagada' => false,
        ]);
        CobranzaFactura::create([
            'cliente_id' => $menor->id,
            'folio' => 'B-1',
            'monto' => 150,
            'fecha_vencimiento' => $hoy->copy()->subDays(5)->toDateString(),
            'pagada' => false,
        ]);
        CobranzaFactura::create([
            'cliente_id' => $menor->id,
            'folio' => 'B-PAGADA',
            'monto' => 999,
            'fecha_vencimiento' => $hoy->copy()->subDays(3)->toDateString(),
            'pagada' => true,
        ]);

        $resumen = app(ResumenCarteraVencidaService::class)->ejecutar($hoy);

        $this->assertSame(1150.0, $resumen['saldo_vencido']);
        $this->assertSame(2, $resumen['cartera']['rango_1_30']['cantidad']);
        $this->assertSame(950.0, $resumen['cartera']['rango_1_30']['total']);
        $this->assertSame(1, $resumen['cartera']['rango_31_60']['cantidad']);
        $this->assertSame(200.0, $resumen['cartera']['rango_31_60']['total']);
        $this->assertSame($mayor->id, $resumen['top_vencidos'][0]['cliente_id']);
        $this->assertSame(1000.0, $resumen['top_vencidos'][0]['saldo_vencido']);
        $this->assertSame(40, $resumen['top_vencidos'][0]['dias_atraso']);
        $this->assertSame(2, $resumen['top_vencidos'][0]['folios']);
        $this->assertSame($menor->id, $resumen['top_vencidos'][1]['cliente_id']);
    }
}
