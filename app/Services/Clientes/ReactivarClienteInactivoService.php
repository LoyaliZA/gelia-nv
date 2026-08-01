<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Services\Solicitudes\EscalonamientoService;

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
        $listas = CatalogoListaDescuento::where('activo', true)->orderByDesc('monto_requerido')->get();

        return app(EscalonamientoService::class)->resolverListaPorMontoId($monto, $listas);
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        return app(EscalonamientoService::class)->resolverListaPorMontoId($monto, $listas);
    }
}
