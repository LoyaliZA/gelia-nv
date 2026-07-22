<?php

namespace App\Services\Traspasos;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListarSolicitudesTraspasoService
{
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        $query = SolicitudTraspaso::with([
            'vendedor:id,name',
            'departamento:id,nombre',
            'estado:id,nombre',
            'cliente:id,numero_cliente,nombre',
            'almacenOrigen:id,codigo,nombre',
            'horario:id,nombre,dias_para_entrega,descripcion',
            'respondidaPor:id,name',
            'productos:id,solicitud_traspaso_id,producto_id,sku,descripcion,piezas',
            'auditorias.usuario:id,name',
            'auditorias.estadoNuevo:id,nombre',
            'auditorias.estadoAnterior:id,nombre',
        ])->orderByDesc('created_at');

        if ($usuario) {
            $this->aplicarAislamiento($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(?User $usuario): array
    {
        $query = SolicitudTraspaso::query();
        if ($usuario) {
            $this->aplicarAislamiento($query, $usuario);
        }

        $hoy = now()->toDateString();
        $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');

        return [
            'pendientes' => $idPendiente ? (clone $query)->where('catalogo_estado_solicitud_id', $idPendiente)->count() : 0,
            'respondidas_hoy' => $idRespondida
                ? (clone $query)->where('catalogo_estado_solicitud_id', $idRespondida)
                    ->whereDate('respondida_at', $hoy)->count()
                : 0,
            'incorrectas' => $idIncorrecta ? (clone $query)->where('catalogo_estado_solicitud_id', $idIncorrecta)->count() : 0,
            'entrega_manana' => (clone $query)->whereDate('fecha_entrega_estimada', now()->addDay()->toDateString())->count(),
        ];
    }

    public function usuarioPuedeVer(User $usuario, SolicitudTraspaso $solicitud): bool
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return true;
        }

        if ($solicitud->vendedor_id === $usuario->id) {
            return true;
        }

        if ($usuario->hasPermissionTo('traspasos.monitorear_alertas')) {
            return true;
        }

        $tieneVisibilidadArea = $usuario->hasRole('Gerente') ||
            $usuario->hasAnyPermission(['traspasos.verificar', 'traspasos.responder']);

        if ($tieneVisibilidadArea) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();

            return ! empty($departamentos) && in_array($solicitud->departamento_id, $departamentos, true);
        }

        return false;
    }

    private function aplicarAislamiento(Builder $query, User $usuario): void
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return;
        }

        if ($usuario->hasPermissionTo('traspasos.monitorear_alertas')) {
            return;
        }

        $tieneVisibilidadArea = $usuario->hasRole('Gerente') ||
            $usuario->hasAnyPermission(['traspasos.verificar', 'traspasos.responder']);

        if ($tieneVisibilidadArea) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();
            if (! empty($departamentos)) {
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
        if (! empty($filtros['tab']) && $filtros['tab'] !== 'TODAS') {
            match ($filtros['tab']) {
                'PENDIENTES' => $this->filtrarPorEstado($query, 'Pendiente'),
                'RESPONDIDAS' => $this->filtrarPorEstado($query, 'Respondida'),
                'VERIFICADAS' => $this->filtrarPorEstado($query, 'Verificada'),
                'INCORRECTAS' => $this->filtrarPorEstado($query, 'Incorrecta'),
                'ENTREGA_MANANA' => $query->whereDate('fecha_entrega_estimada', now()->addDay()->toDateString()),
                default => null,
            };
        }

        if (! empty($filtros['vendedor_id'])) {
            $query->where('vendedor_id', $filtros['vendedor_id']);
        }

        if (! empty($filtros['almacen_origen_id'])) {
            $query->where('almacen_origen_id', $filtros['almacen_origen_id']);
        }

        if (! empty($filtros['fecha_inicio']) && ! empty($filtros['fecha_fin'])) {
            $query->whereBetween('created_at', [
                $filtros['fecha_inicio'] . ' 00:00:00',
                $filtros['fecha_fin'] . ' 23:59:59',
            ]);
        } elseif (! empty($filtros['fecha_inicio'])) {
            $query->where('created_at', '>=', $filtros['fecha_inicio'] . ' 00:00:00');
        } elseif (! empty($filtros['fecha_fin'])) {
            $query->where('created_at', '<=', $filtros['fecha_fin'] . ' 23:59:59');
        }

        if (! empty($filtros['fecha_entrega'])) {
            $query->whereDate('fecha_entrega_estimada', $filtros['fecha_entrega']);
        }

        if (! empty($filtros['q']) || ! empty($filtros['folio'])) {
            $termino = trim($filtros['q'] ?? $filtros['folio'] ?? '');
            $like = '%' . addcslashes($termino, '%_\\') . '%';

            $query->where(function (Builder $q) use ($like, $termino) {
                $q->where('folio', 'like', $like)
                    ->orWhere('folio_traspaso', 'like', $like)
                    ->orWhereHas('cliente', function (Builder $cliente) use ($like) {
                        $cliente->where('numero_cliente', 'like', $like)
                            ->orWhere('nombre', 'like', $like);
                    })
                    ->orWhereHas('vendedor', fn (Builder $v) => $v->where('name', 'like', $like));

                if (ctype_digit($termino)) {
                    $q->orWhere('id', (int) $termino);
                }
            });
        }
    }

    private function filtrarPorEstado(Builder $query, string $nombreEstado): void
    {
        $estadoId = CatalogoEstadoSolicitud::idDe($nombreEstado);
        if ($estadoId) {
            $query->where('catalogo_estado_solicitud_id', $estadoId);
        } else {
            $query->whereRaw('1 = 0');
        }
    }
}
