<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAnexoEnvio;
use App\Services\SaldosAFavor\ReconciliarTotalPedidoSafService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class LiberarResguardoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private ReconciliarTotalPedidoSafService $reconciliarSaf,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, ?array $captura = null, ?UploadedFile $comprobante = null): PedidoBma
    {
        if (!$pedido->es_resguardo) {
            throw new \InvalidArgumentException('Este pedido no está en resguardo.');
        }

        $fase = $pedido->estatus?->fase_ciclo;
        $enPendienteAuxiliar = $fase === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR;
        $enCedisConResguardo = $fase === CatalogoEstatusPedido::FASE_EN_CEDIS;

        if (!$enPendienteAuxiliar && !$enCedisConResguardo) {
            throw new \RuntimeException('Solo se puede liberar resguardo en pedidos pendientes de revisión o en CEDIS.');
        }

        $pedido->loadMissing('tipoOperacionEnvio');

        if ($pedido->esResguardoAbierto() || $pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION) {
            return $this->liberarConCaptura($pedido, $usuarioId, $enPendienteAuxiliar, $captura ?? [], $comprobante);
        }

        return $this->liberarLegacy($pedido, $usuarioId, $enPendienteAuxiliar);
    }

    private function liberarConCaptura(
        PedidoBma $pedido,
        int $usuarioId,
        bool $enPendienteAuxiliar,
        array $captura,
        ?UploadedFile $comprobante
    ): PedidoBma {
        $usaPesaje = $pedido->tienePesajeRespondido();

        $peso = $usaPesaje
            ? ($pedido->peso_real_kg !== null ? (float) $pedido->peso_real_kg : null)
            : (isset($captura['peso_real_kg']) && $captura['peso_real_kg'] !== '' && $captura['peso_real_kg'] !== null
                ? (float) $captura['peso_real_kg']
                : null);
        $cajas = $usaPesaje
            ? ($pedido->numero_cajas !== null ? (int) $pedido->numero_cajas : null)
            : (isset($captura['numero_cajas']) && $captura['numero_cajas'] !== '' && $captura['numero_cajas'] !== null
                ? (int) $captura['numero_cajas']
                : null);
        $costo = isset($captura['costo_envio']) && $captura['costo_envio'] !== '' && $captura['costo_envio'] !== null
            ? (float) $captura['costo_envio']
            : null;

        if ($peso === null || $peso < 0) {
            throw new \InvalidArgumentException(
                $usaPesaje
                    ? 'El pesaje CEDIS no tiene peso válido; solicite re-pesaje.'
                    : 'El peso real es obligatorio al liberar el resguardo abierto.'
            );
        }
        if ($cajas === null || $cajas < 0) {
            throw new \InvalidArgumentException(
                $usaPesaje
                    ? 'El pesaje CEDIS no tiene cajas válidas; solicite re-pesaje.'
                    : 'El número de cajas es obligatorio al liberar el resguardo abierto.'
            );
        }
        if ($costo === null || $costo <= 0) {
            throw new \InvalidArgumentException('El costo de envío debe ser mayor que cero.');
        }
        if (!$comprobante || !$comprobante->isValid()) {
            throw new \InvalidArgumentException('El comprobante de envío es obligatorio.');
        }
        if (empty($captura['catalogo_banco_id'])) {
            throw new \InvalidArgumentException('El banco del pago de envío es obligatorio.');
        }
        if ($pedido->anexosEnvio()->where('estatus', PedidoBmaAnexoEnvio::ESTATUS_PENDIENTE)->exists()) {
            throw new \RuntimeException('Ya existe un anexo de envío pendiente de revisión.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $enPendienteAuxiliar, $captura, $comprobante, $peso, $cajas, $costo) {
            $estatusAnterior = $pedido->estatus;
            $listoParaCedis = $pedido->tienePagoValidado() && $pedido->tieneRemision();
            $totalAntes = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);
            $mercancia = (float) $pedido->total_mercancia;
            $seguro = (bool) $pedido->aplica_seguro;
            $costoSeguro = (float) ($pedido->costo_seguro ?? 0);
            $saldoFavor = (float) ($pedido->saldo_a_favor ?? 0);

            $attrs = [
                'es_resguardo' => false,
                'peso_real_kg' => $peso,
                'peso_cobrado_guia_kg' => PedidoBma::calcularPesoCobradoGuia(
                    $peso,
                    $pedido->peso_volumetrico_kg !== null ? (float) $pedido->peso_volumetrico_kg : null
                ),
                'numero_cajas' => $cajas,
                'costo_envio' => $costo,
                'total_a_cobrar' => PedidoBma::calcularTotal($mercancia, $costo, $seguro, $costoSeguro, $saldoFavor),
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO,
            ];

            $comentarioHistorial = sprintf(
                'Resguardo abierto liberado con captura de envío ($%s). Anexo pendiente de revisión.',
                number_format($costo, 2, '.', ',')
            );

            if ($enPendienteAuxiliar && $listoParaCedis) {
                $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS)
                    ?? CatalogoEstatusPedido::porCodigo('AMARILLO');

                if (!$estatusNuevo) {
                    throw new \RuntimeException('No se encontró el estatus EN_CEDIS.');
                }

                $attrs['catalogo_estatus_pedido_id'] = $estatusNuevo->id;
                $pedido->update($attrs);

                $this->reconciliarSaf->handle(
                    $pedido->fresh(),
                    $totalAntes,
                    $usuarioId,
                    'envio_a_resguardo',
                    'Reconciliación tras liberar resguardo con captura'
                );

                $this->historialService->registrarTransicion(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior,
                    $estatusNuevo,
                    $comentarioHistorial.' Pedido enviado a CEDIS.',
                    AccionesHistorialPedidoBma::LIBERAR_RESGUARDO
                );

                $pasoACedis = true;
            } else {
                $pedido->update($attrs);

                $this->reconciliarSaf->handle(
                    $pedido->fresh(),
                    $totalAntes,
                    $usuarioId,
                    'envio_a_resguardo',
                    'Reconciliación tras liberar resguardo con captura'
                );

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior->id,
                    $estatusAnterior->id,
                    $enPendienteAuxiliar
                        ? $comentarioHistorial
                        : $comentarioHistorial.' Pedido pendiente de empaque.',
                    AccionesHistorialPedidoBma::LIBERAR_RESGUARDO
                );

                $pasoACedis = false;
            }

            $ruta = $comprobante->store("pedidos_bma/anexos_envio/{$pedido->id}", 'public');
            $pedido->anexosEnvio()->create([
                'monto' => $costo,
                'catalogo_banco_id' => (int) $captura['catalogo_banco_id'],
                'comentarios' => $captura['comentarios'] ?? 'Pago de envío al liberar resguardo abierto.',
                'ruta_archivo' => $ruta,
                'nombre_original' => $comprobante->getClientOriginalName(),
                'mime_type' => $comprobante->getMimeType(),
                'tamano_bytes' => $comprobante->getSize(),
                'estatus' => PedidoBmaAnexoEnvio::ESTATUS_PENDIENTE,
                'registrado_por_id' => $usuarioId,
            ]);

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'origen', 'tipoOperacionEnvio', 'documentos',
                'almacen', 'banco', 'direccionVigente', 'anexosEnvio.banco', 'vendedor',
            ]);

            if ($pasoACedis) {
                $this->notificarResguardoLiberado($pedido, $usuarioId);
            }

            return $pedido;
        });
    }

    private function liberarLegacy(PedidoBma $pedido, int $usuarioId, bool $enPendienteAuxiliar): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuarioId, $enPendienteAuxiliar) {
            $estatusAnterior = $pedido->estatus;
            $listoParaCedis = $pedido->tienePagoValidado() && $pedido->tieneRemision();

            if (!$enPendienteAuxiliar) {
                $pedido->update(['es_resguardo' => false]);

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior->id,
                    $estatusAnterior->id,
                    'Resguardo liberado. Pedido pendiente de empaque.',
                    AccionesHistorialPedidoBma::LIBERAR_RESGUARDO
                );

                return $pedido->fresh(['cliente', 'estatus', 'origen', 'documentos', 'almacen', 'banco', 'direccionVigente']);
            }

            if ($listoParaCedis) {
                $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS)
                    ?? CatalogoEstatusPedido::porCodigo('AMARILLO');

                if (!$estatusNuevo) {
                    throw new \RuntimeException('No se encontró el estatus EN_CEDIS.');
                }

                $pedido->update([
                    'es_resguardo' => false,
                    'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                ]);

                $this->historialService->registrarTransicion(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior,
                    $estatusNuevo,
                    'Resguardo liberado; pedido enviado a CEDIS.',
                    AccionesHistorialPedidoBma::LIBERAR_RESGUARDO
                );

                $pedido = $pedido->fresh(['cliente', 'estatus', 'origen', 'documentos', 'almacen', 'banco', 'direccionVigente', 'vendedor']);
                $this->notificarResguardoLiberado($pedido, $usuarioId);

                return $pedido;
            }

            $pedido->update(['es_resguardo' => false]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusAnterior->id,
                $estatusAnterior->id,
                'Resguardo liberado por el auxiliar.',
                AccionesHistorialPedidoBma::LIBERAR_RESGUARDO
            );

            return $pedido->fresh(['cliente', 'estatus', 'origen', 'documentos', 'almacen', 'banco', 'direccionVigente']);
        });
    }

    private function notificarResguardoLiberado(PedidoBma $pedido, int $usuarioId): void
    {
        $this->notificarService->ejecutar(
            $pedido,
            'pedido_resguardo_liberado',
            'Resguardo liberado; pedido listo para CEDIS',
            ['control_pedidos.cedis'],
            $usuarioId,
            false,
            ['url' => '/control-pedidos/cedis?tab=EMPACADOS&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
        );
    }
}
