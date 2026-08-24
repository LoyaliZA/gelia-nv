<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListarTareasTiendaService
{
    public function __construct(
        private PreparacionTiendaConfig $config,
    ) {}

    public function ejecutar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $tab = strtoupper($filtros['tab'] ?? 'PENDIENTES');
        $query = PedidoBmaTareaPreparacion::query()
            ->with([
                'pedido.cliente',
                'pedido.vendedor',
                'modalidad',
                'almacen',
                'asignadaA',
                'productos',
                'solicitudTraspaso.estado',
                'paqueteria',
                'caratulas',
            ]);

        VisibilidadTareaPreparacion::filtrarTienda($query, $usuario, $this->config);

        $query = match ($tab) {
            'EN_ATENCION' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION),
            'CON_INCIDENCIA' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA),
            'RESPONDIDAS_HOY' => $query->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            ])->whereDate('atendida_at', today()),
            'PENDIENTES_LIBERACION' => $query->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            ])->whereHas('modalidad', fn ($q) => $q->where('codigo', 'RECOGE_TIENDA_TRANSFERENCIA')),
            'LISTAS_TRASLADO' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO),
            'LISTAS_CARATULA' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA),
            'EN_TRASLADO' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO),
            'RECHAZADAS_CEDIS' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS),
            default => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_PENDIENTE),
        };

        $this->aplicarFiltros($query, $filtros);

        $query->orderByRaw('CASE WHEN fecha_limite IS NOT NULL AND fecha_limite < NOW() THEN 0 ELSE 1 END')
            ->orderBy('solicitada_at');

        return $query->paginate(15)->withQueryString();
    }

    public function metricas(User $usuario): array
    {
        $base = PedidoBmaTareaPreparacion::query();
        VisibilidadTareaPreparacion::filtrarTienda($base, $usuario, $this->config);

        return [
            'pendientes' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_PENDIENTE)->count(),
            'en_atencion' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION)->count(),
            'con_incidencia' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA)->count(),
            'respondidas_hoy' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA)
                ->whereDate('atendida_at', today())->count(),
            'pendientes_liberacion' => (clone $base)->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            ])->whereHas('modalidad', fn ($q) => $q->where('codigo', 'RECOGE_TIENDA_TRANSFERENCIA'))->count(),
            'listas_traslado' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO)->count(),
            'listas_caratula' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA)->count(),
            'en_traslado' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO)->count(),
            'rechazadas_cedis' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS)->count(),
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['q'])) {
            $q = trim((string) $filtros['q']);
            $query->whereHas('pedido', function ($p) use ($q) {
                $p->where('folio', 'like', "%{$q}%")
                    ->orWhere('folio_remision', 'like', "%{$q}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$q}%")
                        ->orWhere('nombre_comercial', 'like', "%{$q}%"));
            });
        }

        if (! empty($filtros['modalidad'])) {
            $query->whereHas('modalidad', fn ($m) => $m->where('codigo', $filtros['modalidad']));
        }

        if (! empty($filtros['almacen_id'])) {
            $query->where('almacen_id', (int) $filtros['almacen_id']);
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
    }
}
