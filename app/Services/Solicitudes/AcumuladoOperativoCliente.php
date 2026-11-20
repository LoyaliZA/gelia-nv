<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
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

    /**
     * Base para calcular AUTOMÁTICAMENTE una lista: el operativo sin los provisionales que una
     * carga normal posterior pudo haber incluido ya en el oficial. Sin evidencia de conciliación
     * no se cierran ni se descartan importes: solo se evita que un posible doble conteo ascienda
     * al cliente. La lista que Ventas elige a mano sigue validándose contra el operativo completo.
     */
    public function paraLista(Cliente $cliente, ?int $exceptoSolicitudId = null, ?Collection $vigentes = null): string
    {
        $suma = $this->oficial($cliente);
        foreach ($vigentes ?? $this->provisionalesVigentes($cliente->id) as $fila) {
            if ($exceptoSolicitudId !== null && (int) $fila->solicitud_tag_id === $exceptoSolicitudId) {
                continue;
            }
            if ($fila->doble_conteo_posible) {
                continue;
            }
            $suma = bcadd($suma, (string) $fila->importe, 2);
        }

        return $suma;
    }

    /**
     * Provisionales vigentes del cliente marcados con `doble_conteo_posible` cuando una carga
     * normal que subió el oficial ocurrió después de que se aprobó o pagó la solicitud.
     *
     * @return Collection<int, ImporteProvisionalSolicitud>
     */
    private function provisionalesVigentes(int $clienteId): Collection
    {
        $filas = ImporteProvisionalSolicitud::query()
            ->where('cliente_id', $clienteId)
            ->where('estado', ImporteProvisionalSolicitud::ESTADO_VIGENTE)
            ->get(['id', 'cliente_id', 'solicitud_tag_id', 'importe', 'fecha_operacion', 'fecha_confirmacion']);
        if ($filas->isEmpty()) {
            return $filas;
        }

        $ultimaCarga = HistorialMontoCliente::query()
            ->where('cliente_id', $clienteId)
            ->where('origen', 'carga_masiva')
            ->where('diferencia_aplicada', '>', 0)
            ->whereIn('importacion_cliente_id', ImportacionCliente::query()
                ->where('modalidad_carga', ImportacionCliente::MODALIDAD_NORMAL)
                ->select('id'))
            ->max('created_at');

        foreach ($filas as $fila) {
            $instante = $fila->fecha_confirmacion ?? $fila->fecha_operacion;
            $fila->setAttribute(
                'doble_conteo_posible',
                $ultimaCarga !== null && $instante !== null
                    && \Carbon\Carbon::parse($ultimaCarga)->greaterThanOrEqualTo($instante),
            );
        }

        return $filas;
    }

    public function dobleConteoPosible(Cliente $cliente): bool
    {
        return $this->provisionalesVigentes($cliente->id)->contains(fn ($fila) => $fila->doble_conteo_posible);
    }

    /** @return array{acumulado_oficial: string, acumulado_provisional: string, acumulado_operativo: string, acumulado_para_lista: string, doble_conteo_posible: bool, inclusion_indeterminada: bool} */
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
            $vigentes = $this->provisionalesVigentes($cliente->id);
            $oficial = $this->oficial($cliente);
            $provisional = $provisionales[$cliente->id] ?? '0.00';
            $salida[$cliente->id] = [
                'acumulado_oficial' => $oficial,
                'acumulado_provisional' => $provisional,
                'acumulado_operativo' => bcadd($oficial, $provisional, 2),
                'acumulado_para_lista' => $this->paraLista($cliente, null, $vigentes),
                'doble_conteo_posible' => $vigentes->contains(fn ($fila) => $fila->doble_conteo_posible),
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
