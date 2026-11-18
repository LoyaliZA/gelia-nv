<?php

namespace App\Support\ControlPedidos\Conversion;

use App\Models\ControlPedidos\PedidoBma;

/**
 * Política independiente por destino. El límite de primer cierre no vive aquí.
 */
interface PoliticaConversionModalidad
{
    /** @return list<string> */
    public function origenes(): array;

    public function destino(): string;

    public function activo(): bool;

    public function evaluar(PedidoBma $pedido): ResultadoPoliticaConversion;
}
