<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;

/**
 * Contrato de etapas del formulario progresivo de Ventas.
 * React presenta; no reconstruye la máquina con condicionales dispersos.
 */
class CalcularProgresoPedidoBmaService
{
    public const ETAPA_SOLICITUD = 'solicitud';

    public const ETAPA_CONSULTA = 'consulta';

    public const ETAPA_CONFIRMACION = 'confirmacion';

    public const ETAPA_COTIZACION = 'cotizacion';

    public const ETAPA_PAGO = 'pago';

    public const ESTADO_COMPLETA = 'completa';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_BLOQUEADA = 'bloqueada';

    public const ESTADO_CORRECCION = 'requiere_correccion';

    /**
     * @return array{
     *   etapa_actual: string,
     *   etapas: list<array{codigo: string, estado: string, editable: bool, motivo_bloqueo: string|null}>,
     *   accion_recomendada: string,
     *   bloqueos: list<string>
     * }
     */
    public function calcular(?PedidoBma $pedido): array
    {
        if (! $pedido || ! $pedido->exists) {
            return $this->respuestaNuevo();
        }

        $pedido->loadMissing(['origen', 'estatus', 'documentos', 'paqueteria', 'zona', 'tipoGuia']);

        $solicitudOk = $this->solicitudCompleta($pedido);
        $requiereLogistica = (bool) ($pedido->origen?->requiere_logistica ?? false);
        $pendientePesaje = $pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE
            || $pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE;
        $respondido = $pedido->tienePesajeRespondido();
        $consultaCerrada = $pedido->consultaCerrada();
        $actualizacionPendiente = (bool) $pedido->consulta_actualizacion_pendiente;
        $cotizacionOk = $this->cotizacionLista($pedido, $requiereLogistica, $respondido, $consultaCerrada);
        $pagoOk = $this->pagoListo($pedido, $cotizacionOk);

        $bloqueos = [];
        if ($actualizacionPendiente) {
            $bloqueos[] = 'Hay una actualización de consulta pendiente de respuesta CEDIS.';
        }
        if ($pendientePesaje) {
            $bloqueos[] = 'Espere la respuesta de CEDIS.';
        }

        $etapas = [
            $this->etapa(
                self::ETAPA_SOLICITUD,
                $solicitudOk ? self::ESTADO_COMPLETA : self::ESTADO_ACTIVA,
                true,
                null
            ),
            $this->etapaConsulta(
                $solicitudOk,
                $respondido,
                $pendientePesaje,
                $consultaCerrada,
                $actualizacionPendiente
            ),
            $this->etapaConfirmacion(
                $solicitudOk,
                $respondido,
                $pendientePesaje,
                $consultaCerrada,
                $actualizacionPendiente
            ),
            $this->etapaCotizacion(
                $consultaCerrada,
                $actualizacionPendiente,
                $pendientePesaje,
                $cotizacionOk
            ),
            $this->etapaPago($cotizacionOk, $consultaCerrada, $actualizacionPendiente, $pagoOk),
        ];

        $etapaActual = $this->resolverEtapaActual($etapas);
        $accion = $this->accionRecomendada(
            $etapaActual,
            $pendientePesaje,
            $respondido,
            $consultaCerrada,
            $actualizacionPendiente,
            $cotizacionOk,
            $pagoOk
        );

        return [
            'etapa_actual' => $etapaActual,
            'etapas' => $etapas,
            'accion_recomendada' => $accion,
            'bloqueos' => $bloqueos,
        ];
    }

    /**
     * @return array{etapa_actual: string, etapas: list<array<string, mixed>>, accion_recomendada: string, bloqueos: list<string>}
     */
    private function respuestaNuevo(): array
    {
        return [
            'etapa_actual' => self::ETAPA_SOLICITUD,
            'etapas' => [
                $this->etapa(self::ETAPA_SOLICITUD, self::ESTADO_ACTIVA, true, null),
                $this->etapa(self::ETAPA_CONSULTA, self::ESTADO_BLOQUEADA, false, 'Complete la solicitud inicial.'),
                $this->etapa(self::ETAPA_CONFIRMACION, self::ESTADO_BLOQUEADA, false, 'Espere la respuesta de CEDIS.'),
                $this->etapa(self::ETAPA_COTIZACION, self::ESTADO_BLOQUEADA, false, 'Cierre la consulta primero.'),
                $this->etapa(self::ETAPA_PAGO, self::ESTADO_BLOQUEADA, false, 'Complete la cotización primero.'),
            ],
            'accion_recomendada' => 'Capture cliente, tipo de pedido y datos mínimos.',
            'bloqueos' => [],
        ];
    }

