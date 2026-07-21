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

        $empacados = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['EN_CEDIS'] ?? 0)->count();
        $pendientesEnvio = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_DE_ENVIO'] ?? 0)->count();
        $pendientesGuia = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_DE_GUIA'] ?? 0)->count();
        $enviados = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['ENVIADO'] ?? 0)->count();
        $incorrectas = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['INCIDENCIA_CEDIS'] ?? 0)->count();

        return [
            'empacados' => $empacados,
            'pendientes_envio' => $pendientesEnvio,
            'pendientes_guia' => $pendientesGuia,
            'enviados' => $enviados,
            'incorrectas' => $incorrectas,
            // Compat KPI / tests previos
            'pendientes' => $empacados,
            'incidencias' => $incorrectas,
            'total' => $empacados + $pendientesEnvio + $pendientesGuia + $enviados + $incorrectas
                + ((clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['ENTREGADO'] ?? 0)->count()),
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
            'resguardoApartadoPor',
            'direccionVigente',
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
                    ->orWhere('folio_remision', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                        $c->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('numero_cliente', 'like', "%{$termino}%");
                    });
            });
        }

        $tab = strtoupper($filtros['tab'] ?? 'TODOS');
        $idsPorFase = $this->idsPorFase();

        match ($tab) {
            'EMPACADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['EN_CEDIS'] ?? 0),
            'PENDIENTES_ENVIO' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_DE_ENVIO'] ?? 0),
            'PENDIENTES_GUIA' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_DE_GUIA'] ?? 0),
            'ENVIADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['ENVIADO'] ?? 0),
            'INCORRECTAS', 'INCIDENCIAS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['INCIDENCIA_CEDIS'] ?? 0),
            // Legacy: tab PENDIENTES apuntaba a EN_CEDIS
            'PENDIENTES' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['EN_CEDIS'] ?? 0),
            default => null, // TODOS
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
