<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use InvalidArgumentException;
use RuntimeException;

class ActualizarCostosCajasPedidoBmaService
{
    public function __construct(
        private EnviosPedidoBmaConfig $config,
        private CalcularTotalesEnvioPedidoService $totales,
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public function ejecutar(
        PedidoBma $pedido,
        array $lineas,
        int $usuarioId,
        bool $reabrirPago = false,
        ?string $motivoReapertura = null,
    ): PedidoBma {
        if ($lineas === []) {
            return $pedido;
        }

        if ($pedido->pago_validado_at && ! $reabrirPago) {
            throw new RuntimeException(
                'El pago ya está validado. Solicite reapertura autorizada para cambiar costos.'
            );
        }

        if ($pedido->pago_validado_at && $reabrirPago) {
            $motivo = trim((string) $motivoReapertura);
            if ($motivo === '') {
                throw new InvalidArgumentException('Indique el motivo de reapertura del pago.');
            }
        }

        $cajas = PedidoBmaCaja::query()
            ->where('pedido_bma_id', $pedido->id)
            ->activas()
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (PedidoBmaCaja $c) => (string) $c->uuid_operativo);

        $actualizadas = 0;

        foreach ($lineas as $linea) {
            $uuid = trim((string) ($linea['uuid_operativo'] ?? ''));
            $caja = $cajas->get($uuid);
            if (! $caja) {
                throw new InvalidArgumentException('Un envío no pertenece a este pedido.');
            }
            if ($caja->estaRecolectada() && ! $this->config->editarTrasRecoleccion()) {
                throw new RuntimeException('No se puede cambiar el costo de un envío ya recolectado.');
            }

            $envio = $this->montoNoNegativo($linea['costo_envio'] ?? null);
            $seguro = $this->montoNoNegativo($linea['costo_seguro'] ?? null);
            $adicional = $this->montoNoNegativo($linea['costo_adicional'] ?? null);
            $conceptoIn = array_key_exists('concepto_adicional', $linea)
                ? trim((string) $linea['concepto_adicional'])
                : null;

            // No fingir actualización con payload vacío (deja null + costos_actualizados_*).
            if ($envio === null && $seguro === null && $adicional === null
                && ($conceptoIn === null || $conceptoIn === '')) {
                continue;
            }

            $caja->update([
                'costo_envio' => $envio,
                'costo_seguro' => $seguro,
                'costo_adicional' => $adicional,
                'concepto_adicional' => $conceptoIn !== null
                    ? ($conceptoIn === '' ? null : $conceptoIn)
                    : $caja->concepto_adicional,
                'moneda' => $this->config->moneda(),
                'costos_actualizados_at' => now(),
                'costos_actualizados_por_id' => $usuarioId,
            ]);
            $actualizadas++;
        }

        if ($actualizadas === 0) {
            return $pedido;
        }

        $pedido = $pedido->fresh(['cajas', 'zona', 'estatus']);
        $totales = $this->totales->aplicarAlPedido($pedido);

        if ($pedido->pago_validado_at && $reabrirPago) {
            $pedido->update([
                'pago_validado_at' => null,
                'pago_validado_por_id' => null,
            ]);
            $this->historial->ejecutar(
                $pedido->id,
                $usuarioId,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Validación de pago anulada por cambio de costos. Motivo: '.$motivoReapertura,
                AccionesHistorialPedidoBma::REAPERTURA_PAGO_COSTOS
            );
            $this->notificar->ejecutar(
                $pedido->fresh(),
                'pedido_pago_reabierto',
                'Se modificaron costos de envío. Debe volver a validar el pago.',
                ['control_pedidos.auditar'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/auditar?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio))]
            );
        } else {
            $this->historial->ejecutar(
                $pedido->id,
                $usuarioId,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Costos de envío actualizados. Total '.$totales['costo_para_cobertura'].' MXN.',
                AccionesHistorialPedidoBma::COSTOS_ENVIO
            );
        }

        return $pedido->fresh(['cajas.tipoCaja', 'cajas.tipoGuia', 'zona']);
    }

    private function montoNoNegativo(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $n = round((float) $valor, $this->config->precision());
        if ($n < 0) {
            throw new InvalidArgumentException('Los importes de envío no pueden ser negativos.');
        }

        return number_format($n, $this->config->precision(), '.', '');
    }
}
