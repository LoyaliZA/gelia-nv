<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;

class ReactivarClienteInactivoService
{
    /**
     * Si el cliente está inactivo y registra monto de venta (> 0), lo reactiva
     * y asigna la lista que corresponda al monto (salvo lista bloqueada).
     */
    public function ejecutar(Cliente $cliente, float $montoNuevo, bool $asignarListaPorMonto = true): bool
    {
        if (!$cliente->es_inactivo || $montoNuevo <= 0.001) {
            return false;
        }

        $cliente->es_inactivo = false;

        if ($asignarListaPorMonto && !$cliente->lista_bloqueada) {
            $cliente->lista_actual_id = $this->resolverListaPorMonto($montoNuevo);
        }

        return true;
    }

    /**
     * Variante para importación masiva antes de persistir (array $updateData).
     *
     * @return array<string, mixed>
     */
    public function cambiosParaImportacion(Cliente $cliente, float $montoNuevo, array $updateData, $listas): array
    {
        $eraInactivo = $cliente->es_inactivo || ($updateData['es_inactivo'] ?? false);

        if (!$eraInactivo || $montoNuevo <= 0.001) {
            return $updateData;
        }

        $updateData['es_inactivo'] = false;

        if (!$cliente->lista_bloqueada && !isset($updateData['lista_actual_id'])) {
            $updateData['lista_actual_id'] = $this->determinarListaPorMonto($montoNuevo, $listas);
        }

        return $updateData;
    }

    private function resolverListaPorMonto(float $monto): int
    {
        $lista = CatalogoListaDescuento::where('activo', true)
            ->where('nombre', 'not like', '%COLABORADOR%')
            ->where('nombre', 'not like', '%PLATAFORMAS%')
            ->where('monto_requerido', '<=', $monto)
            ->orderBy('monto_requerido', 'desc')
            ->first();

        if ($lista) {
            return $lista->id;
        }

        return (int) CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->value('id');
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if (in_array($lista->nombre, ['COLABORADORES', 'PLATAFORMAS'], true)) {
                continue;
            }
            if ($monto >= $lista->monto_requerido) {
                return $lista->id;
            }
        }

        return $listas->firstWhere('nombre', 'PUBLICO GENERAL')->id;
    }
}
