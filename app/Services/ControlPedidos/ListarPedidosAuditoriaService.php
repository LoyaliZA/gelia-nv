<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;

class ListarPedidosAuditoriaService
{
    private const FASES_AUDITORIA = [
        CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
        CatalogoEstatusPedido::FASE_EN_CEDIS,
        CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
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

        $pendientes = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0)->count();
        $aprobados = (clone $base)->whereIn('catalogo_estatus_pedido_id', array_filter([
            $idsPorFase['EN_CEDIS'] ?? null,
            $idsPorFase['INCIDENCIA_CEDIS'] ?? null,
            $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
            $idsPorFase['PENDIENTE_DE_ENVIO'] ?? null,
            $idsPorFase['ENTREGADO'] ?? null,
            $idsPorFase['ENVIADO'] ?? null,
        ]))->count();
        $rechazados = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['RECHAZADO_VENDEDORA'] ?? 0)->count();
        $resguardos = (clone $base)->where('es_resguardo', true)->count();

        return [
            'pendientes' => $pendientes,
            'aprobados' => $aprobados,
            'rechazados' => $rechazados,
            'resguardos' => $resguardos,
            'total' => $pendientes + $aprobados + $rechazados,
        ];
    }

    private function queryBase(): Builder
    {
        $idsPorFase = $this->idsPorFase();
        $idsVisibles = array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            self::FASES_AUDITORIA
        )));

        return PedidoBma::with([
            'cliente',
            'vendedor',
            'estatus',
            'origen',
            'envioTienda',
            'almacen',
            'banco',
            'tipoCaja',
            'paqueteria',
            'tipoGuia',
            'zona',
            'documentos',
            'pagoValidadoPor',
            'incidenciaEmpaquePor',
            'direccionVigente',
            'historial.usuario',
            'historial.estatusAnterior',
            'historial.estatusNuevo',
        ])
            ->whereIn('catalogo_estatus_pedido_id', $idsVisibles ?: [0])
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

        $tab = strtoupper($filtros['tab'] ?? 'TODAS');
        $idsPorFase = $this->idsPorFase();

        match ($tab) {
            'PENDIENTES' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0),
            'APROBADOS' => $query->whereIn('catalogo_estatus_pedido_id', array_filter([
                $idsPorFase['EN_CEDIS'] ?? null,
                $idsPorFase['INCIDENCIA_CEDIS'] ?? null,
                $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
                $idsPorFase['PENDIENTE_DE_ENVIO'] ?? null,
                $idsPorFase['ENTREGADO'] ?? null,
                $idsPorFase['ENVIADO'] ?? null,
            ])),
            'RECHAZADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['RECHAZADO_VENDEDORA'] ?? 0),
            'RESGUARDOS' => $query->where('es_resguardo', true),
            default => null,
        };
    }

    private function idsPorFase(): array
    {
        return CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', self::FASES_AUDITORIA)
            ->pluck('id', 'fase_ciclo')
            ->all();
    }
}
