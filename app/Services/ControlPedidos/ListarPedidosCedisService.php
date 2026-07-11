<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;

class ListarPedidosCedisService
{
    private const FASES_CEDIS = [
        CatalogoEstatusPedido::FASE_EN_CEDIS,
        CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        CatalogoEstatusPedido::FASE_EN_RUTA,
    ];

    public function ejecutar(array $filtros = [], bool $paginar = true)
    {
        $query = $this->queryBase();
        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(): array
    {
        $base = $this->queryBase();
        $idsPorFase = $this->idsPorFase();

        $pendientes = (clone $base)->whereIn('catalogo_estatus_pedido_id', array_filter([
            $idsPorFase['EN_CEDIS'] ?? null,
            $idsPorFase['INCIDENCIA_CEDIS'] ?? null,
        ]))->count();
        $empacados = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['EN_RUTA'] ?? 0)->count();

        return [
            'pendientes' => $pendientes,
            'empacados' => $empacados,
            'total' => $pendientes + $empacados,
        ];
    }

    private function queryBase(): Builder
    {
        $idsPorFase = $this->idsPorFase();
        $idsVisibles = array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            self::FASES_CEDIS
        )));

        return PedidoBma::with([
            'cliente',
            'estatus',
            'almacenSalida',
            'paqueteria',
            'tipoGuia',
            'tipoCaja',
            'documentos',
            'empacadoPor',
            'incidenciaEmpaquePor',
        ])
            ->whereIn('catalogo_estatus_pedido_id', $idsVisibles ?: [0])
            ->whereNotNull('pago_validado_at')
            ->whereHas('remision')
            ->orderByDesc('created_at');
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function (Builder $q) use ($termino) {
                $q->where('folio', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                        $c->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('numero_cliente', 'like', "%{$termino}%");
                    });
            });
        }

        $tab = strtoupper($filtros['tab'] ?? 'PENDIENTES');
        $idsPorFase = $this->idsPorFase();

        match ($tab) {
            'PENDIENTES' => $query->whereIn('catalogo_estatus_pedido_id', array_filter([
                $idsPorFase['EN_CEDIS'] ?? null,
                $idsPorFase['INCIDENCIA_CEDIS'] ?? null,
            ])),
            'EMPACADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['EN_RUTA'] ?? 0),
            default => null,
        };
    }

    private function idsPorFase(): array
    {
        return CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', self::FASES_CEDIS)
            ->pluck('id', 'fase_ciclo')
            ->all();
    }
}
