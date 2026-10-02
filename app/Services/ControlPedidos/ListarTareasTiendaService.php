<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
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
                'pedido.referencias',
                'pedido.vendedor',
                'modalidad',
                'almacen',
                'asignadaA',
                'productos',
                'solicitudTraspaso.estado',
                'paqueteria',
                'caratulas',
                'cumplimientoFisico',
            ]);

        VisibilidadTareaPreparacion::filtrarTienda($query, $usuario, $this->config);

        $query = match ($tab) {
            'EN_ATENCION' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION),
            'CON_INCIDENCIA' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA),
            'RESPONDIDAS_HOY' => $query->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            ])->whereDate('atendida_at', today()),
            'PENDIENTES_LIBERACION' => $query->where(function ($q) {
                $q->where('estado', PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA)
                    ->orWhere(function ($q2) {
                        $q2->where('estado', PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA)
                            ->whereNotNull('espera_pago_at');
                    })
                    ->orWhere(function ($q2) {
                        $q2->where('estado', PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA)
                            ->whereHas('modalidad', fn ($m) => $m->where('codigo', 'RECOGE_TIENDA_TRANSFERENCIA'));
                    });
            }),
            'LISTAS_TRASLADO' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO),
            'LISTAS_CARATULA' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA),
            'EN_TRASLADO' => $query->where('estado', PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO),
            'RECHAZADAS_CEDIS' => $query->where(function (Builder $q) {
                $q->where('estado', PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS)
                    ->orWhere(function (Builder $q2) {
                        $q2->where('estado', PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA)
                            ->whereNotNull('motivo_rechazo_cedis');
                    });
            }),
            'DEVOLUCION_PENDIENTE' => $query->whereHas(
                'cumplimientoFisico',
                fn ($c) => $c->where('estado', PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE)
            ),
            'HISTORIAL_DEVUELTAS' => $query->whereHas(
                'cumplimientoFisico',
                fn ($c) => $c->where('estado', PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL)
            ),
            'HISTORIAL' => $query->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
                PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
            ]),
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
            'pendientes_liberacion' => (clone $base)->where(function ($q) {
                $q->where('estado', PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA)
                    ->orWhere(function ($q2) {
                        $q2->where('estado', PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA)
                            ->where(function ($q3) {
                                $q3->whereNotNull('espera_pago_at')
                                    ->orWhereHas('modalidad', fn ($m) => $m->where('codigo', 'RECOGE_TIENDA_TRANSFERENCIA'));
                            });
                    });
            })->count(),
            'listas_traslado' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO)->count(),
            'listas_caratula' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA)->count(),
            'en_traslado' => (clone $base)->where('estado', PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO)->count(),
            'rechazadas_cedis' => (clone $base)->where(function (Builder $q) {
                $q->where('estado', PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS)
                    ->orWhere(function (Builder $q2) {
                        $q2->where('estado', PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA)
                            ->whereNotNull('motivo_rechazo_cedis');
                    });
            })->count(),
            'devolucion_pendiente' => (clone $base)->whereHas(
                'cumplimientoFisico',
                fn ($c) => $c->where('estado', PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE)
            )->count(),
            'historial_devueltas' => (clone $base)->whereHas(
                'cumplimientoFisico',
                fn ($c) => $c->where('estado', PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL)
            )->count(),
            'historial' => (clone $base)->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
                PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
            ])->count(),
            'prioridad_md_activas' => (clone $base)->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
                PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
                PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
                PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
            ])->whereHas('pedido', fn ($p) => $p->where('prioridad_md', true))->count(),
            'observabilidad_origen' => $this->conteoPorOrigen($base),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function conteoPorOrigen(Builder $base): array
    {
        $activas = (clone $base)->whereIn('estado', [
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
        ]);

        $out = [];
        foreach (PedidoBma::ORIGENES_SOLICITUD as $origen) {
            $out[$origen] = (clone $activas)->whereHas(
                'pedido',
                fn ($p) => $p->where('origen_solicitud', $origen)
            )->count();
        }
        $out['SIN_ORIGEN'] = (clone $activas)->whereHas(
            'pedido',
            fn ($p) => $p->whereNull('origen_solicitud')
        )->count();

        return $out;
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['q'])) {
            $q = trim((string) $filtros['q']);
            $query->whereHas('pedido', function ($p) use ($q) {
                $p->where('folio', 'like', "%{$q}%")
                    ->orWhere('folio_remision', 'like', "%{$q}%")
                    ->orWhere('contacto_nombre_snapshot', 'like', "%{$q}%")
                    ->orWhere('contacto_telefono_snapshot', 'like', "%{$q}%")
                    ->orWhereHas('referencias', fn ($r) => $r->where('folio', 'like', "%{$q}%"))
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

        if (! empty($filtros['origen_solicitud'])) {
            $origen = (string) $filtros['origen_solicitud'];
            if ($origen === 'SIN_ORIGEN') {
                $query->whereHas('pedido', fn ($p) => $p->whereNull('origen_solicitud'));
            } else {
                $query->whereHas('pedido', fn ($p) => $p->where('origen_solicitud', $origen));
            }
        }

        if (array_key_exists('prioridad_md', $filtros) && $filtros['prioridad_md'] !== '' && $filtros['prioridad_md'] !== null) {
            $md = filter_var($filtros['prioridad_md'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($md !== null) {
                $query->whereHas('pedido', fn ($p) => $p->where('prioridad_md', $md));
            }
        }
    }
}
