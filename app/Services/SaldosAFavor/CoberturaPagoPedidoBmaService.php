<?php

namespace App\Services\SaldosAFavor;

use App\Models\CatalogoBancoDepartamento;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\PagosPedidoBmaConfig;
use InvalidArgumentException;

/**
 * Cálculo canónico de cobertura de pagos BMA.
 * El frontend nunca decide si el pedido está cubierto.
 */
class CoberturaPagoPedidoBmaService
{
    public function __construct(
        private PagosPedidoBmaConfig $config,
    ) {}

    /**
     * @return array{
     *   total_a_cubrir: string,
     *   saldo_favor_aplicado: string,
     *   pagos_validos: string,
     *   diferencia: string,
     *   tolerancia_aplicada: string,
     *   cubierto: bool,
     *   bloqueos: list<string>,
     *   total_a_cobrar: string,
     *   pendiente: string,
     *   excedente_generado: string,
     *   cobertura: string,
     *   total_final: float,
     *   saldos_aplicados: float,
     *   total_recibido: float,
     *   excedente: float,
     *   nuevo_saldo_sugerido: float,
     *   saldo_a_favor_aplicado: float,
     *   total_pagado: float
     * }
     */
    public function calcular(PedidoBma $pedido): array
    {
        $pagosActivos = PedidoBmaPago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->activosParaCobertura()
            ->get();

        $pagadoCentavos = $pagosActivos->sum(
            fn (PedidoBmaPago $p) => PagosPedidoBmaConfig::aCentavos((string) $p->monto)
        );

        $envio = $pedido->costo_envio === null || $pedido->costo_envio === ''
            ? '0.00'
            : number_format((float) $pedido->costo_envio, 2, '.', '');

        $base = $this->calcularDesdeMontos(
            number_format((float) ($pedido->total_mercancia ?? 0), 2, '.', ''),
            $envio,
            (bool) $pedido->aplica_seguro,
            number_format((float) ($pedido->costo_seguro ?? 0), 2, '.', ''),
            number_format((float) ($pedido->saldo_a_favor ?? 0), 2, '.', ''),
            PagosPedidoBmaConfig::centavosADecimal((int) $pagadoCentavos),
        );

        $bloqueos = $this->bloqueos($pedido, $pagosActivos, $base);

        return array_merge($base, [
            'bloqueos' => $bloqueos,
            'cubierto' => $bloqueos === [] && $base['cubierto'],
        ]);
    }

