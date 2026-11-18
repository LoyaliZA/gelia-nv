<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class FlagsConversionModalidad
{
    /**
     * @param  list<DestinoConversionModalidad>  $destinos
     */
    public function __construct(
        public string $origen,
        public array $destinos,
        public bool $puede_iniciar,
        public bool $puede_continuar,
        public bool $puede_cancelar_borrador,
        public bool $consulta_ya_cerrada_alguna_vez,
        public bool $reserva_activa,
        public ?int $reserva_usuario_id,
        public ?string $reserva_instancia,
        public ?string $accion_ui,
        public ?SolicitudPendienteConversion $solicitud_pendiente,
        public HerenciaConsultaConversion $herencia,
        public FinanzasConversionModalidad $finanzas,
        public FisicaConversionModalidad $fisica,
        public int $version,
    ) {}

    /** Envelope GET / listado. @return array{conversion_modalidad: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'conversion_modalidad' => [
                'origen' => $this->origen,
                'destinos' => array_map(
                    static fn (DestinoConversionModalidad $destino) => $destino->toArray(),
                    $this->destinos
                ),
                'puede_iniciar' => $this->puede_iniciar,
                'puede_continuar' => $this->puede_continuar,
                'puede_cancelar_borrador' => $this->puede_cancelar_borrador,
                'consulta_ya_cerrada_alguna_vez' => $this->consulta_ya_cerrada_alguna_vez,
                'reserva_activa' => $this->reserva_activa,
                'reserva_usuario_id' => $this->reserva_usuario_id,
                'reserva_instancia' => $this->reserva_instancia,
                'accion_ui' => $this->accion_ui,
                'solicitud_pendiente' => $this->solicitud_pendiente?->toArray(),
                'herencia' => $this->herencia->toArray(),
                'finanzas' => $this->finanzas->toArray(),
                'fisica' => $this->fisica->toArray(),
                'version' => $this->version,
            ],
        ];
    }
}
