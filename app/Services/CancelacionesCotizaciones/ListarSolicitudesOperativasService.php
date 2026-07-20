<?php

namespace App\Services\CancelacionesCotizaciones;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListarSolicitudesOperativasService
{
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        $query = SolicitudTag::with([
            'cliente',
            'vendedor',
            'departamento',
            'proceso',
            'estado',
            'banco',
            'auditorias.usuario',
            'auditorias.estadoAnterior',
            'auditorias.estadoNuevo',
            'auditorias.estadoNuevo',
        ])
            ->whereHas('proceso', function (Builder $proceso) {
                $proceso->where('categoria_flujo', CatalogoProceso::CATEGORIA_OPERATIVO);
            })
            ->orderByDesc('created_at');

        if ($usuario) {
            $this->aplicarAislamiento($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(?User $usuario): array
    {
        $query = SolicitudTag::query()
            ->whereHas('proceso', function (Builder $proceso) {
                $proceso->where('categoria_flujo', CatalogoProceso::CATEGORIA_OPERATIVO);
            });

        if ($usuario) {
            $this->aplicarAislamiento($query, $usuario);
        }

        $hoy = now()->toDateString();
        $idRespondida = CatalogoEstadoSolicitud::where('nombre', 'Respondida')->value('id');
        $idCancelada = CatalogoEstadoSolicitud::where('nombre', 'Cancelada')->value('id');

        return [
            'pendientes' => (clone $query)->where(function (Builder $q) use ($idCancelada) {
                $q->where('catalogo_estado_solicitud_id', 1)
                    ->orWhere(function (Builder $sub) use ($idCancelada) {
                        $sub->whereNotNull('cancelacion_solicitada_at');
                        if ($idCancelada) {
                            $sub->where('catalogo_estado_solicitud_id', '!=', $idCancelada);
                        }
                    })
                    ->orWhereHas('consultas', function (Builder $c) {
                        $c->where('estado', 'pendiente');
                    });
            })->count(),
            'respondidas_hoy' => $idRespondida
                ? (clone $query)->where('catalogo_estado_solicitud_id', $idRespondida)
                    ->whereDate('updated_at', $hoy)->count()
                : 0,
            'incorrectas' => (clone $query)->where('catalogo_estado_solicitud_id', 4)->count(),
        ];
    }

    public function usuarioPuedeVer(User $usuario, SolicitudTag $solicitud): bool
    {
        $solicitud->loadMissing('proceso');
        if (!$solicitud->proceso?->esOperativo()) {
            return false;
        }

        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return true;
        }

        if ($solicitud->vendedor_id === $usuario->id) {
            return true;
        }

        $tieneVisibilidadArea = $usuario->hasRole('Gerente') ||
            $usuario->hasAnyPermission([
                'cancelaciones_cotizaciones.verificar',
                'configuracion.ver_auditoria',
            ]);

        if ($tieneVisibilidadArea) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();

            return !empty($departamentos) && in_array($solicitud->departamento_id, $departamentos, true);
        }

        return false;
    }

    public function asegurarOperativa(SolicitudTag $solicitud): void
    {
        $solicitud->loadMissing('proceso');
        if (!$solicitud->proceso?->esOperativo()) {
            abort(404, 'Solicitud no encontrada en este módulo.');
        }
    }

    private function aplicarAislamiento(Builder $query, User $usuario): void
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return;
        }

        $tieneVisibilidadArea = $usuario->hasRole('Gerente') ||
            $usuario->hasAnyPermission([
                'cancelaciones_cotizaciones.verificar',
                'configuracion.ver_auditoria',
            ]);

        if ($tieneVisibilidadArea) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();
            if (!empty($departamentos)) {
                $query->whereIn('departamento_id', $departamentos);
            } else {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $query->where('vendedor_id', $usuario->id);
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['tab']) && $filtros['tab'] !== 'TODAS') {
            $this->aplicarFiltroTab($query, $filtros['tab']);
        }

        if (!empty($filtros['tipo_operativo'])) {
            $tipo = strtoupper($filtros['tipo_operativo']);
            $query->whereHas('proceso', function (Builder $proceso) use ($tipo) {
                match ($tipo) {
                    'REMISION' => $proceso->where(function (Builder $q) {
                        $q->where('nombre', 'like', '%REMISIÓN%')
                            ->orWhere('nombre', 'like', '%REMISION%');
                    }),
                    'PEDIDO' => $proceso->where(function (Builder $q) {
                        $q->where('nombre', 'like', '%CANCELACIÓN DE PEDIDO%')
                            ->orWhere('nombre', 'like', '%CANCELACION DE PEDIDO%');
                    }),
                    'COTIZACION' => $proceso->where(function (Builder $q) {
                        $q->where('nombre', 'like', '%COTIZACIÓN%')
                            ->orWhere('nombre', 'like', '%COTIZACION%');
                    }),
                    default => null,
                };
            });
        }

        if (!empty($filtros['proceso_id'])) {
            $query->where('catalogo_proceso_id', $filtros['proceso_id']);
        }

        if (!empty($filtros['vendedor_id'])) {
            $query->where('vendedor_id', $filtros['vendedor_id']);
        }

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereBetween('created_at', [
                $filtros['fecha_inicio'] . ' 00:00:00',
                $filtros['fecha_fin'] . ' 23:59:59',
            ]);
        }

        if (!empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $terminoSinFolio = preg_replace('/^fol-?\s*/i', '', $termino);
            $like = '%' . addcslashes($termino, '%_\\') . '%';

            $query->where(function (Builder $q) use ($termino, $terminoSinFolio, $like) {
                if ($terminoSinFolio !== '' && ctype_digit($terminoSinFolio)) {
                    $q->where('id', (int) $terminoSinFolio);
                } elseif (ctype_digit($termino)) {
                    $q->where('id', (int) $termino);
                }

                $q->orWhere('numero_remision', 'like', $like)
                    ->orWhere('numero_pedido', 'like', $like)
                    ->orWhereHas('cliente', function (Builder $cq) use ($like) {
                        $cq->where('nombre', 'like', $like)
                            ->orWhere('numero_cliente', 'like', $like)
                            ->orWhere('nombre_razon_social', 'like', $like);
                    });
            });
        }
    }

    private function aplicarFiltroTab(Builder $query, string $tab): void
    {
        $idCancelada = CatalogoEstadoSolicitud::where('nombre', 'Cancelada')->value('id');

        match ($tab) {
            'PENDIENTES' => $query->where(function (Builder $q) use ($idCancelada) {
                $q->where('catalogo_estado_solicitud_id', 1)
                    ->orWhere(function (Builder $sub) use ($idCancelada) {
                        $sub->whereNotNull('cancelacion_solicitada_at');
                        if ($idCancelada) {
                            $sub->where('catalogo_estado_solicitud_id', '!=', $idCancelada);
                        }
                    })
                    ->orWhereHas('consultas', function (Builder $c) {
                        $c->where('estado', 'pendiente');
                    });
            }),
            'RESPONDIDAS' => $query->where('catalogo_estado_solicitud_id', 2),
            'VERIFICADAS' => $query->where('catalogo_estado_solicitud_id', 3),
            'INCORRECTAS' => $query->where('catalogo_estado_solicitud_id', 4),
            'CANCELADAS' => $idCancelada
                ? $query->where('catalogo_estado_solicitud_id', $idCancelada)
                : $query->whereRaw('1 = 0'),
            default => null,
        };
    }
}
