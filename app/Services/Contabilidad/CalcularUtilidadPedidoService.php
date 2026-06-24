<?php

namespace App\Services\Contabilidad;

class CalcularUtilidadPedidoService
{
    public function ejecutar(
        float $ventaTotal,
        float $costoProductos,
        float $costoEnvio,
        bool $envioPagadoCliente,
        float $comisionPlataforma,
        string $codigoTipoTransaccion,
        string $codigoEstatusPago = 'pendiente',
        float $comisionTransferencia = 0.0
    ): float {
        $gastoEnvioEmpresa = $envioPagadoCliente ? 0.0 : $costoEnvio;

        if (str_contains(strtolower($codigoTipoTransaccion), 'venta')) {
            $utilidadBase = $ventaTotal - $costoProductos - $gastoEnvioEmpresa - $comisionPlataforma;
        } else {
            $utilidadBase = -($costoProductos + $gastoEnvioEmpresa + $comisionPlataforma);
        }

        if ($codigoEstatusPago === 'transferido') {
            $utilidadBase -= $comisionTransferencia;
        }

        return round($utilidadBase, 2);
    }
}
