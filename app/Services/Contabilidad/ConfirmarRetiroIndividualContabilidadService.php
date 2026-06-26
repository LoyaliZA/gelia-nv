<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Pedido;

class ConfirmarRetiroIndividualContabilidadService
{
    public function __construct(
        private ConfirmarRetiroLoteContabilidadService $loteService,
    ) {}

    public function ejecutar(Pedido $pedido, float $montoRealBanco, string $fechaDeposito): Pedido
    {
        $this->loteService->ejecutar(
            (int) $pedido->plataforma_pago_id,
            $fechaDeposito,
            [['id' => $pedido->id, 'monto_real' => $montoRealBanco]]
        );

        return $pedido->fresh(['plataformaPago', 'estatusPago', 'tipoTransaccion', 'lineas']);
    }
}
