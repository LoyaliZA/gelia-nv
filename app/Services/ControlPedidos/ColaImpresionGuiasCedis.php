<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Nacional\GuiasPdfPorCaja;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ColaImpresionGuiasCedis
{
    public function consultar(User $usuario, ?int $documentoId = null, ?array $pedidoIds = null): Collection
    {
        $movimientos = PedidoBmaHistorialEstado::query()
            ->whereIn('accion', [AccionesHistorialPedidoBma::GUIAS_COMUNICADAS_CEDIS, AccionesHistorialPedidoBma::ABRIR_GUIAS_CEDIS])
            ->whereHas('pedido', fn (Builder $pedido) => VisibilidadPedidoBma::aplicarAlcanceCedis($pedido, $usuario))
            ->when($pedidoIds !== null, fn (Builder $query) => $query->whereIn('pedido_bma_id', $pedidoIds))
            ->when($documentoId !== null, fn (Builder $query) => $query->whereJsonContains('snapshot_json->documentos', $documentoId))
            ->with(['pedido.estatus', 'pedido.vendedor.departamentos'])
            ->latest('id')->get();

        $abiertas = $movimientos->where('accion', AccionesHistorialPedidoBma::ABRIR_GUIAS_CEDIS)
            ->reduce(function (array $aperturas, PedidoBmaHistorialEstado $movimiento) {
                foreach ($movimiento->snapshot_json['documentos'] as $id) {
                    $aperturas[$id] = $movimiento->id;
                }

                return $aperturas;
            }, []);
        $recibidas = $movimientos->where('accion', AccionesHistorialPedidoBma::GUIAS_COMUNICADAS_CEDIS);
        $documentos = PedidoBmaDocumento::query()->with('caja')
            ->whereKey($documentoId !== null
                ? [$documentoId]
                : $recibidas->flatMap(fn (PedidoBmaHistorialEstado $movimiento) => $movimiento->snapshot_json['documentos'])->unique()->all())
            ->get()->keyBy('id');

        return $recibidas->flatMap(function (PedidoBmaHistorialEstado $movimiento) use ($usuario, $documentoId, $documentos, $abiertas) {
            $pedido = $movimiento->pedido;
            if (! $pedido || ! VisibilidadPedidoBma::puedeConsultarDocumentos($usuario, $pedido, PedidoBmaDocumento::TIPO_GUIA)) {
                return [];
            }

            return collect($movimiento->snapshot_json['guias'])
                ->when($documentoId !== null, fn (Collection $guias) => $guias->only([$documentoId]))
                ->map(function (array $guia, int $id) use ($pedido, $documentos, $abiertas) {
                    $documento = $documentos->get($id);

                    return $guia + [
                        'id' => $id,
                        'pedido' => $pedido,
                        'documento' => $documento,
                        'vigente' => $this->vigente($documento, $pedido->id, $guia['caja_id']),
                        'abierta' => isset($abiertas[$id]),
                        'apertura_id' => $abiertas[$id] ?? null,
                    ];
                })->values();
        })->unique('id')->keyBy('id');
    }

    public function vigente(?PedidoBmaDocumento $documento, int $pedidoId, ?int $cajaId): bool
    {
        if (! $documento?->activo || $documento->tipo !== PedidoBmaDocumento::TIPO_GUIA
            || (int) $documento->pedido_bma_id !== $pedidoId
            || (int) $documento->pedido_bma_caja_id !== (int) $cajaId) {
            return false;
        }

        return $cajaId === null
            || ($documento->relacion_tipo === GuiasPdfPorCaja::CONFIRMADA && $documento->caja?->estaActiva()
                && (int) $documento->caja->pedido_bma_id === $pedidoId);
    }

    public function agrupar(Collection $guias, string $folio): Collection
    {
        return $guias
            ->filter(fn (array $guia) => $folio === '' || Str::contains(
                $guia['pedido']->folio.' '.$guia['pedido']->folio_remision,
                $folio,
                ignoreCase: true,
            ))
            ->groupBy('pedido.id')
            ->map(function (Collection $grupo) {
                $pedido = $grupo->first()['pedido'];

                return [
                    'id' => $pedido->id,
                    'folio' => $pedido->folio_remision ?: $pedido->folio,
                    'guias' => $grupo->sortBy('orden')->values()->map(fn (array $guia) => [
                        'id' => $guia['id'],
                        'caja' => $guia['caja'],
                        'nombre_original' => $guia['nombre'],
                        'mime_type' => 'application/pdf',
                        'url' => route('control_pedidos.cedis.impresion_guias.archivo', $guia['id']),
                        'vigente' => $guia['vigente'],
                    ]),
                ];
            })->values();
    }

    /** @return array{string, string} */
    public function archivo(array $guia): array
    {
        if (isset($guia['copia'])) {
            return ['local', $guia['copia']];
        }

        $documento = $guia['documento'];
        abort_unless($documento && $documento->tipo === PedidoBmaDocumento::TIPO_GUIA
            && $documento->relacion_tipo === GuiasPdfPorCaja::CONFIRMADA
            && (int) $documento->pedido_bma_id === (int) $guia['pedido']->id
            && (int) $documento->pedido_bma_caja_id === (int) $guia['caja_id'], 404);

        return [$documento->discoArchivo(), $documento->ruta_archivo];
    }
}
