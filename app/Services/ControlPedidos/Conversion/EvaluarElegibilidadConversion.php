<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\Conversion\ConsultaPedidoYaCerradaAlgunaVez;
use App\Support\ControlPedidos\Conversion\ConversionModalidadEnCursoPedidoBma;
use App\Support\ControlPedidos\Conversion\DestinoConversionModalidad;
use App\Support\ControlPedidos\Conversion\DispatcherPoliticasConversion;
use App\Support\ControlPedidos\Conversion\FlagsConversionModalidad;
use App\Support\ControlPedidos\Conversion\PoliticaConversionModalidad;
use App\Support\ControlPedidos\Conversion\ResultadoElegibilidadConversion;
use App\Support\ControlPedidos\Conversion\SolicitudPendienteConversion;
use App\Support\ControlPedidos\VisibilidadPedidoBma;

class EvaluarElegibilidadConversion
{
    public function __construct(
        private DispatcherPoliticasConversion $dispatcher,
        private ResolverHerenciaConsultaConversion $herencia,
        private ConciliarFinanzasConversion $finanzas,
        private SustituirInstruccionAbiertaConversion $fisica,
    ) {}

    /**
     * @param  array<string, mixed>  $captura
     */
    public function flags(PedidoBma $pedido, ?User $usuario, array $captura = []): FlagsConversionModalidad
    {
        $json = $this->json($pedido);
        $origen = (string) ($json['modalidad_entrega'] ?? '');
        $cerrada = ConsultaPedidoYaCerradaAlgunaVez::es($pedido);
        $reserva = ConversionModalidadEnCursoPedidoBma::payload($pedido->id);
        $conversion = is_array($json['conversion_modalidad'] ?? null) ? $json['conversion_modalidad'] : [];
        $t03 = is_array($json['cambio_modalidad_tienda'] ?? null) ? $json['cambio_modalidad_tienda'] : [];
        $confirmada = ($conversion['estado'] ?? null) === CodigosConversionModalidad::ESTADO_CONFIRMADA;
        $borrador = ($conversion['estado'] ?? null) === CodigosConversionModalidad::ESTADO_BORRADOR;
        $t03Pendiente = ($t03['estado'] ?? null) === CodigosConversionModalidad::ESTADO_T03_PENDIENTE
            && ($t03['destino'] ?? null) === CodigosConversionModalidad::DESTINO_MUNICIPAL;
        $actorOk = $usuario
            && $usuario->can('control_pedidos.crear')
            && VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido);
        $bloqueosMotor = $this->bloqueosMotor($pedido, $origen, $cerrada, $confirmada);

        $destinos = [];
        foreach ([
            CodigosConversionModalidad::DESTINO_MUNICIPAL,
            CodigosConversionModalidad::DESTINO_TIENDA,
            CodigosConversionModalidad::DESTINO_NACIONAL,
        ] as $codigo) {
            $destinos[] = $this->destino($this->dispatcher->porDestino($codigo), $origen, $cerrada, $bloqueosMotor, $confirmada);
        }

        $municipalHabilitado = false;
        foreach ($destinos as $destino) {
            if ($destino->codigo === CodigosConversionModalidad::DESTINO_MUNICIPAL) {
                $municipalHabilitado = $destino->habilitado;
            }
        }

        $puedeContinuar = $actorOk && $t03Pendiente && ! $cerrada && ! $confirmada && $municipalHabilitado;
        $puedeIniciar = $actorOk && ! $t03Pendiente && ! $confirmada && $municipalHabilitado && $bloqueosMotor === null;
        $solicitudPendiente = $t03Pendiente
            ? new SolicitudPendienteConversion(CodigosConversionModalidad::FUENTE_T03, CodigosConversionModalidad::ESTADO_T03_PENDIENTE)
            : null;

