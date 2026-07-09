<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

class GenerarFolioPedidoBmaService
{
    public function ejecutar(): string
    {
        $anio = now()->format('Y');
        $prefijo = "PBMA-{$anio}-";

        $ultimo = PedidoBma::withTrashed()
            ->where('folio', 'like', "{$prefijo}%")
            ->orderByDesc('id')
            ->value('folio');

        $secuencia = 1;
        if ($ultimo && preg_match('/-(\d+)$/', $ultimo, $matches)) {
            $secuencia = (int) $matches[1] + 1;
        }

        return $prefijo . str_pad((string) $secuencia, 5, '0', STR_PAD_LEFT);
    }
}
