<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use Illuminate\Support\Collection;

/**
 * Única fuente de totales de envío. No reparte un costo histórico entre cajas.
 */
class CalcularTotalesEnvioPedidoService
{
    public const FUENTE_DETALLE = 'detalle_cajas';

    public const FUENTE_LEGADO = 'legado';

    public function __construct(
        private EnviosPedidoBmaConfig $config,
    ) {}

    /**
     * @return array{
     *   fuente: string,
     *   incompleto: bool,
     *   requiere_desglose: bool,
     *   cajas_activas: int,
     *   cajas_con_costo: int,
     *   costo_envios: string,
     *   costo_seguro: string,
     *   costo_adicional: string,
     *   costo_para_cobertura: string,
     *   moneda: string
     * }
     */
    public function calcular(PedidoBma $pedido): array
    {
        $pedido->loadMissing(['cajas', 'tipoOperacionEnvio']);
        $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $c) => $c->estaActiva())->values();
        $conCosto = $activas->filter(fn (PedidoBmaCaja $c) => $c->tieneDesgloseCosto());
        $precision = $this->config->precision();

        $completo = $activas->isNotEmpty() && $conCosto->count() === $activas->count();
        $parcial = $conCosto->isNotEmpty() && ! $completo;
        $requiereDesglose = $this->requiereDesgloseCajas($pedido, $activas);

        if ($completo) {
            $enviosCent = 0;
            $seguroCent = 0;
            $adicionalCent = 0;
            foreach ($activas as $caja) {
                $enviosCent += PagosPedidoBmaConfig::aCentavos((string) $caja->costo_envio);
                $seguroCent += PagosPedidoBmaConfig::aCentavos((string) ($caja->costo_seguro ?? '0'));
                $adicionalCent += PagosPedidoBmaConfig::aCentavos((string) ($caja->costo_adicional ?? '0'));
            }
            $paraCobertura = $enviosCent + $adicionalCent;

            return [
                'fuente' => self::FUENTE_DETALLE,
                'incompleto' => false,
                'requiere_desglose' => $requiereDesglose,
                'cajas_activas' => $activas->count(),
                'cajas_con_costo' => $conCosto->count(),
                'costo_envios' => PagosPedidoBmaConfig::centavosADecimal($enviosCent),
                'costo_seguro' => PagosPedidoBmaConfig::centavosADecimal($seguroCent),
                'costo_adicional' => PagosPedidoBmaConfig::centavosADecimal($adicionalCent),
                'costo_para_cobertura' => PagosPedidoBmaConfig::centavosADecimal($paraCobertura),
                'moneda' => $this->config->moneda(),
            ];
        }

        $legadoEnvio = $pedido->costo_envio === null || $pedido->costo_envio === ''
            ? '0.00'
            : number_format((float) $pedido->costo_envio, $precision, '.', '');
        $legadoSeguro = number_format((float) ($pedido->costo_seguro ?? 0), $precision, '.', '');

        // Con detalle_cajas activo y pesaje: 0/N o parcial bloquean finanzas (no legado silencioso).
        $incompleto = $parcial || ($requiereDesglose && $activas->isNotEmpty() && ! $completo);

        return [
            'fuente' => self::FUENTE_LEGADO,
            'incompleto' => $incompleto,
            'requiere_desglose' => $requiereDesglose,
            'cajas_activas' => $activas->count(),
            'cajas_con_costo' => $conCosto->count(),
            'costo_envios' => $legadoEnvio,
            'costo_seguro' => $legadoSeguro,
            'costo_adicional' => number_format(0, $precision, '.', ''),
            'costo_para_cobertura' => $legadoEnvio,
            'moneda' => $this->config->moneda(),
        ];
    }

    /**
     * Desglose por caja obligatorio tras pesaje cuando la UI de detalle está activa,
     * salvo caminos que aplazan o anulan el costo (anexo, por cobrar, guía cliente, etc.).
     *
     * @param  Collection<int, PedidoBmaCaja>|null  $activas
     */
    public function requiereDesgloseCajas(PedidoBma $pedido, ?Collection $activas = null): bool
    {
        if (! $this->config->detalleCajas()) {
            return false;
        }

        if ($pedido->cliente_proporciona_guia || $pedido->envio_por_cobrar) {
            return false;
        }

        $pedido->loadMissing('tipoOperacionEnvio');
        if ($pedido->esMunicipioDiferido() || $pedido->esResguardoAbierto() || $pedido->esResguardoComplementario()) {
            return false;
        }

        if (! $pedido->tienePesajeRespondido()) {
            return false;
        }

        if ($activas === null) {
            $pedido->loadMissing('cajas');
            $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $c) => $c->estaActiva())->values();
        }

        return $activas->isNotEmpty();
    }

    /**
     * Escribe pedidos_bma.costo_envio solo cuando el desglose está completo.
     * No inventa montos si el desglose está incompleto.
     *
     * @return array<string, mixed>
     */
    public function aplicarAlPedido(PedidoBma $pedido): array
    {
        $totales = $this->calcular($pedido);

        if ($totales['fuente'] !== self::FUENTE_DETALLE) {
            return $totales;
        }

        $mercancia = (float) ($pedido->total_mercancia ?? 0);
        $envio = (float) $totales['costo_para_cobertura'];
        // aplica_seguro es la fuente de verdad: no reactivar por monto residual.
        $aplicaSeguro = (bool) $pedido->aplica_seguro;
        $seguro = $aplicaSeguro ? (float) $totales['costo_seguro'] : 0.0;
        $saldo = (float) ($pedido->saldo_a_favor ?? 0);
        $precision = $this->config->precision();

        $pedido->update([
            'costo_envio' => $totales['costo_para_cobertura'],
            'costo_seguro' => $aplicaSeguro
                ? $totales['costo_seguro']
                : number_format(0, $precision, '.', ''),
            'aplica_seguro' => $aplicaSeguro,
            'total_a_cobrar' => PedidoBma::calcularTotal($mercancia, $envio, $aplicaSeguro, $seguro, $saldo),
        ]);

        return $totales;
    }
}
