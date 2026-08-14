<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Database\Eloquent\Builder;

class ListarPedidosAuditoriaService
{
    private const FASES_AUDITORIA = [
        CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
        CatalogoEstatusPedido::FASE_EN_CEDIS,
        CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_ENTREGADO,
        CatalogoEstatusPedido::FASE_ENVIADO,
    ];

    public function ejecutar(array $filtros = [], bool $paginar = true)
    {
        $query = $this->queryBase();
        $this->aplicarFiltros($query, $filtros);

        if (! $paginar) {
            return $query->get()->each(fn (PedidoBma $p) => $this->anexarFlagsVista($p));
        }

        return $query->paginate(15)->withQueryString()->through(
            fn (PedidoBma $p) => $this->anexarFlagsVista($p)
        );
    }

    private function anexarFlagsVista(PedidoBma $pedido): PedidoBma
    {
        $hito = MaquinaEstadosPedidoBma::hitoAuditoria($pedido);
        $pedido->setAttribute('pendiente_re_revision', $this->esPendienteReRevision($pedido));
        $pedido->setAttribute('hito_auditoria', $hito);
        $pedido->setAttribute('hito_auditoria_etiqueta', MaquinaEstadosPedidoBma::etiquetaHito($hito));
        $pedido->setAttribute('fuentes_pago', $pedido->fuentesPagoResumen());
        $incidenciasSaf = $pedido->relationLoaded('safIncidencias')
            ? $pedido->safIncidencias
            : collect();
        $pedido->setAttribute('saf_incidencias_abiertas', $incidenciasSaf->values());
        $pedido->setAttribute('tiene_alerta_saf', $incidenciasSaf->isNotEmpty());

        return $pedido;
    }

    /**
     * Volvió a PENDIENTE_AUXILIAR tras un rechazo o reporte (no el primer envío desde borrador).
     */
    private function esPendienteReRevision(PedidoBma $pedido): bool
    {
        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR) {
            return false;
        }

        $ultimaEntrada = $pedido->historial
            ->filter(fn ($h) => $h->estatusNuevo?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)
            ->sortByDesc('id')
            ->first();

        $faseAnterior = $ultimaEntrada?->estatusAnterior?->fase_ciclo;

