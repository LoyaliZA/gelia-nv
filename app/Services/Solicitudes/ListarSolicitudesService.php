<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListarSolicitudesService
{
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        $query = SolicitudTag::with([
            'cliente.listaDescuento',
            'vendedor',
            'departamento',
            'proceso',
            'estado',
            'banco',
            'auditorias.usuario',
            'auditorias.estadoNuevo',
            'listaDescuento',
            'listaRebaja',
            'tipoCliente',
            'consultas.encargada',
            'consultas.vendedor',
        ])->whereHas('proceso', function (Builder $proceso) {
            $proceso->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO);
        })->orderBy('created_at', 'desc');

        if ($usuario) {
            $this->aplicarAislamientoDeDatos($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    private function aplicarAislamientoDeDatos(Builder $query, User $usuario): void
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return;
        }

        $tieneVisibilidadArea = $usuario->hasRole('Gerente') ||
            $usuario->hasAnyPermission(['solicitudes.verificar', 'solicitudes.reportar', 'solicitudes.cancelar']);

        if ($tieneVisibilidadArea) {
            $this->filtrarPorDepartamento($query, $usuario);
            return;
        }

        $query->where('vendedor_id', $usuario->id);
    }

    private function filtrarPorDepartamento(Builder $query, User $usuario): void
    {
        $departamentosUsuario = $usuario->departamentos->pluck('id')->toArray();

        if (!empty($departamentosUsuario)) {
            $query->whereIn('departamento_id', $departamentosUsuario);
        } else {
            $query->where('id', 0);
        }
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['tab']) && $filtros['tab'] !== 'TODAS') {
            $this->aplicarFiltroTab($query, $filtros['tab']);
        }

        if (!empty($filtros['estado_id'])) {
            $query->where('catalogo_estado_solicitud_id', $filtros['estado_id']);
        }

        if (!empty($filtros['proceso_id'])) {
            $query->where('catalogo_proceso_id', $filtros['proceso_id']);
        }

        if (!empty($filtros['vendedor_id'])) {
            $query->where('vendedor_id', $filtros['vendedor_id']);
        }

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereBetween('created_at', [$filtros['fecha_inicio'] . ' 00:00:00', $filtros['fecha_fin'] . ' 23:59:59']);
        } elseif (!empty($filtros['fecha_inicio'])) {
            $query->whereDate('created_at', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['mes'])) {
            $query->whereMonth('created_at', $filtros['mes']);
            if (!empty($filtros['anio'])) {
                $query->whereYear('created_at', $filtros['anio']);
            }
        }

        if (!empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function (Builder $q) use ($termino) {
                $q->where('id', 'like', '%' . $termino . '%')
                    ->orWhereHas('cliente', function (Builder $cq) use ($termino) {
                        $cq->where('nombre', 'like', '%' . $termino . '%')
                            ->orWhere('numero_cliente', 'like', '%' . $termino . '%');
                    });
            });
        }

        if (!empty($filtros['cliente_numero']) || !empty($filtros['cliente_nombre']) || isset($filtros['es_heredado'])) {
            $query->whereHas('cliente', function ($q) use ($filtros) {
                if (!empty($filtros['cliente_numero'])) {
                    $q->where('numero_cliente', 'like', '%' . $filtros['cliente_numero'] . '%');
                }
                if (!empty($filtros['cliente_nombre'])) {
                    $q->where('nombre', 'like', '%' . $filtros['cliente_nombre'] . '%');
                }
                if (isset($filtros['es_heredado']) && $filtros['es_heredado'] !== '') {
                    $q->where('es_heredado', filter_var($filtros['es_heredado'], FILTER_VALIDATE_BOOLEAN));
                }
            });
        }

        if (!empty($filtros['motivo_incorrecta'])) {
            $this->aplicarFiltroMotivoIncidencia($query, $filtros['motivo_incorrecta']);
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
                    });
            }),
            'RESPONDIDAS' => $query->where('catalogo_estado_solicitud_id', 2),
            'INCORRECTAS' => $query->where('catalogo_estado_solicitud_id', 4),
            'CANCELADAS' => $idCancelada
                ? $query->where('catalogo_estado_solicitud_id', $idCancelada)
                : $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    private function aplicarFiltroMotivoIncidencia(Builder $query, string $motivo): void
    {
        $query->where('catalogo_estado_solicitud_id', 4);

        match ($motivo) {
            'vencimiento_pago' => $query->where(function (Builder $q) {
                $q->where('motivo_incorrecta', 'vencimiento_pago')
                    ->orWhereHas('auditorias', function (Builder $aq) {
                        $aq->where('motivo_reporte', 'like', '%Plazo de pago expirado%')
                            ->orWhere('motivo_reporte', 'like', '%PAGO RECHAZADO%');
                    });
            }),
            'pago_insuficiente' => $query->where(function (Builder $q) {
                $q->where('motivo_incorrecta', 'pago_insuficiente')
                    ->orWhereHas('auditorias', function (Builder $aq) {
                        $aq->where('motivo_reporte', 'like', '%ALERTA DE PAGO%');
                    });
            }),
            'error_reportado' => $query->where(function (Builder $q) {
                $q->where('motivo_incorrecta', 'error_reportado')
                    ->orWhereNull('motivo_incorrecta')
                    ->orWhere(function (Builder $sub) {
                        $sub->whereNotIn('motivo_incorrecta', ['vencimiento_pago', 'pago_insuficiente'])
                            ->whereHas('auditorias', function (Builder $aq) {
                                $aq->where('estado_nuevo_id', 4)
                                    ->where(function (Builder $mq) {
                                        $mq->where('motivo_reporte', 'like', '%error%')
                                            ->orWhere('motivo_reporte', 'like', '%Reporte%')
                                            ->orWhere('motivo_reporte', 'like', '%CAMBIO DE ESTADO%');
                                    });
                            });
                    });
            }),
            default => $query->where('motivo_incorrecta', $motivo),
        };
    }
}
