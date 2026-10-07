<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use Illuminate\Support\Collection;

class ProyeccionClienteEscalonamiento
{
    public function __construct(
        private EscalonamientoAutoridad $autoridad,
        private ConsultarAcumuladoCliente $consultarAcumulado,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paraCliente(Cliente $cliente, ?EscalonamientoPeriodo $periodo = null): array
    {
        if (! $this->autoridad->estaActiva()) {
            return $this->legacy($cliente);
        }

        $periodo ??= $this->autoridad->periodoOperativo();

        return $this->consultarAcumulado->consultar($cliente, $periodo);
    }

    public function montoAcumulado(Cliente $cliente, ?EscalonamientoPeriodo $periodo = null): float
    {
        if (! $this->autoridad->estaActiva()) {
            return (float) ($cliente->monto_venta_actual ?? 0);
        }

        $datos = $this->paraCliente($cliente, $periodo);

        return (float) ($datos['acumulado'] ?? 0);
    }

    public function listaVigenteId(Cliente $cliente, ?EscalonamientoPeriodo $periodo = null): ?int
    {
        if (! $this->autoridad->estaActiva()) {
            $id = $cliente->lista_actual_id;

            return $id ? (int) $id : null;
        }

        $datos = $this->paraCliente($cliente, $periodo);
        $id = $datos['lista_vigente_id'] ?? $datos['lista_operativa_id'] ?? null;

        return $id ? (int) $id : null;
    }

    /**
     * @param  Collection<int, CatalogoListaDescuento>|array<int, CatalogoListaDescuento>  $catalogoListas
     */
    public function listaActualEnCatalogo(Cliente $cliente, Collection|array $catalogoListas, ?EscalonamientoPeriodo $periodo = null): ?CatalogoListaDescuento
    {
        $listas = $catalogoListas instanceof Collection ? $catalogoListas : collect($catalogoListas);
        $listaId = $this->listaVigenteId($cliente, $periodo);
        if (! $listaId) {
            return null;
        }

        return $listas->firstWhere('id', $listaId);
    }

    public function usaAutoridadModulo(): bool
    {
        return $this->autoridad->estaActiva();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacy(Cliente $cliente): array
    {
        $cliente->loadMissing('listaDescuento');

        return [
            'acumulado' => (string) bcadd((string) ($cliente->monto_venta_actual ?? 0), '0', 2),
            'lista_vigente_id' => $cliente->lista_actual_id,
            'lista_operativa_id' => $cliente->lista_actual_id,
            'lista_base_id' => $cliente->lista_actual_id,
            'clasificacion_mes_id' => null,
            'participa' => (bool) $cliente->listaDescuento?->participa_escalonamiento,
            'bloqueo' => (bool) $cliente->lista_bloqueada,
        ];
    }
}
