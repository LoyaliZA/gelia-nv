<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class HerenciaConsultaConversion
{
    /**
     * @param  'sin_consulta'|'parcial'|'respondida_abierta'  $caso
     * @param  array<string, mixed>  $consulta
     * @param  array<string, mixed>  $pagos
     * @param  array<string, mixed>  $remision
     * @param  array<string, mixed>  $documentos
     */
    public function __construct(
        public string $caso,
        public array $consulta = [],
        public array $pagos = [],
        public array $remision = [],
        public array $documentos = [],
        public ?string $procedencia = null,
        public ?string $consulta_origen = null,
        public bool $requiere_revision_complementaria = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'caso' => $this->caso,
            'consulta' => $this->consulta,
            'pagos' => $this->pagos,
            'remision' => $this->remision,
            'documentos' => $this->documentos,
            'procedencia' => $this->procedencia,
            'consulta_origen' => $this->consulta_origen,
            'requiere_revision_complementaria' => $this->requiere_revision_complementaria,
        ];
    }
}
