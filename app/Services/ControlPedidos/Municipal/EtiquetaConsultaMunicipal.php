<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\ConsultaMunicipal;

/** Etiqueta de la consulta municipal. No renombra las fases de otros módulos. */
final class EtiquetaConsultaMunicipal
{
    public static function de(PedidoBma $pedido): ?string
    {
        if (! ConsultaMunicipal::es($pedido)) {
            return null;
        }
        $estado = ConsultaMunicipal::estado($pedido);
        $area = (string) (ConsultaMunicipal::seguimiento($pedido)['area'] ?? '');
        if ($estado === ConsultaMunicipal::CERRADA) {
            return 'Consulta cerrada';
        }
        if ($estado === ConsultaMunicipal::RESPONDIDA) {
            return 'Consulta respondida';
        }
        if ($estado === ConsultaMunicipal::EXCEPCIONES) {
            return 'Excepciones pendientes';
        }

        return $area === 'tienda' ? 'Consulta pendiente: Tienda' : 'Consulta pendiente: CEDIS';
    }
}