        return new FlagsConversionModalidad(
            $origen,
            $destinos,
            $puedeIniciar,
            $puedeContinuar,
            $actorOk && ! $confirmada && ($borrador || $t03Pendiente),
            $cerrada,
            $reserva !== null,
            $reserva?->usuario_id,
            ConversionModalidadEnCursoPedidoBma::instanciaOpaca($reserva),
            $puedeContinuar ? CodigosConversionModalidad::ACCION_UI_CONTINUAR : null,
            $solicitudPendiente,
            $this->herencia->clasificar($pedido, isset($captura['almacen_id']) ? (int) $captura['almacen_id'] : null),
            $this->finanzas->evaluar($pedido, $captura),
            $this->fisica->clasificar($pedido),
            (int) ($conversion['conversion_version'] ?? 0),
        );
    }

    public function paraDestino(PedidoBma $pedido, User $usuario, string $destino): ResultadoElegibilidadConversion
    {
        $cerrada = ConsultaPedidoYaCerradaAlgunaVez::es($pedido);
        if ($cerrada) {
            return new ResultadoElegibilidadConversion(
                false,
                true,
                'La consulta ya se cerró. El cambio de modalidad no está disponible.',
                CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA,
            );
        }
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            return new ResultadoElegibilidadConversion(
                false,
                false,
                'No puede modificar esta solicitud.',
                CodigosConversionModalidad::CODIGO_NO_ELEGIBLE,
            );
        }
        $origen = (string) ($this->json($pedido)['modalidad_entrega'] ?? '');
        $motor = $this->bloqueosMotor($pedido, $origen, false, false);
        if ($motor !== null) {
            return new ResultadoElegibilidadConversion(false, false, $motor, CodigosConversionModalidad::CODIGO_NO_ELEGIBLE);
        }
        try {
            $politica = $this->dispatcher->porDestino($destino);
        } catch (\InvalidArgumentException) {
            return new ResultadoElegibilidadConversion(
                false,
                false,
                'El destino seleccionado no está habilitado.',
                CodigosConversionModalidad::CODIGO_DESTINO_INACTIVO,
            );
        }
        if (! $politica->activo()) {
            return new ResultadoElegibilidadConversion(
                false,
                false,
                $politica->evaluar($pedido)->motivo_bloqueo ?: 'El destino seleccionado no está habilitado.',
                CodigosConversionModalidad::CODIGO_DESTINO_INACTIVO,
            );
        }
        $resultado = $politica->evaluar($pedido);
        if (! $resultado->permitido) {
            return new ResultadoElegibilidadConversion(
                false,
                false,
                $resultado->motivo_bloqueo,
                CodigosConversionModalidad::CODIGO_NO_ELEGIBLE,
            );
        }

        return new ResultadoElegibilidadConversion(true, false);
    }

    private function destino(
        PoliticaConversionModalidad $politica,
        string $origen,
        bool $cerrada,
        ?string $bloqueoMotor,
        bool $confirmada,
    ): DestinoConversionModalidad {
        $codigo = $politica->destino();
        $nombre = match ($codigo) {
            CodigosConversionModalidad::DESTINO_MUNICIPAL => CodigosConversionModalidad::ETIQUETA_DESTINO_MUNICIPAL,
            CodigosConversionModalidad::DESTINO_TIENDA => CodigosConversionModalidad::ETIQUETA_DESTINO_TIENDA,
            default => CodigosConversionModalidad::ETIQUETA_DESTINO_NACIONAL,
        };
        if (! $politica->activo()) {
            return new DestinoConversionModalidad($codigo, $nombre, false, "El cambio a {$nombre} no está habilitado.");
        }
        if ($confirmada) {
            return new DestinoConversionModalidad($codigo, $nombre, false, 'El cambio de modalidad ya está confirmado.');
        }
        if (! in_array($origen, $politica->origenes(), true)) {
            return new DestinoConversionModalidad($codigo, $nombre, false, "Este pedido no admite el cambio a {$nombre}.");
        }
        if ($cerrada) {
            return new DestinoConversionModalidad(
                $codigo,
                $nombre,
                false,
                'La consulta ya se cerró. El cambio de modalidad no está disponible.',
            );
        }
        if ($bloqueoMotor !== null) {
            return new DestinoConversionModalidad($codigo, $nombre, false, $bloqueoMotor);
        }

        return new DestinoConversionModalidad($codigo, $nombre, true);
    }

    private function bloqueosMotor(PedidoBma $pedido, string $origen, bool $cerrada, bool $confirmada): ?string
    {
        if ($confirmada) {
            return 'El cambio de modalidad ya está confirmado.';
        }
        if ($pedido->cancelado_at) {
            return 'El pedido está cancelado.';
        }
        if ($pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_ENTREGADO) {
            return 'El pedido ya fue entregado.';
        }
        if ($cerrada) {
            return 'La consulta ya se cerró. El cambio de modalidad no está disponible.';
        }
        if ($origen !== CodigosConversionModalidad::ORIGEN_TIENDA) {
            return 'Solo un pedido de Tienda puede cambiar a envío municipal.';
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function json(PedidoBma $pedido): array
    {
        return is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
    }
}
