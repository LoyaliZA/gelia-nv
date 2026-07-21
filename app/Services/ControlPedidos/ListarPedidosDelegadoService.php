<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListarPedidosDelegadoService
{
    public function ejecutar(array $filtros = [], bool $paginar = true)
    {
        $query = $this->queryBase();
        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(): array
    {
        $pendientesGuia = (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopePendientesGuia($q))
            ->count();
        $pendientesEnvio = (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopePendientesEnvio($q))
            ->count();
        $enviados = (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopeEnviados($q))
            ->count();

        return [
            'pendientes_guia' => $pendientesGuia,
            'pendientes_envio' => $pendientesEnvio,
            'enviados' => $enviados,
            'total' => $pendientesGuia + $pendientesEnvio + $enviados,
            'pendientes_correccion' => $pendientesEnvio,
        ];
    }

    public function pedidosParaExportar(): Collection
    {
        return (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopePendientesGuia($q))
            ->orderBy('folio_remision')
            ->get();
    }

    private function withRelaciones(): array
    {
        return [
            'cliente',
            'paqueteria',
            'estatus',
            'vendedor',
            'documentos',
            'origen',
            'almacen',
            'tipoGuia',
            'zona',
            'direccionVigente',
            'guiaCorregidaPor',
            'errorDatosPor',
        ];
    }

    private function queryBase(): Builder
    {
        return PedidoBma::with($this->withRelaciones())
            ->whereNotNull('pago_validado_at')
            ->whereHas('remision')
            ->where(function (Builder $q) {
                $q->whereHas('paqueteria', function (Builder $p) {
                    $p->where('categoria', CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL);
                })->orWhere(function (Builder $q2) {
                    $q2->whereNull('catalogo_paqueteria_id')
                        ->whereHas('origen', fn (Builder $o) => $o->where('requiere_logistica', true));
                });
            })
            ->where(function (Builder $q) {
                $q->where(fn (Builder $q2) => $this->scopePendientesGuia($q2))
                    ->orWhere(fn (Builder $q2) => $this->scopePendientesEnvio($q2))
                    ->orWhere(fn (Builder $q2) => $this->scopeEnviados($q2));
            })
            ->orderBy('folio_remision');
    }

    private function scopePendientesGuia(Builder $query): void
    {
        $ids = $this->idsPorFase([
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        ]);

        $query->whereIn('catalogo_estatus_pedido_id', $ids ?: [0])
            ->whereNull('numero_rastreo');
    }

    private function scopePendientesEnvio(Builder $query): void
    {
        $id = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO)?->id;

        $query->where('catalogo_estatus_pedido_id', $id ?? 0)
            ->whereNotNull('numero_rastreo');
    }

    private function scopeEnviados(Builder $query): void
    {
        $id = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO)?->id;

        $query->where('catalogo_estatus_pedido_id', $id ?? 0);
    }

    private function idsPorFase(array $fases): array
    {
        return array_values(array_filter(
            CatalogoEstatusPedido::query()
                ->whereIn('fase_ciclo', $fases)
                ->pluck('id')
                ->all()
        ));
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['q'])) {
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

        $tab = strtoupper($filtros['tab'] ?? 'PENDIENTES_GUIA');

        match ($tab) {
            'TODOS' => null,
            'PENDIENTES_ENVIO', 'CORRECCION' => $query->where(fn (Builder $q) => $this->scopePendientesEnvio($q)),
            'ENVIADOS' => $query->where(fn (Builder $q) => $this->scopeEnviados($q)),
            default => $query->where(fn (Builder $q) => $this->scopePendientesGuia($q)),
        };
    }
}
