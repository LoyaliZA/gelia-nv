<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;

class ListarPedidosCedisService
{
    private const FASES_PENDIENTES = [
        CatalogoEstatusPedido::FASE_EN_CEDIS,
        CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
    ];

    private const FASES_EMPACADOS = [
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_ENTREGADO,
        CatalogoEstatusPedido::FASE_ENVIADO,
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

        $pendientes = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['EN_CEDIS'] ?? 0)->count();
        $incidencias = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['INCIDENCIA_CEDIS'] ?? 0)->count();
        $empacados = (clone $base)->whereIn('catalogo_estatus_pedido_id', array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            self::FASES_EMPACADOS
        ))))->whereNotNull('empacado_at')->count();

        return [
            'pendientes' => $pendientes,
            'incidencias' => $incidencias,
            'empacados' => $empacados,
            'total' => $pendientes + $incidencias + $empacados,
        ];
    }

    private function queryBase(): Builder
    {
        $idsPorFase = $this->idsPorFase();
        $fasesVisibles = array_merge(self::FASES_PENDIENTES, self::FASES_EMPACADOS);
        $idsVisibles = array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            $fasesVisibles
        )));

        return PedidoBma::with([
            'cliente',
            'vendedor',
            'estatus',
            'origen',
            'almacen',
            'paqueteria',
            'tipoGuia',
            'tipoCaja',
            'documentos',
            'empacadoPor',
            'incidenciaEmpaquePor',
        ])
            ->whereIn('catalogo_estatus_pedido_id', $idsVisibles ?: [0])
            ->where('es_resguardo', false)
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
                    ->orWhere('folio_remision', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                        $c->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('numero_cliente', 'like', "%{$termino}%");
                    });
            });
        }

        $tab = strtoupper($filtros['tab'] ?? 'PENDIENTES');
        $idsPorFase = $this->idsPorFase();

        $idsEmpacados = array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            self::FASES_EMPACADOS
        )));

        match ($tab) {
            'PENDIENTES' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['EN_CEDIS'] ?? 0),
            'INCIDENCIAS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['INCIDENCIA_CEDIS'] ?? 0),
            'EMPACADOS' => $query->whereIn('catalogo_estatus_pedido_id', $idsEmpacados ?: [0])
                ->whereNotNull('empacado_at'),
            default => null,
        };
    }

    private function idsPorFase(): array
    {
        return CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', array_merge(self::FASES_PENDIENTES, self::FASES_EMPACADOS))
            ->pluck('id', 'fase_ciclo')
            ->all();
    }
}