    private function solicitudCompleta(PedidoBma $pedido): bool
    {
        if (! $pedido->cliente_id || ! $pedido->origen_id || ! $pedido->almacen_id) {
            return false;
        }

        $tienePdf = $pedido->documentos
            ->contains(fn ($d) => $d->tipo === PedidoBmaDocumento::TIPO_PDF_PEDIDO);

        // Borrador usable: con PDF o al menos folio WizeRP iniciado.
        return $tienePdf || filled($pedido->folio_remision);
    }

    private function cotizacionLista(
        PedidoBma $pedido,
        bool $requiereLogistica,
        bool $respondido,
        bool $consultaCerrada
    ): bool {
        if ($pedido->requiereConsultaCerradaParaProceder() && ! $consultaCerrada) {
            return false;
        }
        if ((float) ($pedido->total_mercancia ?? 0) <= 0) {
            return false;
        }
        if (! $requiereLogistica) {
            return true;
        }
        if (! $respondido) {
            return false;
        }
        if ($pedido->esResguardoAbierto() || $pedido->esResguardoComplementario()) {
            return true;
        }
        if ($pedido->cliente_proporciona_guia || $pedido->envio_por_cobrar) {
            return true;
        }
        if (! $pedido->catalogo_paqueteria_id || ! $pedido->catalogo_tipo_guia_id || ! $pedido->catalogo_zona_id) {
            return false;
        }

        return $pedido->costo_envio !== null && $pedido->costo_envio !== '';
    }

    private function pagoListo(PedidoBma $pedido, bool $cotizacionOk): bool
    {
        if (! $cotizacionOk) {
            return false;
        }

        return $pedido->pago_validado_at !== null
            || (float) ($pedido->total_a_cobrar ?? 0) <= 0;
    }

    /**
     * @return array{codigo: string, estado: string, editable: bool, motivo_bloqueo: string|null}
     */
    private function etapa(string $codigo, string $estado, bool $editable, ?string $motivo): array
    {
        return [
            'codigo' => $codigo,
            'estado' => $estado,
            'editable' => $editable,
            'motivo_bloqueo' => $motivo,
        ];
    }

    /**
     * @return array{codigo: string, estado: string, editable: bool, motivo_bloqueo: string|null}
     */
    private function etapaConsulta(
        bool $solicitudOk,
        bool $respondido,
        bool $pendientePesaje,
        bool $consultaCerrada,
        bool $actualizacionPendiente
    ): array {
        if (! $solicitudOk) {
            return $this->etapa(self::ETAPA_CONSULTA, self::ESTADO_BLOQUEADA, false, 'Complete la solicitud inicial.');
        }
        if ($actualizacionPendiente || $pendientePesaje) {
            return $this->etapa(
                self::ETAPA_CONSULTA,
                $actualizacionPendiente ? self::ESTADO_CORRECCION : self::ESTADO_ACTIVA,
                true,
                $pendientePesaje ? 'Esperando respuesta de CEDIS.' : null
            );
        }
        if ($respondido || $consultaCerrada) {
            return $this->etapa(self::ETAPA_CONSULTA, self::ESTADO_COMPLETA, true, null);
        }

        return $this->etapa(self::ETAPA_CONSULTA, self::ESTADO_ACTIVA, true, null);
    }

    /**
     * @return array{codigo: string, estado: string, editable: bool, motivo_bloqueo: string|null}
     */
    private function etapaConfirmacion(
        bool $solicitudOk,
        bool $respondido,
        bool $pendientePesaje,
        bool $consultaCerrada,
        bool $actualizacionPendiente
    ): array {
        if (! $solicitudOk) {
            return $this->etapa(self::ETAPA_CONFIRMACION, self::ESTADO_BLOQUEADA, false, 'Complete la solicitud inicial.');
        }
        if ($actualizacionPendiente || $pendientePesaje || ! $respondido) {
            return $this->etapa(
                self::ETAPA_CONFIRMACION,
                self::ESTADO_BLOQUEADA,
                false,
                $actualizacionPendiente
                    ? 'Espere la nueva respuesta de CEDIS.'
                    : 'Espere la respuesta de CEDIS.'
            );
        }
        if ($consultaCerrada) {
            return $this->etapa(self::ETAPA_CONFIRMACION, self::ESTADO_COMPLETA, true, null);
        }

        return $this->etapa(self::ETAPA_CONFIRMACION, self::ESTADO_ACTIVA, true, null);
    }

