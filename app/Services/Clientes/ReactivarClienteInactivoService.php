<?php

namespace App\Services\Clientes;

use App\Models\Cliente;

/** @deprecated Solo la sincronización autoritativa de Wizerp cambia el estado comercial. */
class ReactivarClienteInactivoService
{
    public function ejecutar(Cliente $cliente, float $montoNuevo, bool $asignarListaPorMonto = true): bool
    {
        return false;
    }

    public function cambiosParaImportacion(Cliente $cliente, float $montoNuevo, array $updateData, $listas): array
    {
        return $updateData;
    }
}
