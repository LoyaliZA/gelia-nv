<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class FinanzasConversionModalidad
{
    public function __construct(
        public string $mercancia,
        public string $pagado,
        public ?string $costo_envio_actual,
        public ?string $costo_envio_nuevo,
        public string $pendiente_transporte,
        public bool $transporte_desconocido,
        public bool $validacion_historica_no_cubre_nuevo_cargo,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mercancia' => $this->mercancia,
            'pagado' => $this->pagado,
            'costo_envio_actual' => $this->costo_envio_actual,
            'costo_envio_nuevo' => $this->costo_envio_nuevo,
            'pendiente_transporte' => $this->pendiente_transporte,
            'transporte_desconocido' => $this->transporte_desconocido,
            'validacion_historica_no_cubre_nuevo_cargo' => $this->validacion_historica_no_cubre_nuevo_cargo,
        ];
    }
}
