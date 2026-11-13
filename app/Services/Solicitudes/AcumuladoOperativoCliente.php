<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\ImporteProvisionalSolicitud;
use Illuminate\Support\Collection;

/** Oficial de la columna más provisionales vigentes. No consulta el ledger del módulo. */
class AcumuladoOperativoCliente
{
    public function oficial(Cliente $cliente): string
    {
        return bcadd((string) ($cliente->monto_venta_actual ?? '0'), '0', 2);
    }

    public function provisional(int $clienteId): string
    {
        return $this->provisionalDeVarios([$clienteId])[$clienteId] ?? '0.00';
    }

    /** @param  array<int, int>  $clienteIds */
    public function provisionalDeVarios(array $clienteIds): array
    {
        $clienteIds = array_values(array_unique(array_map('intval', $clienteIds)));
        $sumas = array_fill_keys($clienteIds, '0.00');
        if ($clienteIds === []) {
            return [];
        }

        $filas = ImporteProvisionalSolicitud::query()
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', ImporteProvisionalSolicitud::ESTADO_VIGENTE)
            ->get(['cliente_id', 'importe']);

        foreach ($filas as $fila) {
            $id = (int) $fila->cliente_id;
            $sumas[$id] = bcadd($sumas[$id] ?? '0.00', (string) $fila->importe, 2);
        }

        return $sumas;
    }

    public function operativo(Cliente $cliente): string
    {
        return bcadd($this->oficial($cliente), $this->provisional($cliente->id), 2);
    }

    /** @return array{acumulado_oficial: string, acumulado_provisional: string, acumulado_operativo: string, inclusion_indeterminada: bool} */
    public function presentar(Cliente $cliente): array
    {
        return $this->presentarMuchos(new Collection([$cliente]))[$cliente->id];
    }

    /**
     * @param  Collection<int, Cliente>  $clientes
     * @return array<int, array{acumulado_oficial: string, acumulado_provisional: string, acumulado_operativo: string, inclusion_indeterminada: bool}>
     */
    public function presentarMuchos(Collection $clientes): array
    {
        $ids = $clientes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $provisionales = $this->provisionalDeVarios($ids);
        $indeterminados = $this->inclusionesIndeterminadas($ids);
        $salida = [];

        foreach ($clientes as $cliente) {
            $oficial = $this->oficial($cliente);
            $provisional = $provisionales[$cliente->id] ?? '0.00';
            $salida[$cliente->id] = [
                'acumulado_oficial' => $oficial,
                'acumulado_provisional' => $provisional,
                'acumulado_operativo' => bcadd($oficial, $provisional, 2),
                'inclusion_indeterminada' => $indeterminados[$cliente->id] ?? false,
            ];
        }

        return $salida;
    }

    /** @param  array<int, int>  $clienteIds
     *  @return array<int, bool>
     */
    private function inclusionesIndeterminadas(array $clienteIds): array
    {
        if ($clienteIds === []) {
            return [];
        }

        $filas = ImporteProvisionalSolicitud::query()
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', ImporteProvisionalSolicitud::ESTADO_CERRADO)
            ->where('inclusion', ImporteProvisionalSolicitud::INCLUSION_INDETERMINADA)
            ->get(['cliente_id', 'solicitud_tag_id', 'tipo', 'trazabilidad']);
        $pagos = $filas->filter(function (ImporteProvisionalSolicitud $fila) {
            if ($fila->tipo === ImporteProvisionalSolicitud::TIPO_PAGO) {
                return true;
            }

            return collect($fila->trazabilidad)->contains(
                fn ($evento) => ($evento['evento'] ?? '') === 'pago_posterior_sin_reapertura',
            );
        });
        if ($pagos->isEmpty()) {
            return [];
        }

        $incluidas = EscalonamientoConciliacionSolicitud::query()
            ->whereIn('solicitud_tag_id', $pagos->pluck('solicitud_tag_id'))
            ->where('inclusion_acumulado', EscalonamientoConciliacionSolicitud::INCLUSION_INCLUIDA)
            ->pluck('solicitud_tag_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $salida = [];
        foreach ($pagos as $fila) {
            if (! in_array((int) $fila->solicitud_tag_id, $incluidas, true)) {
                $salida[(int) $fila->cliente_id] = true;
            }
        }

        return $salida;
    }
}
