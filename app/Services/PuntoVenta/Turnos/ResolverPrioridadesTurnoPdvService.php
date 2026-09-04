<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\Cliente;
use App\Models\PuntoVenta\TurnoPdv;

final class ResolverPrioridadesTurnoPdvService
{
    /**
     * @return array{
     *     prioridad_adulto_mayor: bool,
     *     prioridad_discapacidad: bool,
     *     prioridad_diamante: bool,
     *     prioridad_vip: bool
     * }
     */
    public function resolver(?Cliente $cliente, bool $adultoMayor, bool $discapacidad): array
    {
        return [
            'prioridad_adulto_mayor' => $adultoMayor,
            'prioridad_discapacidad' => $discapacidad,
            'prioridad_diamante' => $this->esListaDiamante($cliente),
            // ponytail: anclar VIP a campo/catálogo de Cliente cuando cierre decisión 0C §16.2
            'prioridad_vip' => false,
        ];
    }

    private function esListaDiamante(?Cliente $cliente): bool
    {
        if (! $cliente instanceof Cliente) {
            return false;
        }

        $cliente->loadMissing('listaDescuento');
        $nombre = strtoupper(trim((string) ($cliente->listaDescuento?->nombre ?? '')));

        if ($nombre === '') {
            return false;
        }

        return str_contains($nombre, 'DIAMANTE');
    }
}
