<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use Illuminate\Validation\ValidationException;

/** Sucursal PDV que recibe el municipal. Usa los mismos almacenes habilitados que la consulta de Tienda. */
class ResolverSucursalDestinoMunicipal
{
    public function __construct(private DestinoRevisionRegistrarPedidos $destinos) {}

    /** @return list<int> */
    public function habilitadas(): array
    {
        return $this->destinos->almacenesTienda()
            ->pluck('sucursal_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function ejecutar(?int $solicitada): int
    {
        $ids = $this->habilitadas();

        if ($ids === []) {
            throw ValidationException::withMessages(['sucursal_destino_id' => 'No hay una sucursal habilitada para recibir el envío.']);
        }
        if ($solicitada === null) {
            if (count($ids) === 1) {
                return $ids[0];
            }
            throw ValidationException::withMessages(['sucursal_destino_id' => 'Elija la sucursal que recibirá el envío.']);
        }
        if (! in_array($solicitada, $ids, true)) {
            throw ValidationException::withMessages(['sucursal_destino_id' => 'La sucursal indicada no está habilitada para recibir este envío.']);
        }

        return $solicitada;
    }
}
