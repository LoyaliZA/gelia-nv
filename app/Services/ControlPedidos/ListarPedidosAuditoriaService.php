<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\RevisionEnCursoPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
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

    public function ejecutar(array $filtros = [], bool $paginar = true, ?User $usuario = null)
    {
        $query = $this->queryBase($usuario);
        $this->aplicarFiltros($query, $filtros);
        $this->aplicarOrden($query, $filtros);

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
        $pedido->setAttribute('pendiente_re_revision', MaquinaEstadosPedidoBma::esPendienteReRevision($pedido));
        $pedido->setAttribute('en_revision_ahora', RevisionEnCursoPedidoBma::activa($pedido->id));
        $pedido->setAttribute('hito_auditoria', $hito);
        $pedido->setAttribute('hito_auditoria_etiqueta', MaquinaEstadosPedidoBma::etiquetaHito($hito));
        $pedido->setAttribute('fuentes_pago', $pedido->fuentesPagoResumen());
        $incidenciasSaf = $pedido->relationLoaded('safIncidencias')
            ? $pedido->safIncidencias
            : collect();
        $pedido->setAttribute('saf_incidencias_abiertas', $incidenciasSaf->values());
        $pedido->setAttribute('tiene_alerta_saf', $incidenciasSaf->isNotEmpty());

        // Cobertura compacta para bandeja: suma exhibiciones ya cargadas (sin CoberturaPago N veces).
        $pagos = $pedido->relationLoaded('pagosExhibicion')
            ? $pedido->pagosExhibicion
            : collect();
        $pagadoCentavos = 0;
        foreach ($pagos as $pago) {
            if (! $pago->activo_para_cobertura) {
                continue;
            }
            $pagadoCentavos += (int) round(((float) $pago->monto) * 100);
        }
        $totalCobrarCentavos = (int) round(((float) ($pedido->total_a_cobrar ?? 0)) * 100);
        $pedido->setAttribute('pagado_valido', number_format($pagadoCentavos / 100, 2, '.', ''));
        $pedido->setAttribute('diferencia_cobertura', number_format(($totalCobrarCentavos - $pagadoCentavos) / 100, 2, '.', ''));

        return $pedido;
    }

    public function metricas(?User $usuario = null): array
    {
        $base = $this->queryBase($usuario);
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
        $corregidos = (clone $base)->where('catalogo_estatus_pedido_id', $pendienteId);
        $this->aplicarFiltroCorregidos($corregidos);
        $corregidosCount = $corregidos->count();

        return [
            'pendientes' => $pendientes,
            'corregidos' => $corregidosCount,
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

    private function queryBase(?User $usuario = null): Builder
    {
        $idsPorFase = $this->idsPorFase();
        $idsVisibles = array_values(array_filter(array_map(
            fn (string $fase) => $idsPorFase[$fase] ?? null,
            self::FASES_AUDITORIA
        )));

        $query = PedidoBma::with([
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
            'pesajeRespondidoPor:id,name',
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

        if ($usuario) {
            VisibilidadPedidoBma::aplicarAlcanceListadoBma($query, $usuario);
        }

        return $query;
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
                    })
                    ->orWhereHas('vendedor', function (Builder $v) use ($termino) {
                        $v->where('name', 'like', "%{$termino}%");
                    });
            });
        }

        if (! empty($filtros['catalogo_paqueteria_id'])) {
            $paqId = (int) $filtros['catalogo_paqueteria_id'];
            if ($paqId > 0) {
                $query->where('catalogo_paqueteria_id', $paqId);
            }
        }

        if (! empty($filtros['departamento_id'])) {
            $deptoId = (int) $filtros['departamento_id'];
            if ($deptoId > 0) {
                $query->whereHas('vendedor', function (Builder $v) use ($deptoId) {
                    $v->where('departamento_id', $deptoId)
                        ->orWhereHas('departamentos', fn (Builder $d) => $d->where('departamentos.id', $deptoId));
                });
            }
        }

        if (! empty($filtros['cliente'])) {
            $cliente = trim((string) $filtros['cliente']);
            if ($cliente !== '') {
                $query->whereHas('cliente', function (Builder $c) use ($cliente) {
                    $c->where('nombre', 'like', "%{$cliente}%")
                        ->orWhere('numero_cliente', 'like', "%{$cliente}%");
                });
            }
        }

        $tab = strtoupper($filtros['tab'] ?? 'TODAS');
        $idsPorFase = $this->idsPorFase();

        match ($tab) {
            'PENDIENTES' => $query->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0),
            'CORREGIDOS' => tap($query, function (Builder $q) use ($idsPorFase): void {
                $q->where('catalogo_estatus_pedido_id', $idsPorFase['PENDIENTE_AUXILIAR'] ?? 0);
                $this->aplicarFiltroCorregidos($q);
            }),
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

    private function aplicarOrden(Builder $query, array $filtros): void
    {
        $orden = strtolower(trim((string) ($filtros['ordenar'] ?? 'fecha_desc')));

        match ($orden) {
            'fecha_asc' => $query->reorder()->orderBy('fecha')->orderBy('id'),
            'folio_asc' => $query->reorder()->orderBy('folio')->orderBy('id'),
            'folio_desc' => $query->reorder()->orderByDesc('folio')->orderByDesc('id'),
            'cliente_asc' => $query->reorder()
                ->leftJoin('clientes', 'clientes.id', '=', 'pedidos_bma.cliente_id')
                ->orderBy('clientes.nombre')
                ->orderBy('pedidos_bma.id')
                ->select('pedidos_bma.*'),
            'cliente_desc' => $query->reorder()
                ->leftJoin('clientes', 'clientes.id', '=', 'pedidos_bma.cliente_id')
                ->orderByDesc('clientes.nombre')
                ->orderByDesc('pedidos_bma.id')
                ->select('pedidos_bma.*'),
            'total_asc' => $query->reorder()->orderBy('total_a_cobrar')->orderBy('id'),
            'total_desc' => $query->reorder()->orderByDesc('total_a_cobrar')->orderByDesc('id'),
            'vendedor_asc' => $query->reorder()
                ->leftJoin('users', 'users.id', '=', 'pedidos_bma.vendedor_id')
                ->orderBy('users.name')
                ->orderBy('pedidos_bma.id')
                ->select('pedidos_bma.*'),
            default => $query->reorder()->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * Misma regla que MaquinaEstadosPedidoBma::esPendienteReRevision (>1 retorno a PENDIENTE_AUXILIAR).
     */
    private function aplicarFiltroCorregidos(Builder $query): void
    {
        $fase = CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR;
        $query->whereRaw(
            '(SELECT COUNT(*) FROM pedido_bma_historial_estados h
                INNER JOIN catalogo_estatus_pedidos en ON en.id = h.estatus_nuevo_id
                LEFT JOIN catalogo_estatus_pedidos ea ON ea.id = h.estatus_anterior_id
                WHERE h.pedido_bma_id = pedidos_bma.id
                  AND en.fase_ciclo = ?
                  AND ea.fase_ciclo IS NOT NULL
                  AND ea.fase_ciclo <> ?) > 1',
            [$fase, $fase]
        );
    }

    private function idsPorFase(): array
    {
        return CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', self::FASES_AUDITORIA)
            ->pluck('id', 'fase_ciclo')
            ->all();
    }
}
