<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListarSolicitudesService
{
    public function resumenFiltrado(?User $usuario, array $filtros = []): array
    {
        $query = SolicitudTag::query()->whereHas('proceso', fn (Builder $q) =>
            $q->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO));
        if ($usuario) $this->aplicarAislamientoDeDatos($query, $usuario);
        $this->aplicarFiltros($query, $filtros, $usuario);

        $respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $verificada = (int) CatalogoEstadoSolicitud::idDe('Verificada');
        $incorrecta = (int) CatalogoEstadoSolicitud::idDe('Incorrecta');
        $select = "COUNT(*) AS solicitudes, COUNT(DISTINCT cliente_id) AS clientes,
            SUM(CASE WHEN catalogo_estado_solicitud_id IN ($respondida, $verificada)
                AND cancelacion_solicitada_at IS NULL AND rollback_confirmado_at IS NULL THEN 1 ELSE 0 END) AS vigentes,
            SUM(CASE WHEN catalogo_estado_solicitud_id = $incorrecta THEN 1 ELSE 0 END) AS errores";
        $normalizar = fn ($fila) => collect(['solicitudes', 'clientes', 'vigentes', 'errores'])
            ->mapWithKeys(fn ($key) => [$key => (int) $fila->$key])->all();
        $totales = (clone $query)->selectRaw($select)->first();
        $tipos = (clone $query)->select('catalogo_tipo_cliente_id')->selectRaw($select)
            ->with('tipoCliente')->groupBy('catalogo_tipo_cliente_id')->get()
            ->map(fn ($fila) => array_merge($normalizar($fila), [
                'id' => $fila->catalogo_tipo_cliente_id,
                'nombre' => $fila->tipoCliente?->nombre ?? 'Sin tipo registrado',
            ]))->sortBy('nombre')->values()->all();

        return array_merge($normalizar($totales), ['tipos' => $tipos]);
    }

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
            'auditorias.estadoAnterior',
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

        $this->aplicarFiltros($query, $filtros, $usuario);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(?User $usuario): array
    {
        $query = SolicitudTag::query()
            ->whereHas('proceso', function (Builder $proceso) {
                $proceso->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO);
            });

        if ($usuario) {
            $this->aplicarAislamientoDeDatos($query, $usuario);
        }

        $hoy = now()->toDateString();
        $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $idCancelada = CatalogoEstadoSolicitud::idDe('Cancelada');

        return self::empaquetarMetricas(
            (clone $query)->where(function (Builder $q) use ($idPendiente, $idCancelada) {
                $q->where('catalogo_estado_solicitud_id', $idPendiente)
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
            $idRespondida
                ? (clone $query)->where('catalogo_estado_solicitud_id', $idRespondida)
                    ->whereDate('updated_at', $hoy)->count()
                : 0,
            $idIncorrecta
                ? (clone $query)->where('catalogo_estado_solicitud_id', $idIncorrecta)->count()
                : 0,
        );
    }

    /** Contrato de claves para widgets/dashboard (checkable sin DB). */
    public static function empaquetarMetricas(int $pendientes, int $respondidasHoy, int $incorrectas): array
    {
        return [
            'pendientes' => $pendientes,
            'respondidas_hoy' => $respondidasHoy,
            'incorrectas' => $incorrectas,
        ];
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
        $usuario->loadMissing('area');
        $departamentosUsuario = $usuario->departamentos->pluck('id')->toArray();

        if (empty($departamentosUsuario) && $usuario->area?->departamento_id) {
            $departamentosUsuario = [$usuario->area->departamento_id];
        }

        if (!empty($departamentosUsuario)) {
            $query->where(function (Builder $q) use ($departamentosUsuario, $usuario) {
                $q->whereIn('departamento_id', $departamentosUsuario)
                    ->orWhere('vendedor_id', $usuario->id);
            });
            return;
        }

        $query->where('vendedor_id', $usuario->id);
    }

    private function aplicarFiltros(Builder $query, array $filtros, ?User $usuario): void
    {
        if (!empty($filtros['tab']) && $filtros['tab'] !== 'TODAS') {
            $this->aplicarFiltroTab($query, $filtros['tab'], $usuario);
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

        if (!empty($filtros['lista_id'])) {
            $query->where('catalogo_lista_descuento_id', $filtros['lista_id']);
        }
        if (!empty($filtros['tipo_cliente_id'])) {
            $filtros['tipo_cliente_id'] === 'SIN_TIPO'
                ? $query->whereNull('catalogo_tipo_cliente_id')
                : $query->where('catalogo_tipo_cliente_id', $filtros['tipo_cliente_id']);
        }
        if (in_array($filtros['tag'] ?? '', ['con_tag', 'sin_tag'], true)) {
            $query->whereHas('cliente', fn (Builder $q) => $filtros['tag'] === 'con_tag'
                ? $q->whereNotNull('vendedor_id') : $q->whereNull('vendedor_id'));
        }

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_inicio'])
                ->whereDate('created_at', '<=', $filtros['fecha_fin']);
        } elseif (!empty($filtros['fecha_inicio'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_inicio']);
        } elseif (!empty($filtros['fecha_fin'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_fin']);
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

    private function aplicarFiltroTab(Builder $query, string $tab, ?User $usuario): void
    {
        $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $idCancelada = CatalogoEstadoSolicitud::idDe('Cancelada');

        match ($tab) {
            'PENDIENTES' => $query->where(function (Builder $q) use ($idPendiente, $idCancelada) {
                $q->where('catalogo_estado_solicitud_id', $idPendiente)
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
            'RESPONDIDAS' => $query->where('catalogo_estado_solicitud_id', $idRespondida),
            'VIGENTES' => $query->whereIn('catalogo_estado_solicitud_id', array_filter([
                $idRespondida, CatalogoEstadoSolicitud::idDe('Verificada'),
            ]))->whereNull('cancelacion_solicitada_at')->whereNull('rollback_confirmado_at'),
            'INCORRECTAS' => $query->where('catalogo_estado_solicitud_id', $idIncorrecta),
            'CANCELADAS' => $idCancelada
                ? $query->where('catalogo_estado_solicitud_id', $idCancelada)
                : $query->whereRaw('1 = 0'),
            'ELIMINADAS' => ($usuario && $usuario->can('solicitudes.eliminadas'))
                ? $query->onlyTrashed()
                : $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    private function aplicarFiltroMotivoIncidencia(Builder $query, string $motivo): void
    {
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $query->where('catalogo_estado_solicitud_id', $idIncorrecta);

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
            'error_reportado' => $query->where(function (Builder $q) use ($idIncorrecta) {
                $q->where('motivo_incorrecta', 'error_reportado')
                    ->orWhereNull('motivo_incorrecta')
                    ->orWhere(function (Builder $sub) use ($idIncorrecta) {
                        $sub->whereNotIn('motivo_incorrecta', ['vencimiento_pago', 'pago_insuficiente'])
                            ->whereHas('auditorias', function (Builder $aq) use ($idIncorrecta) {
                                $aq->where('estado_nuevo_id', $idIncorrecta)
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
