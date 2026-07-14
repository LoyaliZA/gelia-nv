<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListarPedidosBmaService
{
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        $query = PedidoBma::with([
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
            'direccionVigente',
            'historial.usuario',
            'historial.estatusAnterior',
            'historial.estatusNuevo',
        ])->orderByDesc('created_at');

        if ($usuario) {
            $this->aplicarAislamiento($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(?User $usuario): array
    {
        $base = PedidoBma::query();
        if ($usuario) {
            $this->aplicarAislamiento($base, $usuario);
        }

        $idsPorFase = $this->idsPorFase();

        return [
            'todas' => (clone $base)->count(),
            'borradores' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['BORRADOR'] ?? 0)->count(),
            'pendiente_auxiliar' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0)->count(),
            'en_cedis' => (clone $base)->whereIn('catalogo_estatus_pedido_id', array_filter([
                $idsPorFase['EN_CEDIS'] ?? null,
                $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
            ]))->count(),
            'enviados' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['ENVIADO'] ?? 0)->count(),
            'rechazadas' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['RECHAZADO_VENDEDORA'] ?? 0)->count(),
        ];
    }

    private function aplicarAislamiento(Builder $query, User $usuario): void
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return;
        }

        $query->where('vendedor_id', $usuario->id);
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
            'BORRADORES' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['BORRADOR'] ?? 0),
            'PENDIENTE_AUXILIAR' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0),
            'EN_CEDIS' => $query->whereIn('catalogo_estatus_pedido_id', array_filter([
                $idsPorFase['EN_CEDIS'] ?? null,
                $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
            ])),
            'ENVIADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['ENVIADO'] ?? 0),
            'RECHAZADAS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['RECHAZADO_VENDEDORA'] ?? 0),
            default => null,
        };
    }

    private function idsPorFase(): array
    {
        return CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', [
                'BORRADOR',
                'PENDIENTE_AUXILIAR',
                'EN_CEDIS',
                'PENDIENTE_DE_GUIA',
                'ENVIADO',
                'RECHAZADO_VENDEDORA',
            ])
            ->pluck('id', 'fase_ciclo')
            ->all();
    }

    public function asegurarAcceso(PedidoBma $pedido, User $usuario): void
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return;
        }

        if ($pedido->vendedor_id !== $usuario->id) {
            abort(403, 'No tienes autorización para acceder a este pedido.');
        }
    }
}
