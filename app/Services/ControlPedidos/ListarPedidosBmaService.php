<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
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
            'tipoOperacionEnvio',
            'anexosEnvio.banco',
            'anexosEnvio.registradoPor',
            'envioTienda',
            'almacen',
            'banco',
            'tipoCaja',
            'cajas.tipoCaja', 'cajas.tipoGuia',
            'paqueteria',
            'tipoGuia',
            'zona',
            'documentos',
            'revisionesProducto',
            'direccionVigente',
            'historial.usuario',
            'historial.estatusAnterior',
            'historial.estatusNuevo',
            'complementos',
            'safAplicaciones.credito',
            'pagosExhibicion.banco',
        ])->orderByDesc('created_at');

        if ($usuario) {
            VisibilidadPedidoBma::aplicarAlcanceListadoBma($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros);

        if (! $paginar) {
            return $query->get()->each(fn (PedidoBma $p) => $this->anexarFlagsVista($p, $usuario));
        }

        return $query->paginate(15)->withQueryString()->through(
            fn (PedidoBma $p) => $this->anexarFlagsVista($p, $usuario)
        );
    }

    public function metricas(?User $usuario): array
    {
        $base = PedidoBma::query();
        if ($usuario) {
            VisibilidadPedidoBma::aplicarAlcanceListadoBma($base, $usuario);
        }

        $idsPorFase = $this->idsPorFase();

        return [
            'todas' => (clone $base)->count(),
            'borradores' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['BORRADOR'] ?? 0)->count(),
            'pesaje_pendiente' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PESAJE_PENDIENTE'] ?? 0)->count(),
            'pesaje_respondido' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PESAJE_RESPONDIDO'] ?? 0)->count(),
            'pendiente_auxiliar' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0)->count(),
            'en_cedis' => (clone $base)->whereIn('catalogo_estatus_pedido_id', array_filter([
                $idsPorFase['EN_CEDIS'] ?? null,
                $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
            ]))->count(),
            'pendiente_guia_cliente' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_GUIA_CLIENTE'] ?? 0)->count(),
            'enviados' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['ENVIADO'] ?? 0)->count(),
            'rechazadas' => (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['RECHAZADO_VENDEDORA'] ?? 0)->count(),
            'obs_cedis' => (clone $base)->where('tiene_observaciones_fisicas', true)->count(),
            'sin_existencia' => (clone $base)->whereHas('revisionesProducto', fn ($q) => $q->sinExistenciaAbierta())->count(),
        ];
    }

    private function anexarFlagsVista(PedidoBma $pedido, ?User $usuario): PedidoBma
    {
        $puedeEditar = $usuario
            ? VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)
            : false;

        $pedido->setAttribute('puede_editar', $puedeEditar);
        $pedido->setAttribute('puede_mutar', $puedeEditar);
        $pedido->setAttribute('puede_cancelar', $pedido->puedeCancelarDirecto());
        $pedido->setAttribute('tiene_sin_existencia_abierta', $pedido->tieneSinExistenciaAbierta());
        $pedido->setAttribute('fuentes_pago', $pedido->fuentesPagoResumen());
        $pedido->setAttribute('pendiente_re_revision', MaquinaEstadosPedidoBma::esPendienteReRevision($pedido));

        return $pedido;
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
            'PESAJE_PENDIENTE' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PESAJE_PENDIENTE'] ?? 0),
            'PESAJE_RESPONDIDO' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PESAJE_RESPONDIDO'] ?? 0),
            'OBS_CEDIS' => $query->where('tiene_observaciones_fisicas', true),
            'SIN_EXISTENCIA' => $query->whereHas('revisionesProducto', fn ($q) => $q->sinExistenciaAbierta()),
            'PENDIENTE_AUXILIAR' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0),
            'EN_CEDIS' => $query->whereIn('catalogo_estatus_pedido_id', array_filter([
                $idsPorFase['EN_CEDIS'] ?? null,
                $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
            ])),
            'PENDIENTE_GUIA_CLIENTE' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_GUIA_CLIENTE'] ?? 0),
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
                'PESAJE_PENDIENTE',
                'PESAJE_RESPONDIDO',
                'PENDIENTE_AUXILIAR',
                'EN_CEDIS',
                'PENDIENTE_DE_GUIA',
                'PENDIENTE_GUIA_CLIENTE',
                'ENVIADO',
                'RECHAZADO_VENDEDORA',
            ])
            ->pluck('id', 'fase_ciclo')
            ->all();
    }

    /** Mutaciones de vendedora: solo el creador (o admin). */
    public function asegurarAcceso(PedidoBma $pedido, User $usuario): void
    {
        VisibilidadPedidoBma::assertPuedeMutarComoVendedora($usuario, $pedido);
    }

    /** Consulta: propios, equipo/departamento, o bandeja por permiso. */
    public function asegurarConsulta(PedidoBma $pedido, User $usuario): void
    {
        VisibilidadPedidoBma::assertPuedeConsultar($usuario, $pedido);
    }
}
