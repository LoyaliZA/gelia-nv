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
    public function __construct(
        private GenerarCreditoSafService $generarCredito,
        private RegistrarHistorialPedidoService $historial,
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

            $this->generarExcedenteSiAplica($pedido->fresh(), $usuarioId);

            return $pago->fresh(['banco']);
        });
    }

    /**
     * El total debe estar cubierto (exhibiciones + SAF) antes de enviar a auditar.
     * Cada exhibición registrada debe traer comprobante.
     */
    public function assertCubiertoParaEnviar(PedidoBma $pedido): void
    {
        $pagos = PedidoBmaPago::where('pedido_bma_id', $pedido->id)->get();

        foreach ($pagos as $pago) {
            if (empty($pago->ruta_archivo)) {
                throw new InvalidArgumentException(
                    'Cada exhibición de pago debe incluir su comprobante.'
                );
            }
        }

        $resumen = $this->resumenPago($pedido);
        if ((float) $resumen['pendiente'] > 0.01) {
            throw new InvalidArgumentException(
                'El pago debe cubrir el total del pedido antes de enviar. Pendiente: $'
                .number_format((float) $resumen['pendiente'], 2, '.', '')
                .'. Registre las exhibiciones (uno o varios bancos/métodos) hasta cubrir el total.'
            );
        }
    }

    public function generarExcedenteSiAplica(PedidoBma $pedido, ?int $usuarioId = null): ?SafCredito
    {
        if (! $pedido->cliente_id) {
            return null;
        }

        $resumen = $this->resumenPago($pedido);
        $excedente = (float) ($resumen['excedente'] ?? 0);
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
                'detalle_motivo' => 'Excedente de pagos del pedido',
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

    public function resumenPago(PedidoBma $pedido, float $saldosAplicados = 0): array
    {
        $pagos = PedidoBmaPago::where('pedido_bma_id', $pedido->id)->get();
        $recibido = round((float) $pagos->sum('monto'), 2);
        $totalFinal = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);
        $subtotal = round($totalFinal, 2);
        $saldos = $saldosAplicados > 0 ? $saldosAplicados : (float) ($pedido->saldo_a_favor ?? 0);
        $pendiente = round($subtotal - $saldos - $recibido, 2);
        $excedente = round(max($recibido + $saldos - $subtotal, 0), 2);

        $cobertura = 'sin_pago';
        if ($recibido <= 0 && $saldos <= 0) {
            $cobertura = 'sin_pago';
        } elseif ($pendiente > 0.01) {
            $cobertura = 'parcial';
        } elseif ($excedente > 0.01) {
            $cobertura = 'con_excedente';
        } else {
            $cobertura = 'cubierto';
        }

        $revision = $this->agregarRevision($pagos);

        // Compatibilidad temporal con callers que leen estado_pago combinado.
        $estadoPago = match (true) {
            $cobertura === 'sin_pago' => 'sin_pago',
            $cobertura === 'parcial' => 'parcialmente_pagado',
            $cobertura === 'con_excedente' => 'sobrepagado',
            $revision === 'verificado' => 'pagado_revisado',
            default => 'cubierto_pendiente_revision',
        };

        return [
            'total_final' => $subtotal,
            'saldos_aplicados' => $saldos,
            'total_recibido' => $recibido,
            'pendiente' => max($pendiente, 0),
            'excedente' => $excedente,
            'cobertura' => $cobertura,
            'revision' => $revision,
            'fuentes_pago' => $pedido->fuentesPagoResumen(),
            'estado_pago' => $estadoPago,
            'nuevo_saldo_sugerido' => $excedente,
        ];
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
