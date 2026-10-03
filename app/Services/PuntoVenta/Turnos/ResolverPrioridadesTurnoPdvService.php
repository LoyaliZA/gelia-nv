<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\Cliente;
use App\Support\Catalogos\ClasificacionListaTurno;

final class ResolverPrioridadesTurnoPdvService
{
    /**
     * @return array{
     *     prioridad_adulto_mayor: bool,
     *     prioridad_discapacidad: bool,
     *     prioridad_diamante: bool,
     *     prioridad_vip: bool,
     *     prioridad_cola: int,
     *     lista_tono: ?string
     * }
     */
    public function resolver(?Cliente $cliente, bool $adultoMayor, bool $discapacidad): array
    {
        $clasificacion = $this->clasificacionLista($cliente);

        return [
            'prioridad_adulto_mayor' => $adultoMayor,
            'prioridad_discapacidad' => $discapacidad,
            'prioridad_diamante' => $clasificacion['es_diamante'],
            // ponytail: anclar VIP a campo/catálogo de Cliente cuando cierre decisión 0C §16.2
            'prioridad_vip' => false,
            'prioridad_cola' => $clasificacion['prioridad_cola'],
            'lista_tono' => $clasificacion['tono'],
        ];
    }

    /**
     * @return array{tono: ?string, prioridad_cola: int, es_diamante: bool}
     */
    private function clasificacionLista(?Cliente $cliente): array
    {
        if (! $cliente instanceof Cliente) {
            return ClasificacionListaTurno::resolver(null, null, 0);
        }

        $cliente->loadMissing('listaDescuento');
        $lista = $cliente->listaDescuento;

        return ClasificacionListaTurno::resolver(
            $lista?->nombre,
            $lista?->tono_sala,
            $lista?->prioridad_cola_turnos,
        );
    }
}
