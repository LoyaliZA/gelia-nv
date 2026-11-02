<?php

namespace App\Support\ControlPedidos\RegistrarPedidos;

/** El canal de la venta no es la modalidad. Solo se precarga cuando la correspondencia es única. */
final class OrigenCapturaPedido
{
    /**
     * @param  iterable<int, mixed>  $origenes
     */
    public static function idTiendaUnico(iterable $origenes): ?int
    {
        $ids = [];
        foreach ($origenes as $origen) {
            if (is_object($origen) && method_exists($origen, 'only')) {
                $fila = $origen->only(['id', 'requiere_logistica', 'activo']);
            } elseif (is_array($origen)) {
                $fila = $origen;
            } else {
                continue;
            }
            $activo = array_key_exists('activo', $fila) ? (bool) $fila['activo'] : true;
            $logistica = filter_var($fila['requiere_logistica'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($activo && ! $logistica && isset($fila['id'])) {
                $ids[] = (int) $fila['id'];
            }
        }

        $ids = array_values(array_unique($ids));

        return count($ids) === 1 ? $ids[0] : null;
    }
}
