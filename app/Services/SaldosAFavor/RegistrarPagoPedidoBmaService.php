<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarPagoPedidoBmaService
{
    public const FASE_ENVIAR = 'enviar';

    public const FASE_VALIDAR = 'validar';

    public const FASE_APROBAR = 'aprobar';

    public function __construct(
        private GenerarCreditoSafService $generarCredito,
        private RegistrarHistorialPedidoService $historial,
        private SincronizarAplicacionesPedidoSafService $safPedido,
        private CancelarCreditoSafService $cancelarCredito,
    ) {}

    public function handle(PedidoBma $pedido, array $datos, ?UploadedFile $comprobante = null, ?int $usuarioId = null): PedidoBmaPago
    {
        $monto = round((float) ($datos['monto'] ?? 0), 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto de la exhibición debe ser mayor a cero.');
        }

        $forma = $datos['forma_pago'] ?? null;
        if ($forma !== null && $forma !== '' && ! in_array($forma, PedidoBmaPago::FORMAS_PAGO, true)) {
            throw new InvalidArgumentException('Forma de pago no válida.');
        }

        $bancoId = isset($datos['catalogo_banco_id']) && $datos['catalogo_banco_id'] !== ''
            ? (int) $datos['catalogo_banco_id']
            : null;

        if (PedidoBmaPago::formaRequiereBanco($forma) && ! $bancoId) {
            throw new InvalidArgumentException('Esta forma de pago requiere banco receptor.');
        }

        if (! PedidoBmaPago::formaRequiereBanco($forma)) {
            $bancoId = null;
        }

        if (! $comprobante || ! $comprobante->isValid()) {
            throw new InvalidArgumentException('Cada exhibición de pago debe incluir su comprobante.');
        }

        return DB::transaction(function () use ($pedido, $datos, $comprobante, $usuarioId, $monto, $forma, $bancoId) {
            $siguiente = ((int) PedidoBmaPago::where('pedido_bma_id', $pedido->id)->max('numero_exhibicion')) + 1;

            $ruta = $comprobante->store("pedidos_bma/pagos/{$pedido->id}", 'public');

            $pago = PedidoBmaPago::create([
                'pedido_bma_id' => $pedido->id,
                'numero_exhibicion' => $siguiente,
                'monto' => $monto,
                'catalogo_banco_id' => $bancoId,
                'forma_pago' => $forma,
                'fecha_pago' => $datos['fecha_pago'] ?? now(),
                'referencia' => $datos['referencia'] ?? null,
                'capturado_por_id' => $usuarioId,
                'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
                'observaciones' => $datos['observaciones'] ?? null,
                'ruta_archivo' => $ruta,
                'nombre_original' => $comprobante->getClientOriginalName(),
                'mime_type' => $comprobante->getMimeType(),
                'tamano_bytes' => $comprobante->getSize(),
            ]);

            if ($usuarioId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    sprintf(
                        'Exhibición #%d por $%s (%s).',
                        $siguiente,
                        number_format($monto, 2, '.', ','),
                        PedidoBmaPago::labelForma($forma) ?? 'sin método'
                    ),
                    AccionesHistorialPedidoBma::ALTA_EXHIBICION_PAGO,
                    ['ruta' => $ruta, 'nombre' => $comprobante->getClientOriginalName()]
                );
            }

            $this->reconciliarExcedenteTrasExhibicion($pedido->fresh(), $usuarioId);

            return $pago->fresh(['banco']);
        });
    }

    /**
     * El total debe estar cubierto (exhibiciones + SAF) antes de enviar a auditar.
     * Cada exhibición registrada debe traer comprobante.
     */
    public function assertCubiertoParaEnviar(PedidoBma $pedido): void
    {
        $this->assertPagoListoParaAvanzar($pedido, self::FASE_ENVIAR);
    }

    /**
     * @param  self::FASE_*  $fase
     */
    public function assertPagoListoParaAvanzar(PedidoBma $pedido, string $fase = self::FASE_ENVIAR): void
    {
        $this->assertSafCorresponde($pedido);

        $pagos = PedidoBmaPago::where('pedido_bma_id', $pedido->id)->get();
        $resumen = $this->resumenPago($pedido);
        $neto = (float) $resumen['total_a_cobrar'];
        $pendiente = (float) $resumen['pendiente'];

        if ($neto > 0.01 && $pagos->isEmpty()) {
            throw new InvalidArgumentException(
                'Falta registrar exhibiciones de pago. '.self::mensajeMontoFaltante($pendiente)
            );
        }

        foreach ($pagos as $pago) {
            if (empty($pago->ruta_archivo)) {
                throw new InvalidArgumentException(
                    'Cada exhibición de pago debe incluir su comprobante.'
                );
            }
        }

        if ($pendiente > 0.01) {
            throw new InvalidArgumentException(self::mensajeMontoFaltante($pendiente));
        }

        if (in_array($fase, [self::FASE_VALIDAR, self::FASE_APROBAR], true) && $pagos->isNotEmpty()) {
            $todasVerificadas = $pagos->every(
                fn (PedidoBmaPago $p) => $p->estado_revision === PedidoBmaPago::REVISION_VERIFICADO
            );
            if (! $todasVerificadas) {
                throw new InvalidArgumentException(
                    'Todas las exhibiciones deben estar verificadas antes de validar el pago. Corrija comprobantes rechazados u observados.'
                );
            }
        }
    }

    public static function mensajeMontoFaltante(float $pendiente): string
    {
        return sprintf(
            'El total a cubrir no está completo. Faltan $%s. Registre exhibiciones hasta cubrir mercancía, envío y seguro (menos el saldo a favor aplicado).',
            number_format(max(0, $pendiente), 2, '.', ',')
        );
    }

    /**
     * Fórmula canónica de cobertura. Sin I/O.
     *
     * @return array{
     *   total_a_cubrir:float,
     *   saldo_a_favor_aplicado:float,
     *   total_a_cobrar:float,
     *   total_pagado:float,
     *   pendiente:float,
     *   excedente_generado:float,
     *   cobertura:string,
     *   total_final:float,
     *   saldos_aplicados:float,
     *   total_recibido:float,
     *   excedente:float,
     *   nuevo_saldo_sugerido:float
     * }
     */
    public static function calcularResumenCobertura(
        float $mercancia,
        float $envio,
        bool $aplicaSeguro,
        float $costoSeguro,
        float $saldoAFavor,
        float $totalPagado,
    ): array {
        $round2 = static fn (float $n): float => round($n, 2);

        $totalACubrir = $round2($mercancia + $envio + ($aplicaSeguro ? $costoSeguro : 0));
        $saf = $round2(max(0.0, $saldoAFavor));
        $totalACobrar = max(0.0, $round2($totalACubrir - $saf));
        $pagado = $round2(max(0.0, $totalPagado));
        $delta = $round2($totalACobrar - $pagado);
        $pendiente = max(0.0, $delta);
        $excedente = max(0.0, $round2(-$delta));

        if ($pagado <= 0 && $saf <= 0) {
            $cobertura = 'sin_pago';
        } elseif ($pendiente > 0.01) {
            $cobertura = 'parcial';
        } elseif ($excedente > 0.01) {
            $cobertura = 'con_excedente';
        } else {
            $cobertura = 'cubierto';
        }

        return [
            'total_a_cubrir' => $totalACubrir,
            'saldo_a_favor_aplicado' => $saf,
            'total_a_cobrar' => $totalACobrar,
            'total_pagado' => $pagado,
            'pendiente' => $pendiente,
            'excedente_generado' => $excedente,
            'cobertura' => $cobertura,
            'total_final' => $totalACubrir,
            'saldos_aplicados' => $saf,
            'total_recibido' => $pagado,
            'excedente' => $excedente,
            'nuevo_saldo_sugerido' => $excedente,
        ];
    }

    public function generarExcedenteSiAplica(PedidoBma $pedido, ?int $usuarioId = null): ?SafCredito
    {
        if (! $pedido->cliente_id) {
            return null;
        }

        $resumen = $this->resumenPago($pedido);
        $excedente = (float) ($resumen['excedente_generado'] ?? $resumen['excedente'] ?? 0);
        if ($excedente <= 0.01) {
            return null;
        }

        $motivoId = SafMotivo::where('codigo', 'pago_de_mas')->value('id')
            ?? SafMotivo::where('codigo', 'ajuste_admin')->value('id');
        if (! $motivoId) {
            return null;
        }

        try {
            return $this->generarCredito->handle([
                'cliente_id' => (int) $pedido->cliente_id,
                'monto' => $excedente,
                'saf_motivo_id' => $motivoId,
                'detalle_motivo' => 'Excedente de pagos de este pedido',
                'canal_origen' => 'bellaroma',
                'pedido_bma_id' => $pedido->id,
                'documento_origen' => $pedido->folio_remision ?: $pedido->folio,
                'generado_por_id' => $usuarioId,
                'origen_manual' => false,
            ]);
        } catch (InvalidArgumentException) {
            // Ya existe crédito con el mismo monto para el pedido (idempotente).
            return null;
        }
    }

    public function resumenPago(PedidoBma $pedido): array
    {
        $pagos = PedidoBmaPago::where('pedido_bma_id', $pedido->id)->get();
        $pagado = round((float) $pagos->sum('monto'), 2);
        $envio = $pedido->costo_envio === null || $pedido->costo_envio === ''
            ? 0.0
            : (float) $pedido->costo_envio;

        $base = self::calcularResumenCobertura(
            (float) ($pedido->total_mercancia ?? 0),
            $envio,
            (bool) $pedido->aplica_seguro,
            (float) ($pedido->costo_seguro ?? 0),
            (float) ($pedido->saldo_a_favor ?? 0),
            $pagado,
        );

        $revision = $this->agregarRevision($pagos);
        $cobertura = $base['cobertura'];

        $estadoPago = match (true) {
            $cobertura === 'sin_pago' => 'sin_pago',
            $cobertura === 'parcial' => 'parcialmente_pagado',
            $cobertura === 'con_excedente' => 'sobrepagado',
            $revision === 'verificado' => 'pagado_revisado',
            default => 'cubierto_pendiente_revision',
        };

        return array_merge($base, [
            'revision' => $revision,
            'fuentes_pago' => $pedido->fuentesPagoResumen(),
            'estado_pago' => $estadoPago,
        ]);
    }

    /**
     * Recalcula excedente SAF tras agregar/editar/eliminar exhibiciones.
     * Cancela créditos pago_de_mas huérfanos si ya no hay excedente.
     */
    public function reconciliarExcedenteTrasExhibicion(PedidoBma $pedido, ?int $usuarioId = null): void
    {
        $pedido = $pedido->fresh() ?? $pedido;
        $resumen = $this->resumenPago($pedido);
        $excedente = (float) ($resumen['excedente_generado'] ?? 0);

        $huerfanos = SafCredito::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado_financiero', '!=', SafCredito::ESTADO_CANCELADO)
            ->whereHas('motivo', fn ($q) => $q->where('codigo', 'pago_de_mas'))
            ->get()
            ->filter(fn (SafCredito $c) => (float) $c->monto_reservado < 0.01 && (float) $c->monto_aplicado < 0.01);

        if ($excedente <= 0.01) {
            foreach ($huerfanos as $credito) {
                $this->cancelarCredito->handle(
                    $credito->id,
                    $usuarioId,
                    'Exhibición modificada: ya no hay excedente en este pedido.'
                );
            }

            return;
        }

        $match = $huerfanos->first(
            fn (SafCredito $c) => abs((float) $c->monto_original - $excedente) <= 0.01
        );
        foreach ($huerfanos as $credito) {
            if ($match && (int) $credito->id === (int) $match->id) {
                continue;
            }
            $this->cancelarCredito->handle(
                $credito->id,
                $usuarioId,
                'Exhibición modificada: se recalculó el excedente de este pedido.'
            );
        }

        $this->generarExcedenteSiAplica($pedido->fresh() ?? $pedido, $usuarioId);
    }

    private function assertSafCorresponde(PedidoBma $pedido): void
    {
        $safPedido = round((float) ($pedido->saldo_a_favor ?? 0), 2);
        $envio = $pedido->costo_envio === null || $pedido->costo_envio === ''
            ? 0.0
            : (float) $pedido->costo_envio;
        $totalACubrir = round(
            (float) ($pedido->total_mercancia ?? 0)
            + $envio
            + ($pedido->aplica_seguro ? (float) ($pedido->costo_seguro ?? 0) : 0),
            2
        );

        if ($safPedido > $totalACubrir + 0.01) {
            throw new InvalidArgumentException(
                'El saldo a favor aplicado no puede ser mayor que el total a cubrir (mercancía + envío + seguro).'
            );
        }

        $libro = round($this->safPedido->totalAplicadoOReservado($pedido), 2);
        if ($safPedido <= 0.01 && $libro <= 0.01) {
            return;
        }

        if (abs($safPedido - $libro) > 0.01) {
            throw new InvalidArgumentException(
                'El saldo a favor aplicado no corresponde con los créditos reservados del pedido.'
            );
        }
    }

    /** @param  \Illuminate\Support\Collection<int, PedidoBmaPago>  $pagos */
    private function agregarRevision($pagos): string
    {
        if ($pagos->isEmpty()) {
            return 'sin_pagos';
        }

        $estados = $pagos->pluck('estado_revision')->all();

        if (in_array(PedidoBmaPago::REVISION_RECHAZADO, $estados, true)) {
            return PedidoBmaPago::REVISION_RECHAZADO;
        }
        if (in_array(PedidoBmaPago::REVISION_CON_OBSERVACIONES, $estados, true)) {
            return PedidoBmaPago::REVISION_CON_OBSERVACIONES;
        }
        if (in_array(PedidoBmaPago::REVISION_EN_REVISION, $estados, true)) {
            return PedidoBmaPago::REVISION_EN_REVISION;
        }
        if (collect($estados)->every(fn ($e) => $e === PedidoBmaPago::REVISION_VERIFICADO)) {
            return PedidoBmaPago::REVISION_VERIFICADO;
        }

        return PedidoBmaPago::REVISION_PENDIENTE;
    }
}
