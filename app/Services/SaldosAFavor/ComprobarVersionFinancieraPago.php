<?php

namespace App\Services\SaldosAFavor;

use App\Exceptions\ControlPedidos\ConflictoCapturaPedido;
use App\Models\ControlPedidos\PedidoBma;

/**
 * La revisión comercial y los importes confirmados. La huella de toda la fila
 * cambia cuando el corte escribe la solicitud, así que no sirve como versión financiera.
 */
final class ComprobarVersionFinancieraPago
{
    public function comprobar(PedidoBma $pedido, array $datos): void
    {
        if ($this->presente($datos, 'revision_esperada')
            && (int) $datos['revision_esperada'] !== (int) $pedido->revision_registro) {
            throw new ConflictoCapturaPedido('Los importes cambiaron. Actualice el pedido antes de registrar el pago.');
        }
        if ($this->presente($datos, 'total_mercancia_confirmado')
            && ! $this->mismo($datos['total_mercancia_confirmado'], $pedido->total_mercancia)) {
            throw new ConflictoCapturaPedido('Los importes cambiaron. Actualice el pedido antes de registrar el pago.');
        }
        if ($this->presente($datos, 'saldo_a_favor_confirmado')
            && ! $this->mismo($datos['saldo_a_favor_confirmado'], $pedido->saldo_a_favor)) {
            throw new ConflictoCapturaPedido('Los importes cambiaron. Actualice el pedido antes de registrar el pago.');
        }
    }

    /** @param  array<string, mixed>  $datos */
    private function presente(array $datos, string $clave): bool
    {
        return array_key_exists($clave, $datos) && $datos[$clave] !== null && $datos[$clave] !== '';
    }

    private function mismo(mixed $esperado, mixed $actual): bool
    {
        return $this->canon($esperado) === $this->canon($actual);
    }

    private function canon(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return number_format(round((float) $valor, 2), 2, '.', '');
    }
}
