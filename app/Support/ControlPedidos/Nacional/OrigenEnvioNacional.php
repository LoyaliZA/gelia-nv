<?php

namespace App\Support\ControlPedidos\Nacional;

/**
 * El origen del envío nacional se identifica por sus atributos, no por un nombre editable.
 * Solo se asigna cuando existe exactamente un origen activo que requiere logística.
 */
final class OrigenEnvioNacional
{
    /**
     * @param  iterable<int, mixed>  $origenes
     * @return list<int>
     */
    public static function candidatos(iterable $origenes): array
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
            if ($activo && $logistica && isset($fila['id'])) {
                $ids[] = (int) $fila['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  iterable<int, mixed>  $origenes
     */
    public static function idUnico(iterable $origenes): ?int
    {
        $ids = self::candidatos($origenes);

        return count($ids) === 1 ? $ids[0] : null;
    }
}
