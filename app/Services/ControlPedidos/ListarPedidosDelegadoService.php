<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListarPedidosDelegadoService
{
    public function ejecutar(array $filtros = [], bool $paginar = true)
    {
        $query = $this->queryBase($filtros);
        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(): array
    {
        return [
            'pendientes_guia' => $this->queryPendientesGuia()->count(),
            'pendientes_correccion' => $this->queryCorreccionGuia()->count(),
        ];
    }

    public function pedidosParaExportar(): Collection
    {
        return $this->queryPendientesGuia()->get();
    }

    private function queryBase(array $filtros): Builder
    {
        $tab = strtoupper($filtros['tab'] ?? 'PENDIENTES_GUIA');

        return match ($tab) {
            'CORRECCION' => $this->queryCorreccionGuia(),
            default => $this->queryPendientesGuia(),
        };
    }

    private function queryPendientesGuia(): Builder
    {
        $estatusId = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA)?->id ?? 0;

        return PedidoBma::with(['cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos'])
            ->where('catalogo_estatus_pedido_id', $estatusId)
            ->orderBy('folio_remision');
    }

    private function queryCorreccionGuia(): Builder
    {
        $estatusId = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO)?->id ?? 0;

        return PedidoBma::with(['cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos'])
            ->where('catalogo_estatus_pedido_id', $estatusId)
            ->whereNotNull('numero_rastreo')
            ->orderBy('folio_remision');
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (empty($filtros['q'])) {
            return;
        }

        $termino = trim($filtros['q']);
        $query->where(function (Builder $q) use ($termino) {
            $q->where('folio', 'like', "%{$termino}%")
                ->orWhere('folio_remision', 'like', "%{$termino}%")
                ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                    $c->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('numero_cliente', 'like', "%{$termino}%");
                });
        });
    }
}
