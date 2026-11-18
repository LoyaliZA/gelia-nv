<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class ResultadoConversionModalidad
{
    /**
     * @param  array<string, mixed>  $herencia
     */
    public function __construct(
        public int $pedido_id,
        public ?string $folio,
        public string $modalidad_entrega,
        public int $conversion_version,
        public int $revision_registro,
        public int $version_consulta,
        public array $herencia,
        public FlagsConversionModalidad $flags,
        public bool $ok = true,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'pedido_id' => $this->pedido_id,
            'folio' => $this->folio,
            'modalidad_entrega' => $this->modalidad_entrega,
            'conversion_version' => $this->conversion_version,
            'revision_registro' => $this->revision_registro,
            'version_consulta' => $this->version_consulta,
            'herencia' => $this->herencia,
            ...$this->flags->toArray(),
        ];
    }
}
