<?php

namespace App\Services\SaldosAFavor;

use App\Models\Cliente;
use InvalidArgumentException;

class ValidarClienteSafService
{
    public function assertTransferible(Cliente|int $cliente): Cliente
    {
        $modelo = $cliente instanceof Cliente
            ? $cliente
            : Cliente::query()->find($cliente);

        if (! $modelo) {
            throw new InvalidArgumentException('El saldo a favor requiere un cliente identificado.');
        }

        $numero = trim((string) $modelo->numero_cliente);
        if ($numero === '') {
            throw new InvalidArgumentException('El cliente no tiene número de cliente válido.');
        }

        $nombre = mb_strtolower(trim((string) $modelo->nombre));
        $nombre = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü'], ['a', 'e', 'i', 'o', 'u', 'u'], $nombre);
        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? $nombre;

        if ($nombre === 'publico general' || str_contains($nombre, 'publico general')) {
            throw new InvalidArgumentException('No se permite saldo transferible para público general.');
        }

        return $modelo;
    }
}