    /**
     * @return array{codigo: string, estado: string, editable: bool, motivo_bloqueo: string|null}
     */
    private function etapaCotizacion(
        bool $consultaCerrada,
        bool $actualizacionPendiente,
        bool $pendientePesaje,
        bool $cotizacionOk
    ): array {
        if ($actualizacionPendiente || $pendientePesaje || ! $consultaCerrada) {
            return $this->etapa(
                self::ETAPA_COTIZACION,
                self::ESTADO_BLOQUEADA,
                false,
                $actualizacionPendiente
                    ? 'La cotización se bloquea hasta nueva respuesta CEDIS.'
                    : 'Cierre la consulta con el cliente primero.'
            );
        }
        if ($cotizacionOk) {
            return $this->etapa(self::ETAPA_COTIZACION, self::ESTADO_COMPLETA, true, null);
        }

        return $this->etapa(self::ETAPA_COTIZACION, self::ESTADO_ACTIVA, true, null);
    }

    /**
     * @return array{codigo: string, estado: string, editable: bool, motivo_bloqueo: string|null}
     */
    private function etapaPago(
        bool $cotizacionOk,
        bool $consultaCerrada,
        bool $actualizacionPendiente,
        bool $pagoOk
    ): array {
        if ($actualizacionPendiente || ! $consultaCerrada || ! $cotizacionOk) {
            return $this->etapa(
                self::ETAPA_PAGO,
                self::ESTADO_BLOQUEADA,
                false,
                $actualizacionPendiente
                    ? 'El pago se bloquea hasta nueva respuesta CEDIS.'
                    : 'Complete la cotización primero.'
            );
        }
        if ($pagoOk) {
            return $this->etapa(self::ETAPA_PAGO, self::ESTADO_COMPLETA, true, null);
        }

        return $this->etapa(self::ETAPA_PAGO, self::ESTADO_ACTIVA, true, null);
    }

    /**
     * @param  list<array{codigo: string, estado: string, editable: bool, motivo_bloqueo: string|null}>  $etapas
     */
    private function resolverEtapaActual(array $etapas): string
    {
        foreach ($etapas as $etapa) {
            if (in_array($etapa['estado'], [self::ESTADO_ACTIVA, self::ESTADO_CORRECCION], true)) {
                return $etapa['codigo'];
            }
        }
        foreach (array_reverse($etapas) as $etapa) {
            if ($etapa['estado'] === self::ESTADO_COMPLETA) {
                return $etapa['codigo'];
            }
        }

        return self::ETAPA_SOLICITUD;
    }

    private function accionRecomendada(
        string $etapaActual,
        bool $pendientePesaje,
        bool $respondido,
        bool $consultaCerrada,
        bool $actualizacionPendiente,
        bool $cotizacionOk,
        bool $pagoOk
    ): string {
        if ($actualizacionPendiente || $pendientePesaje) {
            return 'Esperar respuesta de CEDIS';
        }

        return match ($etapaActual) {
            self::ETAPA_SOLICITUD => 'Complete la solicitud y envíe la consulta a CEDIS.',
            self::ETAPA_CONSULTA => $respondido
                ? 'Revise la respuesta de CEDIS.'
                : 'Solicite la revisión a CEDIS.',
            self::ETAPA_CONFIRMACION => 'Confirme condiciones con el cliente y cierre la consulta.',
            self::ETAPA_COTIZACION => $cotizacionOk
                ? 'Revise la cotización.'
                : 'Capture montos, dirección y costos disponibles.',
            self::ETAPA_PAGO => $pagoOk
                ? 'Envíe el pedido al Auxiliar.'
                : 'Registre el comprobante de pago.',
            default => 'Continúe el pedido.',
        };
    }
}
