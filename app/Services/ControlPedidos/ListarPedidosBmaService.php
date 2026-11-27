<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Services\ControlPedidos\Municipal\EtiquetaConsultaMunicipal;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\ContextoDepartamentoPedido;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\Nacional\ResumenGrupoMultienvio;
use App\Support\ControlPedidos\RespuestaPreparacionTienda;
use App\Support\ControlPedidos\RevisionEnCursoPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use App\Support\ControlPedidos\BitacoraCedis;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Illuminate\Database\Eloquent\Builder;

class ListarPedidosBmaService
{
    public function __construct(
        private CalcularProgresoPedidoBmaService $progreso,
    ) {}

    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true, int $porPagina = 15)
    {
        $query = PedidoBma::with($this->relacionesConsulta())->orderByDesc('created_at');

        if ($usuario) {
            VisibilidadPedidoBma::aplicarAlcanceListadoBma($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros, $usuario);
        $this->colapsarMultienvios($query);

        if (! $paginar) {
            $pedidos = $query->get();
            if ($usuario) {
                ResumenGrupoMultienvio::paraPagina($pedidos, $usuario);
                $this->marcarAgrupados($pedidos);
            }

            return $pedidos->each(fn (PedidoBma $p) => $this->anexarFlagsVista($p, $usuario));
        }

        $paginador = $query->paginate(max(1, $porPagina))->withQueryString();
        if ($usuario) {
            ResumenGrupoMultienvio::paraPagina($paginador->getCollection(), $usuario);
            $this->marcarAgrupados($paginador->getCollection());
        }

        return $paginador->through(fn (PedidoBma $p) => $this->anexarFlagsVista($p, $usuario));
    }

    /**
     * Un multienvío se muestra como una sola operación: se omiten los envíos 2..N cuando el envío 1 es de la misma
     * vendedora y está en el listado. Quien solo ve un envío suelto (otro alcance) lo sigue viendo.
     */
    /** En el listado de Ventas el grupo es una sola fila: el envío 1 (o el origen en configuración) lo representa. */
    private function marcarAgrupados(\Illuminate\Support\Collection $pedidos): void
    {
        foreach ($pedidos as $pedido) {
            $grupo = $pedido->getAttributes()['multienvio'] ?? null;
            if (is_array($grupo) && ($pedido->multienvio_orden === null || (int) $pedido->multienvio_orden === 1) && ($grupo['estado'] ?? null) === 'activo') {
                $grupo['agrupado'] = true;
                $pedido->setAttribute('multienvio', $grupo);
            }
        }
    }

    private function colapsarMultienvios(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereNull('pedidos_bma.multienvio_orden')
                ->orWhere('pedidos_bma.multienvio_orden', '<=', 1)
                ->orWhereNotExists(function ($sub) {
                    $sub->selectRaw('1')->from('pedidos_bma as primero')
                        ->whereColumn('primero.multienvio_nacional_id', 'pedidos_bma.multienvio_nacional_id')
                        ->where('primero.multienvio_orden', 1)
                        ->whereColumn('primero.vendedor_id', 'pedidos_bma.vendedor_id');
                });
        });
    }

    public function presentar(PedidoBma $pedido, User $usuario): PedidoBma
    {
        $pedido->load($this->relacionesConsulta());
        ResumenGrupoMultienvio::paraPagina(collect([$pedido]), $usuario);
        $this->marcarAgrupados(collect([$pedido]));

        return $this->anexarFlagsVista($pedido, $usuario);
    }

    /**
     * @return list<string>
     */
    private function relacionesConsulta(): array
    {
        return [
            'cliente',
            'vendedor',
            'estatus',
            'origen',
            'tipoOperacionEnvio',
            'anexosEnvio.banco',
            'anexosEnvio.registradoPor',
            'envioTienda',
            'almacen',
            'sucursalDestino',
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
            'resguardosAnexados.resguardo',
            'safAplicaciones.credito',
            'pagosExhibicion.banco',
            'tareaPreparacionVigente.modalidad',
            'tareaPreparacionVigente.almacen',
            'tareaPreparacionVigente.productos',
            'tareaPreparacionVigente.atendidaPor',
            'tareaPreparacionVigente.cumplimientoFisico',
            'tareaPreparacionVigente.documentos',
            'cancelacionOperativaActiva.tareas.tarea.almacen',
            'cancelacionOperativaActiva.tareas.tarea.modalidad',
            'tareaPreparacionVigente.historial',
            'tareaPreparacionVigente.paqueteria',
            'tareaPreparacionVigente.caratulas',
            'tareaPreparacionRespondida.modalidad',
            'tareaPreparacionRespondida.almacen',
            'tareaPreparacionRespondida.productos',
            'tareaPreparacionRespondida.atendidaPor',
            'tareaPreparacionRespondida.cumplimientoFisico',
            'tareaPreparacionRespondida.documentos',
            'tareaPreparacionRespondida.paqueteria',
            'tareaPreparacionRespondida.caratulas',
            'referencias',
        ];
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
        ConsultaMunicipal::hidratarAlmacen($pedido);
        // Se calcula antes de filtrar la bitácora por permisos. No expone su contenido.
        $pedido->setAttribute('version_consulta', (int) $pedido->historial->max('id'));
        $puedeEditar = $usuario
            ? VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)
            : false;

        $pedido->setAttribute('departamento_operacion_id', ContextoDepartamentoPedido::dePedido($pedido));
        $pedido->setAttribute('puede_editar', $puedeEditar);
        $pedido->setAttribute('puede_continuar_nacional', $puedeEditar
            && \App\Support\ControlPedidos\Nacional\CapturaNacional::es($pedido)
            && $pedido->consultaCerrada()
            && in_array($pedido->estatus?->fase_ciclo, [
                CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
                CatalogoEstatusPedido::FASE_EN_CEDIS,
                CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
                CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            ], true)
            && ! $pedido->cajas->contains(fn ($caja) => $caja->estaActiva() && $caja->estaRecolectada()));
        $pedido->setAttribute('puede_anexar_pago_envio', $pedido->puedeAnexarPagoEnvio());
        $pedido->setAttribute('puede_mutar', $puedeEditar);
        $pedido->setAttribute('puede_cancelar', $puedeEditar && $pedido->puedeCancelarDirecto());
        $pedido->setAttribute('tiene_sin_existencia_abierta', $pedido->tieneSinExistenciaAbierta());
        $pedido->setAttribute('consulta_cerrada', $pedido->consultaCerrada());
        $pedido->setAttribute('puede_cerrar_consulta', $puedeEditar && $pedido->puedeCerrarConsulta());
        $pedido->setAttribute('motivo_bloqueo_cierre', ConsultaMunicipal::es($pedido)
            ? ConsultaMunicipal::motivoBloqueo($pedido)
            : (($pedido->esConsultaDisponibilidadTienda() || $pedido->esConsultaDisponibilidadCedis()) ? $pedido->motivoBloqueoCierreConsultaTienda() : null));
        $conversion = app(\App\Services\ControlPedidos\Conversion\EvaluarElegibilidadConversion::class)->flags($pedido, $usuario);
        $pedido->setAttribute('conversion_modalidad', $conversion->toArray()['conversion_modalidad']);
        if ($conversion->reserva_activa) {
            $pedido->setAttribute('puede_cerrar_consulta', false);
            $pedido->setAttribute('motivo_bloqueo_cierre', \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::MENSAJE_RESERVA);
        }
        if (ConsultaMunicipal::es($pedido)) {
            $pedido->setAttribute('etiqueta_consulta', EtiquetaConsultaMunicipal::de($pedido));
        }
        $pedido->setAttribute('piezas_adicionales', \App\Support\ControlPedidos\Municipal\PresentadorPiezasAdicionales::para($pedido, $usuario));
        $pedido->setAttribute('cambio_a_tienda', \App\Support\ControlPedidos\Municipal\PresentadorCambioATienda::para($pedido, $usuario));
        $pedido->setAttribute('es_consulta_mercancia', $pedido->esConsultaMercancia());
        $pedido->setAttribute('consulta_cedis_modo_revision_mercancia', $pedido->consultaCedisModoRevisionMercancia());
        $pedido->setAttribute('fuentes_pago', $pedido->fuentesPagoResumen());
        $pedido->setAttribute('pendiente_re_revision', MaquinaEstadosPedidoBma::esPendienteReRevision($pedido));
        $pedido->setAttribute('en_revision_ahora', RevisionEnCursoPedidoBma::activa($pedido->id));
        $pedido->setAttribute('progreso', $this->progreso->calcular($pedido));
        app(AnexarEstatusSucursalPedidoBmaService::class)->ejecutar($pedido);
        $pedido->setAttribute('usa_preparacion_tienda', $pedido->usaPreparacionTienda());
        $enRutaPreparacion = $pedido->enRutaPreparacionTienda();
        $pedido->setAttribute('en_ruta_preparacion_tienda', $enRutaPreparacion);
        $pesoPreparacionObligatorio = false;
        $pesoPreparacionCapturado = false;
        if ($enRutaPreparacion) {
            $tareaRuta = $pedido->tareaPreparacionVigente ?? $pedido->tareaPreparacionRespondida;
            if ($tareaRuta) {
                $reqRuta = app(CalcularRequisitosPreparacionService::class)->efectivos($tareaRuta);
                $pesoPreparacionObligatorio = (bool) ($reqRuta['peso_real_obligatorio'] ?? false);
                $pesoRuta = $tareaRuta->peso_real_kg ?? $pedido->peso_real_kg;
                $pesoPreparacionCapturado = $pesoRuta !== null && $pesoRuta !== '' && (float) $pesoRuta > 0;
            }
        }
        $pedido->setAttribute('preparacion_peso_real_obligatorio', $pesoPreparacionObligatorio);
        $pedido->setAttribute('preparacion_peso_capturado', $pesoPreparacionCapturado);
        $pedido->setAttribute('tiene_traslado_cedis_pendiente', $pedido->tieneTrasladoCedisPendiente());
        $pedido->setAttribute('tiene_caratula_municipal_pendiente', $pedido->tieneCaratulaMunicipalPendiente());
        $tareaVista = $this->resolverTareaPreparacionVista($pedido);
        $pedido->setAttribute('tarea_preparacion', $this->serializarTareaPreparacion($tareaVista));
        $pedido->setAttribute('referencia_captura', $this->referenciaCaptura($pedido));

        $canc = $pedido->relationLoaded('cancelacionOperativaActiva')
            ? $pedido->cancelacionOperativaActiva
            : null;
        if (ConsultaMunicipal::es($pedido)) {
            $pedido->setAttribute('resguardo_envio', \App\Support\ControlPedidos\PoliticaResguardoEnvio::evaluar($pedido));
        }
        $pedido->setAttribute('esperando_pago_at', $pedido->esperando_pago_at?->toIso8601String());
        $pedido->setAttribute('esta_esperando_pago', $pedido->estaEsperandoPago());
        $fechaLimiteEspera = null;
        if ($pedido->estaEsperandoPago()) {
            $t = $this->resolverTareaPreparacionVista($pedido);
            $fechaLimiteEspera = $t?->fecha_limite?->toIso8601String();
        }
        $pedido->setAttribute('fecha_limite_espera', $fechaLimiteEspera);
        $pedido->setAttribute('puede_espera_pago', $puedeEditar
            && ! $pedido->estaEsperandoPago()
            && ! $canc
            && $pedido->enRutaPreparacionTienda()
            && in_array($pedido->tarea_preparacion['estado'] ?? '', ['RESPONDIDA', 'RECIBIDA_CEDIS'], true));
        $pedido->setAttribute('cancelacion_operativa', $canc ? [
            'id' => $canc->id,
            'estado' => $canc->estado,
            'motivo' => $canc->motivo,
            'comentario' => $canc->comentario,
            'puede_reactivar' => $canc->puedeReactivar(),
            'requiere_resolucion_financiera' => (bool) $canc->requiere_resolucion_financiera,
            'version' => $canc->version,
            'folio_anterior' => $canc->folio_anterior,
            'tareas' => $canc->tareas->map(fn ($fila) => [
                'id' => $fila->id,
                'estado_liberacion' => $fila->estado_liberacion,
                'almacen' => $fila->tarea?->almacen?->nombre,
                'area' => $fila->tarea?->area_responsable_codigo,
                'modalidad' => $fila->tarea?->modalidad?->nombre,
            ])->values()->all(),
        ] : null);

        return BitacoraCedis::presentar($pedido, $usuario);
    }

    /**
     * Folio adicional ya registrado. No rellena con el folio interno ni con la remisión.
     *
     * @return array{tipo: string, folio: string}|null
     */
    private function referenciaCaptura(PedidoBma $pedido): ?array
    {
        $referencia = $pedido->relationLoaded('referencias')
            ? $pedido->referencias
                ->where('vigente', true)
                ->whereIn('tipo', [
                    PedidoBmaReferencia::TIPO_COTIZACION,
                    PedidoBmaReferencia::TIPO_PEDIDO,
                    PedidoBmaReferencia::TIPO_OTRO,
                ])
                ->sortByDesc('id')
                ->first()
            : null;

        if (! $referencia) {
            return null;
        }

        return [
            'tipo' => (string) $referencia->tipo,
            'folio' => (string) $referencia->folio,
        ];
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
            'respuesta' => RespuestaPreparacionTienda::datos($tarea),
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
            'pago_confirmado' => $tarea->cumplimientoFisico?->pago_confirmado_at !== null,
            'salida_autorizada' => $tarea->cumplimientoFisico?->salida_autorizada_at !== null,
            'progreso_traslado' => VisibilidadTareaPreparacion::progresoTraslado($tarea),
            'progreso_caratula' => VisibilidadTareaPreparacion::progresoCaratula($tarea),
            'motivo_rechazo_cedis' => $tarea->motivo_rechazo_cedis,
            'enviada_cedis_at' => $tarea->enviada_cedis_at?->toIso8601String(),
            'recibida_cedis_at' => $tarea->recibida_cedis_at?->toIso8601String(),
            'entrega_municipal' => $tarea->modalidad?->esEnvioMunicipio() ? [
                'destinatario_es_cliente' => $tarea->destinatario_es_cliente,
                'destinatario_nombre' => $tarea->destinatario_nombre,
                'destinatario_telefono' => $tarea->destinatario_telefono,
                'municipio_destino' => $tarea->municipio_destino,
                'direccion_referencia' => $tarea->direccion_referencia,
                'catalogo_paqueteria_id' => $tarea->catalogo_paqueteria_id,
                'modalidad_cobro' => $tarea->modalidad_cobro,
                'transporte' => $tarea->paqueteria?->nombre,
            ] : null,
            'caratula' => ($c = $tarea->caratulaVigente()) ? [
                'id' => $c->id,
                'version' => $c->version,
                'estado' => $c->estado,
            ] : null,
            'apartado' => $tarea->cumplimientoFisico ? [
                'estado' => $tarea->cumplimientoFisico->estado,
                'version' => $tarea->cumplimientoFisico->version,
            ] : null,
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros, ?User $usuario = null): void
    {
        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function (Builder $q) use ($termino) {
                $q->where('folio', 'like', "%{$termino}%")
                    ->orWhere('folio_remision', 'like', "%{$termino}%")
                    ->orWhere('contacto_nombre_snapshot', 'like', "%{$termino}%")
                    ->orWhere('contacto_telefono_snapshot', 'like', "%{$termino}%")
                    ->orWhereHas('referencias', fn (Builder $r) => $r->where('folio', 'like', "%{$termino}%"))
                    ->orWhere(fn (Builder $g) => \App\Support\ControlPedidos\Nacional\BusquedaGrupoMultienvio::orHermano($g, $termino))
                    ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                        $c->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('numero_cliente', 'like', "%{$termino}%");
                    });
            });
        }

        $this->aplicarFiltrosAcotados($query, $filtros, $usuario);

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

    /** Fecha del pedido, almacén de preparación y «solo míos». Valores inválidos se ignoran. */
    private function aplicarFiltrosAcotados(Builder $query, array $filtros, ?User $usuario): void
    {
        foreach (['desde' => '>=', 'hasta' => '<='] as $clave => $operador) {
            $valor = $filtros[$clave] ?? null;
            if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)
                && \DateTime::createFromFormat('Y-m-d', $valor)?->format('Y-m-d') === $valor) {
                $query->whereDate('fecha', $operador, $valor);
            }
        }

        $almacen = $filtros['almacen_id'] ?? null;
        if (is_scalar($almacen) && ctype_digit((string) $almacen) && (int) $almacen > 0) {
            $query->where('almacen_id', (int) $almacen);
        }

        if ($usuario && filter_var($filtros['mios'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('vendedor_id', $usuario->id);
        }
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
