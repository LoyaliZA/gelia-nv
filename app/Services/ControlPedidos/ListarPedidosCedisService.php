<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    public function ejecutar(array $filtros = [], bool $paginar = true): LengthAwarePaginator|Collection
    {
        $tab = strtoupper($filtros['tab'] ?? 'TODOS');
        $query = match ($tab) {
            'PENDIENTES_PESAJE' => $this->queryPendientesPesaje(),
            'TODOS', '' => $this->queryTodos(),
            default => $this->queryBase(),
        };

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
        $pendientesPesaje = $this->queryPendientesPesaje()->count();
        $entregados = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['ENTREGADO'] ?? 0)->count();

        return [
            'empacados' => $empacados,
            'pendientes_envio' => $pendientesEnvio,
            'pendientes_guia' => $pendientesGuia,
            'enviados' => $enviados,
            'incorrectas' => $incorrectas,
            'pendientes' => $empacados,
            'incidencias' => $incorrectas,
            'pendientes_pesaje' => $pendientesPesaje,
            'total' => $empacados + $pendientesEnvio + $pendientesGuia + $enviados + $incorrectas
                + $entregados + $pendientesPesaje,
        ];
    }

    private function withRelations(): array
    {
        return [
            'cliente',
            'vendedor',
            'estatus',
            'origen',
            'almacen',
            'paqueteria',
            'tipoGuia',
            'tipoCaja',
            'cajas.tipoCaja', 'cajas.tipoGuia',
            'documentos',
            'empacadoPor',
            'incidenciaEmpaquePor',
            'resguardoApartadoPor',
            'errores.reportadoPor',
            'errores.corregidoPor',
            'direccionVigente',
            'tipoOperacionEnvio',
            'historial.usuario',
            'historial.estatusAnterior',
            'historial.estatusNuevo',
            'complementos.documentos',
            'complementos.estatus',
            'complementos.cliente',
        ];
    }

    /** Bandeja CEDIS post-remisión (sin pendientes de pesaje). */
    private function queryBase(): Builder
    {
        $idsVisibles = $this->idsEstatusVisibles();

        return PedidoBma::with($this->withRelations())
            ->whereNull('pedido_principal_id')
            ->whereIn('catalogo_estatus_pedido_id', $idsVisibles ?: [0])
            ->whereNotNull('pago_validado_at')
            ->whereHas('remision')
            ->orderByDesc('created_at');
    }

    /** TODOS = bandeja CEDIS + pendientes de pesaje. */
    private function queryTodos(): Builder
    {
        $idsVisibles = $this->idsEstatusVisibles();
        $estatusPesaje = PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE;

        return PedidoBma::with($this->withRelations())
            ->whereNull('pedido_principal_id')
            ->where(function (Builder $q) use ($idsVisibles, $estatusPesaje) {
                $q->where(function (Builder $bandeja) use ($idsVisibles) {
                    $bandeja->whereIn('catalogo_estatus_pedido_id', $idsVisibles ?: [0])
                        ->whereNotNull('pago_validado_at')
                        ->whereHas('remision');
                })->orWhere(function (Builder $pesaje) use ($estatusPesaje) {
                    $pesaje->where('estatus_envio', $estatusPesaje)
                        ->whereNull('empacado_at');
                });
            })
            ->orderByRaw('CASE WHEN estatus_envio = ? THEN 0 ELSE 1 END', [$estatusPesaje])
            ->orderByDesc('pesaje_solicitado_at')
            ->orderByDesc('created_at');
    }

    private function queryPendientesPesaje(): Builder
    {
        return PedidoBma::with($this->withRelations())
            ->whereNull('pedido_principal_id')
            ->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE)
            ->whereNull('empacado_at')
            ->orderByDesc('pesaje_solicitado_at')
            ->orderByDesc('created_at');
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function (Builder $q) use ($termino) {
                $q->where('folio', 'like', "%{$termino}%")
                    ->orWhere('folio_remision', 'like', "%{$termino}%")
                    ->orWhereHas('complementos', function (Builder $c) use ($termino) {
                        $c->where('folio', 'like', "%{$termino}%")
                            ->orWhere('folio_remision', 'like', "%{$termino}%");
                    })
                    ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                        $c->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('numero_cliente', 'like', "%{$termino}%");
                    });
            });
        }

        $tab = strtoupper($filtros['tab'] ?? 'TODOS');
        if ($tab === 'PENDIENTES_PESAJE' || $tab === 'TODOS' || $tab === '') {
            return;
        }

        $idsPorFase = $this->idsPorFase();

        match ($tab) {
            'EMPACADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['EN_CEDIS'] ?? 0),
            'PENDIENTES_ENVIO' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_DE_ENVIO'] ?? 0),
            'PENDIENTES_GUIA' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_DE_GUIA'] ?? 0),
            'ENVIADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['ENVIADO'] ?? 0),
            'INCORRECTAS', 'INCIDENCIAS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['INCIDENCIA_CEDIS'] ?? 0),
            'PENDIENTES' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['EN_CEDIS'] ?? 0),
            default => null,
        };
    }

    private function idsEstatusVisibles(): array
    {
        $idsPorFase = $this->idsPorFase();
        $fasesVisibles = array_merge(self::FASES_PENDIENTES, self::FASES_EMPACADOS);

        return array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            $fasesVisibles
        )));
    }

    private function idsPorFase(): array
    {
        return CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', array_merge(self::FASES_PENDIENTES, self::FASES_EMPACADOS))
            ->pluck('id', 'fase_ciclo')
            ->all();
    }
}