    /**
     * Fórmula sin I/O (pruebas unitarias).
     *
     * @return array<string, mixed>
     */
    public function calcularDesdeMontos(
        string $mercancia,
        string $envio,
        bool $aplicaSeguro,
        string $costoSeguro,
        string $saldoAFavor,
        string $totalPagado,
        ?string $tolerancia = null,
    ): array {
        return self::calcularDesdeMontosEstatico(
            $mercancia,
            $envio,
            $aplicaSeguro,
            $costoSeguro,
            $saldoAFavor,
            $totalPagado,
            $tolerancia ?? $this->config->toleranciaMxn(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function calcularDesdeMontosEstatico(
        string $mercancia,
        string $envio,
        bool $aplicaSeguro,
        string $costoSeguro,
        string $saldoAFavor,
        string $totalPagado,
        string $tolerancia,
    ): array {
        $tolCentavos = PagosPedidoBmaConfig::aCentavos($tolerancia);

        $totalACubrir = PagosPedidoBmaConfig::aCentavos($mercancia)
            + PagosPedidoBmaConfig::aCentavos($envio)
            + ($aplicaSeguro ? PagosPedidoBmaConfig::aCentavos($costoSeguro) : 0);

        $saf = max(0, PagosPedidoBmaConfig::aCentavos($saldoAFavor));
        $totalACobrar = max(0, $totalACubrir - $saf);
        $pagado = max(0, PagosPedidoBmaConfig::aCentavos($totalPagado));
        $delta = $totalACobrar - $pagado;
        $pendiente = max(0, $delta);
        $excedente = max(0, -$delta);

        if ($pagado <= 0 && $saf <= 0) {
            $cobertura = 'sin_pago';
        } elseif ($pendiente > $tolCentavos) {
            $cobertura = 'parcial';
        } elseif ($excedente > $tolCentavos) {
            $cobertura = 'con_excedente';
        } else {
            $cobertura = 'cubierto';
        }

        $cubierto = in_array($cobertura, ['cubierto', 'con_excedente'], true);

        $totalACubrirDec = PagosPedidoBmaConfig::centavosADecimal($totalACubrir);
        $safDec = PagosPedidoBmaConfig::centavosADecimal($saf);
        $pagadoDec = PagosPedidoBmaConfig::centavosADecimal($pagado);
        $diffDec = PagosPedidoBmaConfig::centavosADecimal($delta);
        $pendienteDec = PagosPedidoBmaConfig::centavosADecimal($pendiente);
        $excedenteDec = PagosPedidoBmaConfig::centavosADecimal($excedente);
        $aCobrarDec = PagosPedidoBmaConfig::centavosADecimal($totalACobrar);

        return [
            'total_a_cubrir' => $totalACubrirDec,
            'saldo_favor_aplicado' => $safDec,
            'pagos_validos' => $pagadoDec,
            'diferencia' => $diffDec,
            'tolerancia_aplicada' => $tolerancia,
            'cubierto' => $cubierto,
            'bloqueos' => [],
            'total_a_cobrar' => $aCobrarDec,
            'pendiente' => $pendienteDec,
            'excedente_generado' => $excedenteDec,
            'cobertura' => $cobertura,
            'total_final' => (float) $totalACubrirDec,
            'saldos_aplicados' => (float) $safDec,
            'saldo_a_favor_aplicado' => (float) $safDec,
            'total_recibido' => (float) $pagadoDec,
            'total_pagado' => (float) $pagadoDec,
            'excedente' => (float) $excedenteDec,
            'nuevo_saldo_sugerido' => (float) $excedenteDec,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PedidoBmaPago>  $pagosActivos
     * @param  array<string, mixed>  $base
     * @return list<string>
     */
    private function bloqueos(PedidoBma $pedido, $pagosActivos, array $base): array
    {
        $bloqueos = [];

        $pendienteSinSustituto = PedidoBmaPago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado_revision', PedidoBmaPago::REVISION_RECHAZADO)
            ->whereNotNull('rechazado_at')
            ->whereDoesntHave('sustituto')
            ->exists();

        if ($pendienteSinSustituto) {
            $bloqueos[] = 'Hay comprobantes rechazados pendientes de sustitución.';
        }

        if ((float) $base['pendiente'] > (float) $base['tolerancia_aplicada']) {
            $bloqueos[] = RegistrarPagoPedidoBmaService::mensajeMontoFaltante((float) $base['pendiente']);
        }

        foreach ($pagosActivos as $pago) {
            if (empty($pago->ruta_archivo)) {
                $bloqueos[] = 'Cada exhibición de pago debe incluir su comprobante.';
                break;
            }
        }

        return array_values(array_unique($bloqueos));
    }

    /**
     * Valida banco permitido para el departamento del vendedor del pedido.
     * Sin filas en pivote: mantiene experiencia anterior (cualquier banco activo).
     */
    public static function assertBancoPermitido(PedidoBma $pedido, ?int $bancoId): void
    {
        if (! $bancoId) {
            return;
        }

        $pedido->loadMissing(['vendedor.departamento', 'vendedor.departamentos']);
        $deptoIds = collect([
            $pedido->vendedor?->departamento_id,
            ...($pedido->vendedor?->departamentos?->pluck('id')->all() ?? []),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($deptoIds->isEmpty()) {
            return;
        }

        $hayMapeo = CatalogoBancoDepartamento::query()
            ->whereIn('departamento_id', $deptoIds)
            ->where('activo', true)
            ->exists();

        if (! $hayMapeo) {
            return;
        }

        $ok = CatalogoBancoDepartamento::query()
            ->whereIn('departamento_id', $deptoIds)
            ->where('catalogo_banco_id', $bancoId)
            ->where('activo', true)
            ->exists();

        if (! $ok) {
            throw new InvalidArgumentException(
                'El banco seleccionado no está permitido para el departamento del pedido.'
            );
        }
    }
}
