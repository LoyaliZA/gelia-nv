<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ResponderPesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private SesionEvidenciaCedisService $sesionEvidencia,
        private SincronizarCajasPedidoBmaService $sincronizarCajas,
        private CalcularTotalesEnvioPedidoService $totalesEnvio,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineasCaja
     * @param  array{
     *   estado_fisico_general?: string,
     *   comentario_fisico_general?: string|null,
     *   evidencias_generales?: list<UploadedFile>,
     *   evidencias_envios?: array<int, list<UploadedFile>>,
     *   revisiones?: list<array<string, mixed>>,
     *   motivo_retiro?: string|null
     * }  $revisionFisica
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $lineasCaja, array $revisionFisica = []): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen']);

        if (! $pedido->puedeResponderPesaje()) {
            throw new \RuntimeException(
                $pedido->esConsultaMercancia()
                    ? 'Este pedido no está pendiente de consulta de mercancía.'
                    : 'Este pedido no está pendiente de pesaje.'
            );
        }

        $soloRevisiones = $pedido->esConsultaMercancia();
        $pesoAntes = (float) ($pedido->peso_cobrado_guia_kg ?? 0);
        $cajasAntes = (int) ($pedido->numero_cajas ?? 0);
        $costoEnvioAntes = $pedido->costo_envio;
        $esActualizacion = (bool) $pedido->consulta_actualizacion_pendiente;

        $lineas = $soloRevisiones ? [] : $this->normalizarLineas($lineasCaja);
        if (! $soloRevisiones && $lineas === []) {
            throw new \InvalidArgumentException('Debe indicar al menos un envío con tipo de caja y pesos.');
        }

        $comentarioGeneral = trim((string) ($revisionFisica['comentario_fisico_general'] ?? ''));
        /** @var list<UploadedFile> $evidenciasGenerales */
        $evidenciasGenerales = array_values(array_filter(
            $revisionFisica['evidencias_generales'] ?? [],
            fn ($f) => $f instanceof UploadedFile
        ));

        $revisiones = $this->normalizarRevisiones($revisionFisica['revisiones'] ?? []);
        if ($soloRevisiones && $revisiones === []) {
            throw new \InvalidArgumentException('Debe revisar al menos un producto para la consulta de mercancía.');
        }

        foreach ($revisiones as $rev) {
            if (PedidoBmaRevisionProducto::requiereEvidencia($rev['estado_fisico'])
                && $rev['evidencias'] === []
                && ! $this->sesionEvidencia->tieneFotoProducto($pedido, $rev['client_uuid'])) {
                throw new \InvalidArgumentException(
                    "El producto «{$rev['descripcion_producto']}» en estado malo/dañado requiere evidencia."
                );
            }
        }
        $evidenciasEnvios = $soloRevisiones
            ? []
            : $this->normalizarEvidenciasEnvios(
                $revisionFisica['evidencias_envios'] ?? [],
                count($lineas)
            );

        // Estado general se deriva de productos (default Bueno); ya no se captura en UI.
        $estadoGeneral = $this->derivarEstadoGeneral(
            $revisiones,
            (string) ($revisionFisica['estado_fisico_general'] ?? PedidoBmaRevisionProducto::ESTADO_BUENO)
        );

        if (! $soloRevisiones) {
            // Foto del lote por cada caja (siempre): local o sesión celular.
            foreach ($lineas as $i => $linea) {
                $uuid = (string) ($linea['client_uuid'] ?? '');
                $hayLocal = ($evidenciasEnvios[$i] ?? []) !== [];
                $haySesion = $uuid !== '' && $this->sesionEvidencia->tieneFotoCaja($pedido, $uuid, $i);
                if (! $hayLocal && ! $haySesion) {
                    throw new \InvalidArgumentException('Adjunte al menos una foto del contenido del envío '.($i + 1).'.');
                }
            }
        } elseif ($evidenciasGenerales === [] && ! $this->sesionEvidencia->tieneAlgunaFotoCaja($pedido)) {
            throw new \InvalidArgumentException(
                'Adjunte al menos una foto de cómo quedan los productos (evidencia final del pedido).'
            );
        }

        $tipos = collect();
        if (! $soloRevisiones) {
            $tipos = CatalogoTipoCajaPedido::query()
                ->whereIn('id', array_column($lineas, 'catalogo_tipo_caja_id'))
                ->get()
                ->keyBy('id');

            if ($tipos->count() !== count(array_unique(array_column($lineas, 'catalogo_tipo_caja_id')))) {
                throw new \InvalidArgumentException('Una o más cajas del catálogo no existen.');
            }
        }

        return DB::transaction(function () use (
            $pedido, $usuarioId, $lineas, $tipos,
            $estadoGeneral, $comentarioGeneral, $evidenciasGenerales, $evidenciasEnvios, $revisiones,
            $soloRevisiones, $pesoAntes, $cajasAntes, $costoEnvioAntes, $esActualizacion, $revisionFisica
        ) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            $pesoRealTotal = 0.0;
            $pesoVolumetricoTotal = 0.0;
            $pesoCobradoTotal = 0.0;
            $cajaPrincipalId = null;
            $maxVol = -1.0;
            /** @var list<PedidoBmaCaja> $cajasCreadas */
            $cajasCreadas = [];
            $productoUuidARevisionId = [];
            $cajaUuidACajaId = [];

            if (! $soloRevisiones) {
                $lineasSync = [];
                foreach ($lineas as $i => $linea) {
                    $tipo = $tipos->get($linea['catalogo_tipo_caja_id']);
                    $pesoCobrado = PedidoBma::calcularPesoCobradoGuia($linea['peso_real_kg'], $linea['peso_volumetrico_kg']);
                    $linea['peso_cobrado_kg'] = $pesoCobrado;
                    $lineasSync[] = $linea;
                    $pesoRealTotal += $linea['peso_real_kg'];
                    $pesoVolumetricoTotal += $linea['peso_volumetrico_kg'];
                    $pesoCobradoTotal += (float) $pesoCobrado;
                    if ($linea['peso_volumetrico_kg'] > $maxVol) {
                        $maxVol = $linea['peso_volumetrico_kg'];
                        $cajaPrincipalId = $tipo->id;
                    }
                }

                $sync = $this->sincronizarCajas->ejecutar(
                    $pedido,
                    $lineasSync,
                    $usuarioId,
                    isset($revisionFisica['motivo_retiro']) ? (string) $revisionFisica['motivo_retiro'] : null
                );
                $cajasCreadas = $sync['cajas'];
                foreach ($cajasCreadas as $idx => $cajaNueva) {
                    $cajaUuidACajaId['idx:'.$idx] = $cajaNueva->id;
                    $uuid = (string) ($cajaNueva->uuid_operativo ?: ($lineasSync[$idx]['client_uuid'] ?? ''));
                    if ($uuid !== '') {
                        $cajaUuidACajaId[$uuid] = $cajaNueva->id;
                    }
                }
            }

            $this->sincronizarRevisiones($pedido, $revisiones, $productoUuidARevisionId);

            if (! $soloRevisiones && $cajaPrincipalId === null) {
                $cajaPrincipalId = $lineas[0]['catalogo_tipo_caja_id'];
            }

            $tieneSinExistencia = collect($revisiones)->contains(
                fn (array $r) => $r['estado_fisico'] === PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
            );
            $tieneObs = $tieneSinExistencia
                || PedidoBmaRevisionProducto::esObservacionParaVentas($estadoGeneral)
                || collect($revisiones)->contains(
                    fn (array $r) => PedidoBmaRevisionProducto::esObservacionParaVentas($r['estado_fisico'])
                );

            $estatus = $pedido->estatus;
            $numeroCajas = count($lineas);

            MaquinaEstadosPedidoBma::assertTransicion(
                $estatus?->fase_ciclo,
                CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO
            );
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO);
            if (! $estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus de pesaje respondido.');
            }

            $cambioPesos = ! $soloRevisiones && (
                abs($pesoAntes - $pesoCobradoTotal) > 0.0001
                || $cajasAntes !== $numeroCajas
            );
            // Invalidar costo solo si la actualización cambia pesos/cajas (Envío).
            $costoEnvio = ($esActualizacion && $cambioPesos) ? null : $costoEnvioAntes;
            $totalACobrar = $pedido->total_a_cobrar;
            if ($esActualizacion && $cambioPesos && $costoEnvioAntes !== null) {
                $mercancia = (float) $pedido->total_mercancia;
                $seguro = (bool) $pedido->aplica_seguro;
                $costoSeguro = (float) ($pedido->costo_seguro ?? 0);
                $saldoFavor = (float) ($pedido->saldo_a_favor ?? 0);
                $totalACobrar = PedidoBma::calcularTotal($mercancia, 0, $seguro, $costoSeguro, $saldoFavor);
            }

            $datosPedido = [
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
                'pesaje_respondido_at' => now(),
                'pesaje_respondido_por_id' => $usuarioId,
                'estado_fisico_general' => $estadoGeneral,
                'comentario_fisico_general' => $comentarioGeneral !== '' ? $comentarioGeneral : null,
                'tiene_observaciones_fisicas' => $tieneObs,
                'consulta_actualizacion_pendiente' => false,
                'consulta_cerrada_at' => null,
                'consulta_cerrada_por_id' => null,
                'motivo_repesaje' => null,
            ];

            if ($soloRevisiones) {
                $datosPedido['peso_real_kg'] = null;
                $datosPedido['peso_volumetrico_kg'] = null;
                $datosPedido['peso_cobrado_guia_kg'] = null;
                $datosPedido['numero_cajas'] = null;
                $datosPedido['catalogo_tipo_caja_id'] = null;
            } else {
                $datosPedido['peso_real_kg'] = round($pesoRealTotal, 4);
                $datosPedido['peso_volumetrico_kg'] = round($pesoVolumetricoTotal, 4);
                $datosPedido['peso_cobrado_guia_kg'] = round($pesoCobradoTotal, 4);
                $datosPedido['numero_cajas'] = $numeroCajas;
                $datosPedido['catalogo_tipo_caja_id'] = $cajaPrincipalId;
                $datosPedido['costo_envio'] = $costoEnvio;
                if ($esActualizacion && $cambioPesos) {
                    $datosPedido['total_a_cobrar'] = $totalACobrar;
                }
            }

            $pedido->update($datosPedido);
            if (! $soloRevisiones) {
                $this->totalesEnvio->aplicarAlPedido($pedido->fresh(['cajas', 'zona']));
            }

            $ordenDoc = (int) $pedido->documentos()->max('orden') + 1;
            foreach ($evidenciasGenerales as $file) {
                $this->guardarEvidencia(
                    $pedido,
                    $file,
                    PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    $ordenDoc++,
                    PedidoBmaDocumento::RELACION_REVISION_GENERAL,
                    null,
                    $comentarioGeneral !== '' ? $comentarioGeneral : (
                        $soloRevisiones ? 'Evidencia final del lote (tienda)' : 'Foto del lote'
                    )
                );
            }

            foreach ($cajasCreadas as $idxCaja => $caja) {
                foreach ($evidenciasEnvios[$idxCaja] ?? [] as $file) {
                    $this->guardarEvidencia(
                        $pedido,
                        $file,
                        PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        $ordenDoc++,
                        PedidoBmaDocumento::RELACION_ENVIO_CAJA,
                        $caja->id,
                        'Contenido envío '.($idxCaja + 1)
                    );
                }
            }

            foreach ($revisiones as $i => $rev) {
                $rowId = $productoUuidARevisionId[$rev['client_uuid'] ?? '']
                    ?? $productoUuidARevisionId['idx:'.$i]
                    ?? null;
                if (! $rowId) {
                    continue;
                }
                foreach ($rev['evidencias'] as $file) {
                    $this->guardarEvidencia(
                        $pedido,
                        $file,
                        PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        $ordenDoc++,
                        PedidoBmaDocumento::RELACION_REVISION_PRODUCTO,
                        $rowId,
                        $rev['comentario'] ?: $rev['descripcion_producto']
                    );
                }
            }

            $ordenDoc = $this->sesionEvidencia->promover(
                $pedido,
                $productoUuidARevisionId,
                $cajaUuidACajaId,
                $ordenDoc
            );

            $detalleHist = $soloRevisiones
                ? sprintf(
                    'Consulta de mercancía respondida: %d producto(s), evidencia final del lote. Estado físico: %s.%s',
                    count($revisiones),
                    PedidoBmaRevisionProducto::LABELS[$estadoGeneral] ?? $estadoGeneral,
                    $tieneSinExistencia
                        ? ' Sin existencias — pedido detenido hasta que Ventas elija acción.'
                        : ($tieneObs ? ' Con observaciones — Ventas debe revisar.' : '')
                )
                : sprintf(
                    'Pesaje CEDIS respondido: %.4f kg cobrados, %d envío(s). Estado físico: %s.%s',
                    $pesoCobradoTotal,
                    $numeroCajas,
                    PedidoBmaRevisionProducto::LABELS[$estadoGeneral] ?? $estadoGeneral,
                    $tieneSinExistencia
                        ? ' Sin existencias — pedido detenido hasta que Ventas elija acción.'
                        : ($tieneObs ? ' Con observaciones — Ventas debe revisar.' : '')
                );

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatusNuevo->id,
                $detalleHist,
                AccionesHistorialPedidoBma::RESPUESTA_PESAJE
            );

            $folioQ = $pedido->folio_remision ?: $pedido->folio ?: '';
            $fresh = $pedido->fresh();
            if ($tieneSinExistencia) {
                $this->notificarService->ejecutar(
                    $fresh,
                    'pedido_sin_existencia',
                    'CEDIS reportó producto sin existencias. El pedido está detenido hasta que elijas una acción.',
                    [],
                    $usuarioId,
                    true,
                    [
                        'url' => '/control-pedidos?tab=SIN_EXISTENCIA'.($folioQ !== '' ? '&q='.rawurlencode($folioQ) : ''),
                        'con_sin_existencia' => true,
                        'con_observaciones_fisicas' => true,
                    ]
                );
            } else {
                $tituloNotif = $soloRevisiones
                    ? ($tieneObs
                        ? 'CEDIS respondió la consulta de mercancía con observaciones'
                        : 'CEDIS respondió la consulta de mercancía')
                    : ($tieneObs
                        ? 'CEDIS respondió el pesaje con observaciones físicas'
                        : 'CEDIS respondió el pesaje de tu pedido');
                $urlNotif = $tieneObs
                    ? '/control-pedidos?tab=OBS_CEDIS'.($folioQ !== '' ? '&q='.rawurlencode($folioQ) : '')
                    : '/control-pedidos'.($folioQ !== '' ? '?q='.rawurlencode($folioQ) : '');

                $this->notificarService->ejecutar(
                    $fresh,
                    'pedido_pesaje_listo',
                    $tituloNotif,
                    [],
                    $usuarioId,
                    true,
                    [
                        'url' => $urlNotif,
                        'con_observaciones_fisicas' => $tieneObs,
                    ]
                );
            }

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja', 'tipoGuia',
                'pesajeRespondidoPor', 'revisionesProducto',
            ]);
        });
    }

    private function guardarEvidencia(
        PedidoBma $pedido,
        UploadedFile $file,
        string $tipo,
        int $orden,
        ?string $relacionTipo,
        ?int $relacionId,
        ?string $comentario
    ): void {
        $cajaId = $relacionTipo === PedidoBmaDocumento::RELACION_ENVIO_CAJA ? $relacionId : null;
        $ruta = $file->store('pedidos_bma/'.$pedido->id, 'public');
        $pedido->documentos()->create([
            'tipo' => $tipo,
            'ruta_archivo' => $ruta,
            'nombre_original' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'tamano_bytes' => $file->getSize(),
            'orden' => $orden,
            'comentario' => $comentario,
            'relacion_tipo' => $relacionTipo,
            'relacion_id' => $relacionId,
            'pedido_bma_caja_id' => $cajaId,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $revisiones
     * @param  array<string, int>  $productoUuidARevisionId
     */
    private function sincronizarRevisiones(PedidoBma $pedido, array $revisiones, array &$productoUuidARevisionId): void
    {
        $existentes = PedidoBmaRevisionProducto::query()
            ->where('pedido_bma_id', $pedido->id)
            ->orderBy('orden')
            ->get();
        $usados = [];

        foreach ($revisiones as $i => $rev) {
            $match = $existentes->first(function (PedidoBmaRevisionProducto $row) use ($rev, $usados) {
                if (isset($usados[$row->id])) {
                    return false;
                }
                if ($rev['producto_id'] && (int) $row->producto_id === (int) $rev['producto_id']) {
                    return true;
                }

                return $row->descripcion_producto === $rev['descripcion_producto']
                    && (string) $row->sku === (string) ($rev['sku'] ?? '');
            });

            $attrs = [
                'pedido_bma_id' => $pedido->id,
                'orden' => $i,
                'descripcion_producto' => $rev['descripcion_producto'],
                'producto_id' => $rev['producto_id'],
                'sku' => $rev['sku'],
                'estado_fisico' => $rev['estado_fisico'],
                'comentario' => $rev['comentario'],
                'unica_pieza' => $rev['unica_pieza'],
                'mejor_ejemplar' => $rev['mejor_ejemplar'],
            ];

            if ($match) {
                $match->update($attrs);
                $row = $match;
            } else {
                $row = PedidoBmaRevisionProducto::query()->create($attrs);
            }
            $usados[$row->id] = true;
            $productoUuidARevisionId['idx:'.$i] = $row->id;
            if (($rev['client_uuid'] ?? '') !== '') {
                $productoUuidARevisionId[$rev['client_uuid']] = $row->id;
            }
        }

        foreach ($existentes as $row) {
            if (isset($usados[$row->id])) {
                continue;
            }
            $tieneDocs = PedidoBmaDocumento::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('relacion_tipo', PedidoBmaDocumento::RELACION_REVISION_PRODUCTO)
                ->where('relacion_id', $row->id)
                ->exists();
            if ($tieneDocs) {
                continue;
            }
            $row->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCaja
     * @return list<array{
     *   catalogo_tipo_caja_id:int,
     *   largo:float,
     *   ancho:float,
     *   alto:float,
     *   peso_real_kg:float,
     *   peso_volumetrico_kg:float,
     *   client_uuid:string
     * }>
     */
    private function normalizarLineas(array $lineasCaja): array
    {
        $out = [];
        foreach ($lineasCaja as $linea) {
            $tipoId = (int) ($linea['catalogo_tipo_caja_id'] ?? 0);
            $pesoReal = isset($linea['peso_real_kg']) ? (float) $linea['peso_real_kg'] : null;
            $pesoVol = isset($linea['peso_volumetrico_kg']) ? (float) $linea['peso_volumetrico_kg'] : null;
            $largo = isset($linea['largo']) ? (float) $linea['largo'] : null;
            $ancho = isset($linea['ancho']) ? (float) $linea['ancho'] : null;
            $alto = isset($linea['alto']) ? (float) $linea['alto'] : null;

            if ($tipoId <= 0 || $pesoReal === null || $pesoVol === null
                || $largo === null || $ancho === null || $alto === null) {
                continue;
            }
            if ($pesoReal < 0 || $pesoVol < 0 || $largo < 0 || $ancho < 0 || $alto < 0) {
                continue;
            }

            $out[] = [
                'catalogo_tipo_caja_id' => $tipoId,
                'largo' => round($largo, 2),
                'ancho' => round($ancho, 2),
                'alto' => round($alto, 2),
                'peso_real_kg' => round($pesoReal, 4),
                'peso_volumetrico_kg' => round($pesoVol, 4),
                'client_uuid' => trim((string) ($linea['client_uuid'] ?? $linea['uuid_operativo'] ?? '')),
                'uuid_operativo' => trim((string) ($linea['uuid_operativo'] ?? $linea['client_uuid'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return array<int, list<UploadedFile>>
     */
    private function normalizarEvidenciasEnvios(mixed $raw, int $numEnvios): array
    {
        $out = [];
        for ($i = 0; $i < $numEnvios; $i++) {
            $lista = is_array($raw) ? ($raw[$i] ?? []) : [];
            if (! is_array($lista)) {
                $lista = $lista ? [$lista] : [];
            }
            $out[$i] = array_values(array_filter(
                $lista,
                fn ($f) => $f instanceof UploadedFile
            ));
        }

        return $out;
    }

    /**
     * @param  list<array{estado_fisico:string}>  $revisiones
     */
    private function derivarEstadoGeneral(array $revisiones, string $fallback): string
    {
        $severidad = [
            PedidoBmaRevisionProducto::ESTADO_BUENO => 0,
            PedidoBmaRevisionProducto::ESTADO_REGULAR => 1,
            PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA => 2,
            PedidoBmaRevisionProducto::ESTADO_MALO => 3,
            PedidoBmaRevisionProducto::ESTADO_DANADO => 4,
        ];

        $peor = in_array($fallback, PedidoBmaRevisionProducto::ESTADOS, true)
            ? $fallback
            : PedidoBmaRevisionProducto::ESTADO_BUENO;

        foreach ($revisiones as $rev) {
            $estado = (string) ($rev['estado_fisico'] ?? '');
            if (($severidad[$estado] ?? -1) > ($severidad[$peor] ?? -1)) {
                $peor = $estado;
            }
        }

        return $peor;
    }

    /**
     * @param  list<array<string, mixed>>  $revisiones
     * @return list<array{
     *   descripcion_producto:string,
     *   producto_id:?int,
     *   sku:?string,
     *   estado_fisico:string,
     *   comentario:?string,
     *   unica_pieza:bool,
     *   mejor_ejemplar:bool,
     *   evidencias:list<UploadedFile>
     * }>
     */
    private function normalizarRevisiones(array $revisiones): array
    {
        $out = [];
        foreach ($revisiones as $rev) {
            $desc = trim((string) ($rev['descripcion_producto'] ?? ''));
            $estado = (string) ($rev['estado_fisico'] ?? '');
            if ($desc === '' || ! in_array($estado, PedidoBmaRevisionProducto::ESTADOS, true)) {
                continue;
            }
            $comentario = trim((string) ($rev['comentario'] ?? ''));
            $evidencias = array_values(array_filter(
                $rev['evidencias'] ?? [],
                fn ($f) => $f instanceof UploadedFile
            ));

            if (PedidoBmaRevisionProducto::requiereComentario($estado) && $comentario === '') {
                $motivo = $estado === PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
                    ? 'sin existencias'
                    : 'malo/dañado';
                throw new \InvalidArgumentException(
                    "El producto «{$desc}» en estado {$motivo} requiere comentario."
                );
            }
            if (PedidoBmaRevisionProducto::requiereEvidencia($estado) && $evidencias === []) {
                $uuid = trim((string) ($rev['client_uuid'] ?? ''));
                // ponytail: la foto de sesión se valida con el pedido en ejecutar(); aquí solo archivos locales.
                if ($uuid === '') {
                    throw new \InvalidArgumentException(
                        "El producto «{$desc}» en estado malo/dañado requiere evidencia."
                    );
                }
            }

            $sku = trim((string) ($rev['sku'] ?? ''));
            $productoId = isset($rev['producto_id']) && $rev['producto_id'] !== '' && $rev['producto_id'] !== null
                ? (int) $rev['producto_id']
                : null;

            $out[] = [
                'descripcion_producto' => mb_substr($desc, 0, 255),
                'producto_id' => $productoId && $productoId > 0 ? $productoId : null,
                'sku' => $sku !== '' ? mb_substr($sku, 0, 64) : null,
                'estado_fisico' => $estado,
                'comentario' => $comentario !== '' ? $comentario : null,
                'unica_pieza' => filter_var($rev['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mejor_ejemplar' => filter_var($rev['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'evidencias' => $evidencias,
                'client_uuid' => trim((string) ($rev['client_uuid'] ?? '')),
            ];
        }

        return $out;
    }
}
