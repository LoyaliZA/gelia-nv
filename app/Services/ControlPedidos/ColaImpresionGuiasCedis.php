<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\ImpresionGuiaCedis;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Support\ControlPedidos\Nacional\GuiasPdfPorCaja;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ColaImpresionGuiasCedis
{
    public function consulta(User $usuario): Builder
    {
        return ImpresionGuiaCedis::query()
            ->whereHas('pedido', fn (Builder $query) => VisibilidadPedidoBma::aplicarAlcanceCedis($query, $usuario))
            ->with(['pedido.estatus', 'pedido.vendedor.departamentos', 'documento', 'caja']);
    }

    public function visible(User $usuario, ImpresionGuiaCedis $guia): bool
    {
        return $guia->pedido
            && VisibilidadPedidoBma::puedeVerEnCedis($usuario, $guia->pedido)
            && VisibilidadPedidoBma::puedeConsultarDocumentos($usuario, $guia->pedido, PedidoBmaDocumento::TIPO_GUIA);
    }

    public function vigente(ImpresionGuiaCedis $guia): bool
    {
        $documento = $guia->documento;
        if (! $documento?->activo || $documento->tipo !== PedidoBmaDocumento::TIPO_GUIA
            || (int) $documento->pedido_bma_id !== (int) $guia->pedido_bma_id
            || (int) $documento->pedido_bma_caja_id !== (int) $guia->pedido_bma_caja_id) {
            return false;
        }

        return $guia->pedido_bma_caja_id === null
            || ($documento->relacion_tipo === GuiasPdfPorCaja::CONFIRMADA && $guia->caja?->estaActiva()
                && (int) $guia->caja->pedido_bma_id === (int) $guia->pedido_bma_id);
    }

    public function agrupar(Collection $guias, string $folio): Collection
    {
        return $guias
            ->filter(fn (ImpresionGuiaCedis $guia) => $folio === '' || Str::contains(
                $guia->pedido->folio.' '.$guia->pedido->folio_remision,
                $folio,
                ignoreCase: true,
            ))
            ->groupBy('pedido_bma_id')
            ->map(function (Collection $grupo) {
                $pedido = $grupo->first()->pedido;

                return [
                    'id' => $pedido->id,
                    'folio' => $pedido->folio_remision ?: $pedido->folio,
                    'guias' => $grupo->sortBy('orden_caja')->values()->map(fn (ImpresionGuiaCedis $guia) => [
                        'id' => $guia->id,
                        'caja' => $guia->pedido_bma_caja_id === null
                            ? 'Guía general'
                            : ($guia->nombre_caja ?: 'Caja '.($guia->orden_caja + 1)),
                        'nombre_original' => $guia->nombre_pdf,
                        'mime_type' => 'application/pdf',
                        'url' => route('control_pedidos.cedis.impresion_guias.archivo', $guia->id),
                        'vigente' => $this->vigente($guia),
                        'confirmada_at' => $guia->impresion_confirmada_at?->toISOString(),
                    ]),
                ];
            })->values();
    }

    /** @return array{string, string} */
    public function archivo(ImpresionGuiaCedis $guia): array
    {
        if ($guia->ruta_copia) {
            return ['local', $guia->ruta_copia];
        }

        $documento = $guia->documento;
        abort_unless($documento && $documento->tipo === PedidoBmaDocumento::TIPO_GUIA
            && $documento->relacion_tipo === GuiasPdfPorCaja::CONFIRMADA
            && (int) $documento->pedido_bma_id === (int) $guia->pedido_bma_id
            && (int) $documento->pedido_bma_caja_id === (int) $guia->pedido_bma_caja_id, 404);

        return [$documento->discoArchivo(), $documento->ruta_archivo];
    }
}
