<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\FiltroPaqueteriaDelegadoPedidoBma;
use App\Support\ControlPedidos\SituacionOperativaDelegadoPedidoBma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListarPedidosDelegadoService
{
    public function ejecutar(array $filtros = [], bool $paginar = true)
    {
        $query = $this->queryBase();
        $this->aplicarFiltros($query, $filtros);
        $this->aplicarOrden($query, $filtros);

        if (! $paginar) {
            return $query->get();
        }

        $page = max(1, (int) ($filtros['page'] ?? 1));

        return $query->paginate(15, ['*'], 'page', $page)->withQueryString();
    }

    public function metricas(): array
    {
        $pendientesGuia = (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopePendientesGuia($q))
            ->count();
        $pendienteEmpaque = (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopePendienteEmpaqueConGuia($q))
            ->count();
        $pendientesEnvio = (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopePendientesEnvio($q))
            ->count();
        $enviados = (clone $this->queryBase())
            ->where(fn (Builder $q) => $this->scopeEnviados($q))
            ->count();

        return [
            'pendientes_guia' => $pendientesGuia,
            'pendiente_empaque' => $pendienteEmpaque,
            'pendientes_envio' => $pendientesEnvio,
            'enviados' => $enviados,
            'total' => $pendientesGuia + $pendienteEmpaque + $pendientesEnvio + $enviados,
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
            'vendedor.departamento:id,nombre',
            'vendedor.departamentos:id,nombre',
            'documentos',
            'origen',
            'almacen',
            'tipoGuia',
            'zona',
            'direccionVigente',
            'guiaCorregidaPor',
            'errorDatosPor',
            'historial.usuario',
            'historial.estatusAnterior',
            'historial.estatusNuevo',
        ];
    }

    private function queryBase(): Builder
    {
        return PedidoBma::with($this->withRelaciones())
            ->whereNotNull('pago_validado_at')
            ->where('cliente_proporciona_guia', false)
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
                    ->orWhere(fn (Builder $q2) => $this->scopePendienteEmpaqueConGuia($q2))
                    ->orWhere(fn (Builder $q2) => $this->scopePendientesEnvio($q2))
                    ->orWhere(fn (Builder $q2) => $this->scopeEnviados($q2));
            });
    }

    /** Sin número de guía: EN_CEDIS o ya empacado esperando rastreo. */
    private function scopePendientesGuia(Builder $query): void
    {
        $ids = $this->idsPorFase([
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        ]);

        $query->whereIn('catalogo_estatus_pedido_id', $ids ?: [0])
            ->whereNull('numero_rastreo');
    }

    /** Guía capturada en paralelo; sigue en pendiente de empaque (CEDIS). */
    private function scopePendienteEmpaqueConGuia(Builder $query): void
    {
        $id = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS)?->id;

        $query->where('catalogo_estatus_pedido_id', $id ?? 0)
            ->whereNotNull('numero_rastreo')
            ->whereNull('empacado_at');
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
                    ->orWhere('numero_rastreo', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                        $c->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('numero_cliente', 'like', "%{$termino}%");
                    });

                if (ctype_digit($termino)) {
                    $q->orWhere('id', (int) $termino);
                }
            });
        }

        $paqueteria = FiltroPaqueteriaDelegadoPedidoBma::resolver($filtros);
        if ($paqueteria['forzar_vacio']) {
            $query->whereRaw('1 = 0');
        } elseif ($paqueteria['aplicar']) {
            $query->whereIn('catalogo_paqueteria_id', $paqueteria['ids']);
        }

        $situacion = strtolower(trim((string) ($filtros['situacion'] ?? '')));
        if ($situacion !== '' && in_array($situacion, SituacionOperativaDelegadoPedidoBma::valoresPermitidos(), true)) {
            SituacionOperativaDelegadoPedidoBma::aplicar($query, $situacion);
        }

        $tab = strtoupper($filtros['tab'] ?? 'PENDIENTES_GUIA');

        match ($tab) {
            'TODOS' => null,
            CatalogoEstatusPedido::FASE_EN_CEDIS, 'PENDIENTE_EMPAQUE' => $query->where(
                fn (Builder $q) => $this->scopePendienteEmpaqueConGuia($q)
            ),
            'PENDIENTES_ENVIO', 'CORRECCION' => $query->where(fn (Builder $q) => $this->scopePendientesEnvio($q)),
            'ENVIADOS' => $query->where(fn (Builder $q) => $this->scopeEnviados($q)),
            default => $query->where(fn (Builder $q) => $this->scopePendientesGuia($q)),
        };
    }

    private function aplicarOrden(Builder $query, array $filtros): void
    {
        $orden = strtolower(trim((string) ($filtros['ordenar'] ?? 'fecha_desc')));

        match ($orden) {
            'fecha_asc' => $query->reorder()->orderBy('pedidos_bma.created_at')->orderBy('pedidos_bma.id'),
            default => $query->reorder()->orderByDesc('pedidos_bma.created_at')->orderByDesc('pedidos_bma.id'),
        };
    }
}
