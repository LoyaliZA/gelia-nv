<?php

namespace Tests\Unit\Reportes;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Support\Reportes\DetectarPosiblesDuplicadosVouchersService;
use Tests\TestCase;

class DetectarPosiblesDuplicadosVouchersTest extends TestCase
{
    public function test_dos_items_iguales_ambos_flagged(): void
    {
        $fecha = now()->startOfDay();
        $bancoId = 42;

        $item1 = PedidoBmaCierrePagoItem::query()->make([
            'numero_exhibicion' => 1,
            'monto_snapshot' => 1500.00,
            'forma_pago_snapshot' => 'transferencia',
            'catalogo_banco_id' => $bancoId,
            'banco_snapshot' => 'Banco Test',
            'referencia_snapshot' => 'REF-001',
            'fecha_pago_snapshot' => $fecha,
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
        ]);
        $item1->id = 1;

        $item2 = PedidoBmaCierrePagoItem::query()->make([
            'numero_exhibicion' => 2,
            'monto_snapshot' => 1500.00,
            'forma_pago_snapshot' => 'transferencia',
            'catalogo_banco_id' => $bancoId,
            'banco_snapshot' => 'Banco Test',
            'referencia_snapshot' => 'REF-001',
            'fecha_pago_snapshot' => $fecha,
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
        ]);
        $item2->id = 2;

        $marcados = app(DetectarPosiblesDuplicadosVouchersService::class)->marcar([$item1, $item2]);

        $this->assertTrue(isset($marcados[1]));
        $this->assertTrue(isset($marcados[2]));
        $this->assertCount(2, $marcados);
    }

    public function test_item_unico_no_flagged(): void
    {
        $item = PedidoBmaCierrePagoItem::query()->make([
            'numero_exhibicion' => 1,
            'monto_snapshot' => 500.00,
            'forma_pago_snapshot' => 'transferencia',
            'catalogo_banco_id' => 1,
            'banco_snapshot' => 'Banco',
            'referencia_snapshot' => 'UNICA',
            'fecha_pago_snapshot' => now(),
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
        ]);
        $item->id = 99;

        $marcados = app(DetectarPosiblesDuplicadosVouchersService::class)->marcar([$item]);

        $this->assertEmpty($marcados);
    }
}