        return $faseAnterior !== null
            && $faseAnterior !== CatalogoEstatusPedido::FASE_BORRADOR;
    }

    public function metricas(): array
    {
        $base = $this->queryBase();
        $idsPorFase = $this->idsPorFase();

        $pendientes = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0)->count();
        $aprobados = (clone $base)->whereIn('catalogo_estatus_pedido_id', array_filter([
            $idsPorFase['EN_CEDIS'] ?? null,
            $idsPorFase['INCIDENCIA_CEDIS'] ?? null,
            $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
            $idsPorFase['PENDIENTE_GUIA_CLIENTE'] ?? null,
            $idsPorFase['PENDIENTE_DE_ENVIO'] ?? null,
            $idsPorFase['ENTREGADO'] ?? null,
            $idsPorFase['ENVIADO'] ?? null,
        ]))->count();
        $rechazados = (clone $base)->where('catalogo_estatus_pedido_id', $idsPorFase['RECHAZADO_VENDEDORA'] ?? 0)->count();
        $resguardos = (clone $base)->where('es_resguardo', true)->count();
        $envioPendiente = (clone $base)->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION)->count();
        $pendienteLiberacion = (clone $base)->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION)->count();
        $anexoPorVerificar = (clone $base)->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO)->count();
        $anexoRechazado = (clone $base)->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_ANEXO_RECHAZADO)->count();
        $consolidados = (clone $base)->whereNull('pedido_principal_id')->whereHas('complementos')->count();
        $pendienteId = $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0;
        $pagoEnRevision = (clone $base)->where('catalogo_estatus_pedido_id', $pendienteId)->whereNull('pago_validado_at')->count();
        $pendienteRemision = (clone $base)->where('catalogo_estatus_pedido_id', $pendienteId)
            ->whereNotNull('pago_validado_at')->whereDoesntHave('remision')->count();
        $pagoValidado = (clone $base)->where('catalogo_estatus_pedido_id', $pendienteId)
            ->whereNotNull('pago_validado_at')->whereHas('remision')->count();

        return [
            'pendientes' => $pendientes,
            'pago_en_revision' => $pagoEnRevision,
            'pendiente_remision' => $pendienteRemision,
            'pago_validado' => $pagoValidado,
            'aprobados' => $aprobados,
            'rechazados' => $rechazados,
            'resguardos' => $resguardos,
            'envio_pendiente' => $envioPendiente,
            'pendiente_liberacion' => $pendienteLiberacion,
            'anexo_por_verificar' => $anexoPorVerificar,
            'anexo_rechazado' => $anexoRechazado,
            'consolidados' => $consolidados,
            'total' => $pendientes + $aprobados + $rechazados,
        ];
    }

    private function queryBase(): Builder
    {
        $idsPorFase = $this->idsPorFase();
        $idsVisibles = array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            self::FASES_AUDITORIA
        )));

        return PedidoBma::with([
            'cliente',
            'vendedor.departamento:id,nombre',
            'vendedor.departamentos:id,nombre',
            'estatus',
            'origen',
            'tipoOperacionEnvio',
            'anexosEnvio.banco',
            'anexosEnvio.registradoPor',
            'anexosEnvio.validadoPor',
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
            'pagoValidadoPor',
            'incidenciaEmpaquePor',
            'direccionVigente',
            'historial.usuario',
            'historial.estatusAnterior',
            'historial.estatusNuevo',
            'errores.reportadoPor',
            'errores.corregidoPor',
            'principal',
            'complementos',
            'safAplicaciones.credito',
            'pagosExhibicion.banco',
            'safIncidencias' => fn ($q) => $q->where('estado', \App\Models\SaldosAFavor\SafIncidencia::ESTADO_ABIERTA)->orderByDesc('id'),
        ])
            ->whereIn('catalogo_estatus_pedido_id', $idsVisibles ?: [0])
            ->orderByDesc('created_at');
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
            'PENDIENTES' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0),
            'PAGO_EN_REVISION' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0)
                ->whereNull('pago_validado_at'),
            'PENDIENTE_REMISION' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0)
                ->whereNotNull('pago_validado_at')
                ->whereDoesntHave('remision'),
            'PAGO_VALIDADO' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0)
                ->whereNotNull('pago_validado_at')
                ->whereHas('remision'),
            'APROBADOS' => $query->whereIn('catalogo_estatus_pedido_id', array_filter([
                $idsPorFase['EN_CEDIS'] ?? null,
                $idsPorFase['INCIDENCIA_CEDIS'] ?? null,
                $idsPorFase['PENDIENTE_DE_GUIA'] ?? null,
                $idsPorFase['PENDIENTE_GUIA_CLIENTE'] ?? null,
                $idsPorFase['PENDIENTE_DE_ENVIO'] ?? null,
                $idsPorFase['ENTREGADO'] ?? null,
                $idsPorFase['ENVIADO'] ?? null,
            ])),
            'RECHAZADOS' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['RECHAZADO_VENDEDORA'] ?? 0),
            'RESGUARDOS' => $query->where('es_resguardo', true),
            'ENVIO_PENDIENTE' => $query->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION),
            'PENDIENTE_LIBERACION' => $query->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION),
            'ANEXO_POR_VERIFICAR' => $query->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO),
            'ANEXO_RECHAZADO' => $query->where('estatus_envio', PedidoBma::ESTATUS_ENVIO_ANEXO_RECHAZADO),
            'CONSOLIDADOS' => $query->whereNull('pedido_principal_id')->whereHas('complementos'),
            default => null,
        };
    }

    private function idsPorFase(): array
    {
        return CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', self::FASES_AUDITORIA)
            ->pluck('id', 'fase_ciclo')
            ->all();
    }
}
