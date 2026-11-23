<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\User;
use App\Support\ControlPedidos\Municipal\PresentadorCambioATienda;
use App\Support\ControlPedidos\Municipal\PresentadorPiezasAdicionales;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorBultosEmpaqueCedisPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorIncidenciaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorPedidoRevisionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorRegistroManualResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorRetiroPedidoResguardoPdv;
use App\Support\PuntoVenta\Resguardos\UrlEvidenciaResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaDetalleResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly CalcularAntiguedadOperativaResguardoPdvService $antiguedad,
        private readonly PlazosCustodiaResguardoPdvConfig $plazos,
        private readonly ConsultaAuditoriaResguardoPdvService $auditoria,
        private readonly SincronizarCantidadBultosEsperadaResguardoPdvService $sincronizarCantidadBultos,
        private readonly AutorizacionConsultaResguardoPdv $autorizacion,
        private readonly RegistrarSalidaTerminalResguardoPdvService $salidaTerminal,
    ) {}

    /**
     * @return array{resguardo: array<string, mixed>, timeline: list<array<string, mixed>>}
     */
    public function obtener(User $user, ResguardoPdv $resguardo): array
    {
        $this->autorizacion->asegurarDetalleResguardo($user, $resguardo);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        $resguardo->load([
            'sucursal:id,nombre',
            'cliente:id,numero_cliente',
            'pedido:id,folio,folio_remision,envia_a_otra_persona,envia_otra_persona,solicitud_preparacion_json,prioridad_md',
            'pedido.bultosEmpaque.documentos',
            'evidencias',
            'eventoRegistroManual.actor:id,name,username',
            'bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id'),
            'incidencias' => fn ($q) => $q
                ->with([
                    'evidencias',
                    'reportadoPor:id,username',
                    'autorizadoPor:id,username',
                ])
                ->orderByDesc('reportado_at')
                ->orderByDesc('id'),
            'entregas' => fn ($q) => $q
                ->with([
                    'evidencias' => fn ($ev) => $ev->orderBy('capturado_at')->orderBy('id'),
                ])
                ->orderByDesc('entregado_at')
                ->orderByDesc('id')
                ->limit(1),
        ]);

        if ((int) $resguardo->cantidad_bultos_esperada < 1) {
            $resguardo = $this->sincronizarCantidadBultos->ejecutar($resguardo);
            $resguardo->load([
                'sucursal:id,nombre',
                'cliente:id,numero_cliente',
                'pedido:id,folio,folio_remision,envia_a_otra_persona,envia_otra_persona,solicitud_preparacion_json',
                'pedido.bultosEmpaque.documentos',
                'evidencias',
                'eventoRegistroManual.actor:id,name,username',
                'bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id'),
                'incidencias' => fn ($q) => $q
                    ->with([
                        'evidencias',
                        'reportadoPor:id,username',
                        'autorizadoPor:id,username',
                    ])
                    ->orderByDesc('reportado_at')
                    ->orderByDesc('id'),
                'entregas' => fn ($q) => $q
                    ->with([
                        'evidencias' => fn ($ev) => $ev->orderBy('capturado_at')->orderBy('id'),
                    ])
                    ->orderByDesc('entregado_at')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);
        }

        $auditoria = $this->auditoria->obtener($user, $resguardo);

        $serializado = $this->serializarResguardo($resguardo);
        $serializado['integracion_municipal'] = $this->integracionMunicipal($user, $resguardo);
        if ($resguardo->esOrigenMunicipal()) {
            // Solo municipal: Tienda y Nacional conservan su forma.
            $salida = $resguardo->salidaTerminal();
            $serializado['puede_salida_terminal'] = $this->salidaTerminal->puedeRegistrar($user, $resguardo);
            $serializado['salida_terminal'] = $salida ? [
                'at' => $salida['at'] ?? null,
                'receptor' => $salida['receptor'] ?? null,
            ] : null;
            $serializado['es_municipal'] = true;
            $serializado['caratula'] = $this->resumenCaratulaMunicipal($user, $resguardo);
            $serializado['complementos_municipales'] = $this->complementosMunicipales($user, $resguardo);
            $pedidoCambio = $resguardo->pedido_bma_id ? PedidoBma::query()->find($resguardo->pedido_bma_id) : null;
            $serializado['cambio_a_tienda'] = $pedidoCambio ? PresentadorCambioATienda::para($pedidoCambio, $user) : null;
        }

        return [
            'resguardo' => $serializado,
            'timeline' => $auditoria['timeline'],
        ];
    }

    /**
     * Resumen de la carátula del envío municipal para las acciones del detalle. Las rutas de carátula repiten la
     * validación de permiso y sucursal; aquí solo se decide qué acciones ofrecer.
     *
     * @return array<string, mixed>|null
     */
    /**
     * M-03b: piezas adicionales del envío para la sucursal que tiene el paquete. La misma fuente que ve CEDIS y Ventas;
     * «puede_integrar» exige pago y remisión propios, paquete en custodia y personal de la sucursal.
     *
     * @return list<array<string, mixed>>
     */
    private function complementosMunicipales(User $user, ResguardoPdv $resguardo): array
    {
        $principal = $resguardo->pedido_bma_id ? PedidoBma::query()->find($resguardo->pedido_bma_id) : null;

        return $principal instanceof PedidoBma
            ? (PresentadorPiezasAdicionales::para($principal, $user)['items'] ?? [])
            : [];
    }

    private function resumenCaratulaMunicipal(User $user, ResguardoPdv $resguardo): ?array
    {
        // La relación del resguardo carga pocas columnas; la carátula necesita destino, cancelación y estatus.
        $pedido = $resguardo->pedido_bma_id ? PedidoBma::query()->with('estatus')->find($resguardo->pedido_bma_id) : null;
        if (! $pedido || ! RecorridoFisicoMunicipal::aplica($pedido)) {
            return null;
        }

        $vigente = PedidoBmaCaratula::query()
            ->where('pedido_bma_id', $pedido->id)
            ->whereNull('pedido_bma_tarea_preparacion_id')
            ->whereIn('estado', [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA])
            ->whereNotNull('ruta_pdf')
            ->orderByDesc('version')
            ->first();

        $sucursalOk = $pedido->sucursal_destino_id
            && $user->idsSucursalesOperables()->contains((int) $pedido->sucursal_destino_id);
        $cancelado = $pedido->cancelado_at || $pedido->estatus?->fase_ciclo === 'CANCELADO';
        // Tras la salida a terminal el paquete ya no está en la sucursal: solo queda consultar la carátula.
        $editable = ! $cancelado && ! RecorridoFisicoMunicipal::salioATerminal($pedido);

        return [
            'pedido_id' => (int) $pedido->id,
            'id' => $vigente?->id,
            'version' => $vigente?->version,
            'estado' => $vigente?->estado,
            'generada_at' => $vigente?->generada_at?->toIso8601String(),
            'puede_generar' => ! $vigente && $sucursalOk && $editable && $user->can('control_pedidos.tienda.generar_caratula'),
            'puede_regenerar' => $vigente !== null && $vigente->estado !== PedidoBmaCaratula::ESTADO_COLOCADA
                && $sucursalOk && $editable && $user->can('control_pedidos.tienda.regenerar_caratula'),
            'puede_descargar' => $vigente !== null && $sucursalOk && $user->can('control_pedidos.tienda.imprimir_caratula'),
        ];
    }

    /**
     * Integración local pendiente o confirmada de esta custodia con un envío municipal. Solo expone la fila de esta
     * remisión; `puede_confirmar` repite la política del servidor y la ruta vuelve a validarla.
     *
     * @return array<string, mixed>|null
     */
    private function integracionMunicipal(User $user, ResguardoPdv $resguardo): ?array
    {
        if (! $resguardo->pedido_bma_id) {
            return null;
        }
        $custodia = PedidoBma::query()->find($resguardo->pedido_bma_id);
        $local = $custodia && is_array($custodia->solicitud_preparacion_json['integracion_local'] ?? null)
            ? $custodia->solicitud_preparacion_json['integracion_local']
            : [];
        $principal = in_array($local['estado'] ?? null, ['pendiente', 'integrado'], true) && (int) ($local['pedido_principal_id'] ?? 0) > 0
            ? PedidoBma::query()->find((int) $local['pedido_principal_id'])
            : null;
        if (! $principal || (int) $principal->cliente_id !== (int) $custodia->cliente_id) {
            return null;
        }
        $fila = collect(PoliticaResguardoEnvio::vinculos($principal))
            ->first(fn (array $v) => (int) $v['origen_id'] === (int) $custodia->id && $v['mecanismo'] === PoliticaResguardoEnvio::MECANISMO_LOCAL);
        if (! $fila) {
            return null;
        }

        return [
            'pedido_id' => (int) $principal->id,
            'puede_confirmar' => PoliticaResguardoEnvio::puedeConfirmarIntegracionFisica($user, $custodia, PoliticaResguardoEnvio::MECANISMO_LOCAL),
            'resguardo_envio' => ['activo' => true, 'estados' => [], 'vinculos' => [$fila]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResguardo(ResguardoPdv $resguardo): array
    {
        $antiguedadConfigurada = $this->antiguedadConfigurada();
        $evaluacion = $antiguedadConfigurada
            ? $this->antiguedad->evaluar($resguardo)
            : [
                'clasificaciones' => $this->antiguedad->clasificacionesVacias(),
                'fecha_limite_custodia' => null,
                'fecha_limite_rezago' => null,
                'plazos_snapshot' => null,
            ];

        $clasificacionesEtiquetas = [];
        foreach ($evaluacion['clasificaciones'] as $clave => $activa) {
            if ($activa) {
                $clasificacionesEtiquetas[] = EtiquetasResguardoPdv::antiguedades()[$clave] ?? $clave;
            }
        }

        $retiro = SerializadorRetiroPedidoResguardoPdv::desdeResguardo($resguardo);

        return [
            'id' => $resguardo->id,
            'version' => (int) $resguardo->version,
            'estado' => $resguardo->estado,
            'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstadoResguardo($resguardo),
            'pedido_bma_id' => $resguardo->pedido_bma_id,
            'es_t02_documental' => \App\Support\ControlPedidos\TransferenciaT02::documental($resguardo->pedido),
            'es_custodia_documental' => \App\Support\ControlPedidos\CustodiaDocumentalTienda::aplica($resguardo->pedido),
            'snapshot_folio' => $resguardo->snapshot_folio,
            'snapshot_cliente_nombre' => $resguardo->snapshot_cliente_nombre,
            'envia_a_otra_persona' => $retiro['envia_a_otra_persona'],
            'envia_otra_persona' => $retiro['envia_otra_persona'],
            'etiqueta_retiro' => $retiro['etiqueta_retiro'],
            'referencia_cliente' => $this->referenciaCliente($resguardo),
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'cantidad_bultos_recibida' => EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo),
            'cantidad_bultos_pendiente' => EstadoRecepcionResguardoPdv::cantidadPendiente($resguardo),
            'cantidad_bultos_en_custodia' => EstadoResguardoPdv::cantidadEnCustodia($resguardo),
            'cantidad_bultos_pendiente_custodia' => EstadoResguardoPdv::cantidadPendienteCustodia($resguardo),
            'admite_recepcion' => EstadoRecepcionResguardoPdv::admiteRecepcion($resguardo),
            'admite_pasar_a_recepcion' => EstadoResguardoPdv::admitePasarARecepcion($resguardo),
            'admite_confirmacion_custodia' => EstadoResguardoPdv::admiteConfirmacionCustodia($resguardo),
            'recepcion_completa' => EstadoRecepcionResguardoPdv::recepcionCompleta($resguardo),
            'custodia_completa' => EstadoResguardoPdv::custodiaCompleta($resguardo),
            'salida_cedis_at' => $resguardo->salida_cedis_at?->toIso8601String(),
            'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
            'custodia_confirmada_at' => $resguardo->custodia_confirmada_at?->toIso8601String(),
            'vencido_repuesto_at' => $resguardo->vencido_repuesto_at?->toIso8601String(),
            'entrega_completada_at' => $resguardo->entrega_completada_at?->toIso8601String(),
            'devolucion_confirmada_at' => $resguardo->devolucion_confirmada_at?->toIso8601String(),
            'entrega_bloqueada' => $resguardo->entrega_bloqueada,
            'cancelacion_recibida' => $resguardo->eventos()
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA)
                ->exists(),
            'clasificaciones' => $evaluacion['clasificaciones'],
            'clasificaciones_etiquetas' => $clasificacionesEtiquetas,
            'fecha_limite_custodia' => $evaluacion['fecha_limite_custodia'],
            'fecha_limite_rezago' => $evaluacion['fecha_limite_rezago'],
            'antiguedad_configurada' => $antiguedadConfigurada,
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
            ] : null,
            'pedido_revision' => SerializadorPedidoRevisionResguardoPdv::desdePedido($resguardo->pedido),
            'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                'id' => $bulto->id,
                'folio' => $bulto->folio,
                'codigo_etiqueta' => $bulto->codigo_etiqueta,
                'tipo' => $bulto->tipo,
                'estado' => $bulto->estado,
                'recepcion_at' => $bulto->recepcion_at?->toIso8601String(),
                'entrega_at' => $bulto->entrega_at?->toIso8601String(),
            ])->values()->all(),
            'incidencias' => $resguardo->incidencias
                ->map(fn ($incidencia) => SerializadorIncidenciaResguardoPdv::incidencia($incidencia))
                ->values()
                ->all(),
            'bultos_empaque_cedis' => SerializadorBultosEmpaqueCedisPdv::desdePedido($resguardo->pedido),
            'registro_manual' => SerializadorRegistroManualResguardoPdv::desdeResguardo($resguardo),
            'ultima_entrega' => $this->serializarUltimaEntrega($resguardo),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializarUltimaEntrega(ResguardoPdv $resguardo): ?array
    {
        if ($resguardo->estado !== ResguardoPdv::ESTADO_ENTREGADO) {
            return null;
        }

        $entrega = $resguardo->entregas->first();
        if (! $entrega instanceof ResguardoPdvEntrega) {
            return null;
        }

        $relaciones = EtiquetasResguardoPdv::relacionesEntrega();
        $relacion = (string) $entrega->relacion;
        $firma = $entrega->evidencias
            ->first(fn (ResguardoPdvEvidencia $evidencia) => $evidencia->tipo === ResguardoPdvEvidencia::TIPO_FIRMA);

        return [
            'id' => $entrega->id,
            'relacion' => $relacion,
            'relacion_etiqueta' => $relaciones[$relacion] ?? $relacion,
            'nombre_quien_retira' => $entrega->nombre_quien_retira,
            'entregado_at' => $entrega->entregado_at?->toIso8601String(),
            'firma_entrega' => $firma ? [
                'id' => $firma->id,
                'tipo' => $firma->tipo,
                'nombre_original' => $firma->nombre_original,
                'mime_type' => $firma->mime_type,
                'ruta_publica' => UrlEvidenciaResguardoPdv::url($firma),
            ] : null,
            'evidencias_fotograficas' => $entrega->evidencias
                ->filter(fn (ResguardoPdvEvidencia $evidencia) => $evidencia->tipo === ResguardoPdvEvidencia::TIPO_FOTO)
                ->map(fn (ResguardoPdvEvidencia $evidencia) => [
                    'id' => $evidencia->id,
                    'tipo' => $evidencia->tipo,
                    'nombre_original' => $evidencia->nombre_original,
                    'mime_type' => $evidencia->mime_type,
                    'ruta_publica' => UrlEvidenciaResguardoPdv::url($evidencia),
                ])
                ->values()
                ->all(),
        ];
    }

    private function referenciaCliente(ResguardoPdv $resguardo): string
    {
        $numero = $resguardo->cliente?->numero_cliente;

        if ($numero !== null && $numero !== '') {
            return '#'.(string) $numero;
        }

        return $resguardo->snapshot_folio ?: 'Sin referencia';
    }

    private function antiguedadConfigurada(): bool
    {
        $global = $this->plazos->obtenerGlobal();

        return $global !== null && $global['activo'];
    }
}
