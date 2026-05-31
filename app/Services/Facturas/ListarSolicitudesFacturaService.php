<?php

namespace App\Services\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudFactura;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListarSolicitudesFacturaService
{
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        $query = SolicitudFactura::with([
            'vendedor:id,name',
            'departamento:id,nombre',
            'estado:id,nombre',
            'cliente:id,numero_cliente,nombre,rfc',
            'vouchers:id,solicitud_factura_id,path,nombre_original,mime,orden',
        ])->orderByDesc('created_at');

        if ($usuario) {
            $this->aplicarAislamiento($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    public function metricas(?User $usuario): array
    {
        $query = SolicitudFactura::query();
        if ($usuario) {
            $this->aplicarAislamiento($query, $usuario);
        }

        $hoy = now()->toDateString();
        $idRespondida = CatalogoEstadoSolicitud::where('nombre', 'Respondida')->value('id');

        return [
            'pendientes' => (clone $query)->where('catalogo_estado_solicitud_id', 1)->count(),
            'respondidas_hoy' => (clone $query)->where('catalogo_estado_solicitud_id', $idRespondida)
                ->whereDate('respondida_at', $hoy)->count(),
            'incorrectas' => (clone $query)->where('catalogo_estado_solicitud_id', 4)->count(),
        ];
    }

    public function usuarioPuedeVer(User $usuario, SolicitudFactura $solicitud): bool
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return true;
        }

        if ($solicitud->vendedor_id === $usuario->id) {
            return true;
        }

        $tieneVisibilidadArea = $usuario->hasRole('Gerente') ||
            $usuario->hasAnyPermission(['facturas.verificar', 'facturas.responder']);

        if ($tieneVisibilidadArea) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();

            return !empty($departamentos) && in_array($solicitud->departamento_id, $departamentos, true);
        }

        return false;
    }

    private function aplicarAislamiento(Builder $query, User $usuario): void
    {
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return;
        }

        $tieneVisibilidadArea = $usuario->hasRole('Gerente') ||
            $usuario->hasAnyPermission(['facturas.verificar', 'facturas.responder']);

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
            match ($filtros['tab']) {
                'PENDIENTES' => $query->where('catalogo_estado_solicitud_id', 1),
                'RESPONDIDAS' => $query->where('catalogo_estado_solicitud_id', 2),
                'VERIFICADAS' => $query->where('catalogo_estado_solicitud_id', 3),
                'INCORRECTAS' => $query->where('catalogo_estado_solicitud_id', 4),
                'CANCELADAS' => $query->whereHas('estado', fn ($q) => $q->where('nombre', 'Cancelada')),
                default => null,
            };
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
            $terminoRfc = strtoupper(preg_replace('/\s+/', '', $termino));
            $like = '%' . addcslashes($termino, '%_\\') . '%';
            $likeRfc = '%' . addcslashes($terminoRfc, '%_\\') . '%';

            $query->where(function (Builder $q) use ($termino, $terminoRfc, $like, $likeRfc) {
                $q->where('folio', 'like', $like)
                    ->orWhere('razon_social', 'like', $like)
                    ->orWhere('datos_fiscales->rfc', 'like', $likeRfc);

                if ($terminoRfc !== $termino) {
                    $q->orWhere('datos_fiscales->rfc', 'like', $like);
                }

                $q->orWhereHas('cliente', function (Builder $cliente) use ($like, $likeRfc, $termino, $terminoRfc) {
                    $cliente->where('rfc', 'like', $likeRfc);
                    if ($terminoRfc !== $termino) {
                        $cliente->orWhere('rfc', 'like', $like);
                    }
                });
            });
        }
    }
}
