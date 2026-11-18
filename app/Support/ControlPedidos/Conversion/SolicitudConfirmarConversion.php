<?php

namespace App\Support\ControlPedidos\Conversion;

/**
 * Body POST confirmar P0. evidencias viajan en multipart; aquí solo metadatos.
 */
readonly class SolicitudConfirmarConversion
{
    /**
     * @param  list<mixed>  $evidencias
     */
    public function __construct(
        public string $destino,
        public string $instancia_id,
        public string $motivo,
        public bool $error_captura,
        public array $evidencias,
        public int $catalogo_paqueteria_id,
        public int $consignatario_id,
        public ?int $almacen_id,
        public string $leyenda_terminal,
        public string $costo_transporte,
        public mixed $costo_envio,
        public int $revision_esperada,
        public int $conversion_version,
        public string $clave_idempotencia,
    ) {}
}
