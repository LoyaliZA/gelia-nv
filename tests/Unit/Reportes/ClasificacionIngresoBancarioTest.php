<?php

namespace Tests\Unit\Reportes;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\Reportes\PagosPedidos\CalcularIngresoBancarioValidadoService;
use App\Support\Reportes\ClasificacionIngresoBancario;
use Tests\TestCase;

class ClasificacionIngresoBancarioTest extends TestCase
{
    private function item(array $attrs = []): PedidoBmaCierrePagoItem
    {
        return new PedidoBmaCierrePagoItem(array_merge([
            'forma_pago_snapshot' => 'transferencia',
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
            'monto_snapshot' => '100.00',
            'catalogo_banco_id' => 1,
            'banco_snapshot' => 'BBVA',
        ], $attrs));
    }

    public function test_transferencia_verificada_es_ingreso_bancario(): void
    {
        $this->assertSame(
            ClasificacionIngresoBancario::INGRESO_BANCARIO,
            ClasificacionIngresoBancario::clasificarItem($this->item())
        );
        $this->assertTrue(ClasificacionIngresoBancario::cuentaIngresoBancario($this->item()));
    }

    public function test_efectivo_verificado_es_pago_no_bancario(): void
    {
        $item = $this->item(['forma_pago_snapshot' => 'efectivo', 'catalogo_banco_id' => null, 'banco_snapshot' => null]);

        $this->assertSame(ClasificacionIngresoBancario::PAGO_NO_BANCARIO, ClasificacionIngresoBancario::clasificarItem($item));
        $this->assertFalse(ClasificacionIngresoBancario::cuentaIngresoBancario($item));
    }

    public function test_pendiente_no_cuenta_como_validado(): void
    {
        $item = $this->item(['estado_revision_snapshot' => PedidoBmaPago::REVISION_PENDIENTE]);

        $this->assertSame(ClasificacionIngresoBancario::PAGO_PENDIENTE, ClasificacionIngresoBancario::clasificarItem($item));
        $this->assertFalse(ClasificacionIngresoBancario::cuentaIngresoBancario($item));
    }

    public function test_rechazado_no_cuenta(): void
    {
        $item = $this->item([
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_RECHAZADO,
            'activo_para_cobertura_snapshot' => false,
            'motivo_rechazo_snapshot' => 'Comprobante ilegible',
        ]);

        $this->assertSame(ClasificacionIngresoBancario::PAGO_RECHAZADO, ClasificacionIngresoBancario::clasificarItem($item));
    }

    public function test_total_solo_suma_ingreso_bancario_validado(): void
    {
        $svc = new CalcularIngresoBancarioValidadoService();
        $resultado = $svc->desdeItems([
            $this->item(['monto_snapshot' => '500.00']),
            $this->item(['forma_pago_snapshot' => 'efectivo', 'monto_snapshot' => '200.00', 'catalogo_banco_id' => null]),
            $this->item([
                'estado_revision_snapshot' => PedidoBmaPago::REVISION_PENDIENTE,
                'monto_snapshot' => '100.00',
            ]),
            $this->item([
                'estado_revision_snapshot' => PedidoBmaPago::REVISION_RECHAZADO,
                'activo_para_cobertura_snapshot' => false,
                'motivo_rechazo_snapshot' => 'x',
                'monto_snapshot' => '50.00',
            ]),
        ]);

        $this->assertSame('500.00', $resultado['total_ingreso_bancario']);
        $this->assertSame(1, $resultado['vouchers_ingreso_bancario']);
        $this->assertSame('200.00', $resultado['excluidos']['pago_no_bancario']);
        $this->assertSame('100.00', $resultado['excluidos']['pendiente']);
        $this->assertSame('50.00', $resultado['excluidos']['rechazado']);
        $this->assertSame('BBVA', $resultado['por_banco'][0]['banco']);
    }

    public function test_saf_no_es_forma_de_pago_en_catalogo(): void
    {
        $codigos = array_column(ClasificacionIngresoBancario::catalogoFormasPago(), 'codigo');

        $this->assertNotContains('saf', $codigos);
        $this->assertNotContains('saldo_a_favor', $codigos);
    }
}
