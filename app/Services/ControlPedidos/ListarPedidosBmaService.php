<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\RevisionEnCursoPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Database\Eloquent\Builder;

class ListarPedidosBmaService
{
    public function __construct(
        private CalcularProgresoPedidoBmaService $progreso,
    ) {}

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
            'pesajeRespondidoPor:id,name',
            'direccionVigente',
            'historial.usuario',
            'historial.estatusAnterior',
            'historial.estatusNuevo',
            'complementos',
            'safAplicaciones.credito',
            'pagosExhibicion.banco',
            'tareaPreparacionVigente.modalidad',
            'tareaPreparacionVigente.almacen',
            'tareaPreparacionVigente.productos',
            'tareaPreparacionVigente.historial',
            'tareaPreparacionVigente.paqueteria',
            'tareaPreparacionVigente.caratulas',
            'tareaPreparacionRespondida.modalidad',
            'tareaPreparacionRespondida.almacen',
            'tareaPreparacionRespondida.productos',
            'tareaPreparacionRespondida.paqueteria',
            'tareaPreparacionRespondida.caratulas',
        ])->orderByDesc('created_at');

        if ($usuario) {
            VisibilidadPedidoBma::aplicarAlcanceListadoBma($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros, $usuario);

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
            'eliminadas' => $usuario?->can('control_pedidos.eliminados')
                ? (clone $base)->onlyTrashed()->whereNotNull('eliminacion_registro_at')->count()
                : 0,
        ];
    }

    private function anexarFlagsVista(PedidoBma $pedido, ?User $usuario): PedidoBma
    {
        $puedeEditar = $usuario
            ? VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)
            : false;

        $pedido->setAttribute('puede_editar', $puedeEditar);
        $pedido->setAttribute('puede_mutar', $puedeEditar);
        $pedido->setAttribute('puede_cancelar', $puedeEditar && $pedido->puedeCancelarDirecto());
        $pedido->setAttribute('tiene_sin_existencia_abierta', $pedido->tieneSinExistenciaAbierta());
        $pedido->setAttribute('consulta_cerrada', $pedido->consultaCerrada());
        $pedido->setAttribute('puede_cerrar_consulta', $puedeEditar && $pedido->puedeCerrarConsulta());
        $pedido->setAttribute('es_consulta_mercancia', $pedido->esConsultaMercancia());
        $pedido->setAttribute('fuentes_pago', $pedido->fuentesPagoResumen());
        $pedido->setAttribute('pendiente_re_revision', MaquinaEstadosPedidoBma::esPendienteReRevision($pedido));
        $pedido->setAttribute('en_revision_ahora', RevisionEnCursoPedidoBma::activa($pedido->id));
        $pedido->setAttribute('progreso', $this->progreso->calcular($pedido));
        $pedido->setAttribute('usa_preparacion_tienda', $pedido->usaPreparacionTienda());
        $pedido->setAttribute('tiene_traslado_cedis_pendiente', $pedido->tieneTrasladoCedisPendiente());
        $pedido->setAttribute('tiene_caratula_municipal_pendiente', $pedido->tieneCaratulaMunicipalPendiente());
        $pedido->setAttribute('tarea_preparacion', $this->serializarTareaPreparacion(
            $this->resolverTareaPreparacionVista($pedido)
        ));

        return $pedido;
    }

    private function resolverTareaPreparacionVista(PedidoBma $pedido): ?PedidoBmaTareaPreparacion
    {
        if ($pedido->relationLoaded('tareaPreparacionVigente') && $pedido->tareaPreparacionVigente) {
            return $pedido->tareaPreparacionVigente;
        }

        if ($pedido->relationLoaded('tareaPreparacionRespondida') && $pedido->tareaPreparacionRespondida) {
            return $pedido->tareaPreparacionRespondida;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializarTareaPreparacion(?PedidoBmaTareaPreparacion $tarea): ?array
    {
        if (! $tarea) {
            return null;
        }

        $incidencia = null;
        if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA) {
            $hist = $tarea->relationLoaded('historial')
                ? $tarea->historial->where('accion', 'incidencia')->sortByDesc('id')->first()
                : $tarea->historial()->where('accion', 'incidencia')->latest('id')->first();
            $incidencia = $hist?->meta_json;
        }

        return [
            'id' => $tarea->id,
            'estado' => $tarea->estado,
            'estado_label' => PedidoBmaTareaPreparacion::LABELS[$tarea->estado] ?? $tarea->estado,
            'version' => $tarea->version,
            'observaciones_solicitud' => $tarea->observaciones_solicitud,
            'observaciones_respuesta' => $tarea->observaciones_respuesta,
            'fecha_limite' => $tarea->fecha_limite?->toIso8601String(),
            'modalidad' => $tarea->modalidad ? [
                'id' => $tarea->modalidad->id,
                'codigo' => $tarea->modalidad->codigo,
                'nombre' => $tarea->modalidad->nombre,
            ] : null,
            'almacen' => $tarea->almacen ? [
                'id' => $tarea->almacen->id,
                'nombre' => $tarea->almacen->nombre,
                'codigo' => $tarea->almacen->codigo ?? null,
            ] : null,
            'productos' => $tarea->productos->map(fn ($p) => [
                'id' => $p->id,
                'descripcion_snapshot' => $p->descripcion_snapshot,
                'sku' => $p->sku,
                'producto_id' => $p->producto_id,
                'cantidad_solicitada' => $p->cantidad_solicitada,
                'cantidad_encontrada' => $p->cantidad_encontrada,
                'estado_fisico' => $p->estado_fisico,
                'observacion' => $p->observacion,
            ])->values()->all(),
            'incidencia' => $incidencia,
            'requiere_traslado_cedis' => (bool) $tarea->requiere_traslado_cedis,
            'progreso_traslado' => \App\Support\ControlPedidos\VisibilidadTareaPreparacion::progresoTraslado($tarea),
            'progreso_caratula' => \App\Support\ControlPedidos\VisibilidadTareaPreparacion::progresoCaratula($tarea),
            'motivo_rechazo_cedis' => $tarea->motivo_rechazo_cedis,
            'enviada_cedis_at' => $tarea->enviada_cedis_at?->toIso8601String(),
            'recibida_cedis_at' => $tarea->recibida_cedis_at?->toIso8601String(),
            'entrega_municipal' => $tarea->modalidad?->esEnvioMunicipio() ? [
                'destinatario_nombre' => $tarea->destinatario_nombre,
                'municipio_destino' => $tarea->municipio_destino,
                'modalidad_cobro' => $tarea->modalidad_cobro,
                'transporte' => $tarea->paqueteria?->nombre,
            ] : null,
            'caratula' => ($c = $tarea->caratulaVigente()) ? [
                'id' => $c->id,
                'version' => $c->version,
                'estado' => $c->estado,
            ] : null,
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros, ?User $usuario = null): void
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

        if ($tab === 'ELIMINADAS') {
            if (! $usuario?->can('control_pedidos.eliminados')) {
                $query->whereRaw('0 = 1');

                return;
            }

            $query->onlyTrashed()
                ->whereNotNull('eliminacion_registro_at')
                ->with(['eliminacionRegistroPor:id,name', 'auditoriasRegistro.usuario:id,name']);

            return;
        }

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

    /** Mutaciones de vendedora: solo el creador. */
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
