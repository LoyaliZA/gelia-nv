<?php

namespace App\Services\Almacenes;

class ProcesarFilaInventarioImportacionService
{
    public function __construct(
        private readonly ProcesarFilaCantidadReferenciaImportacionService $cantidades,
    ) {}

    /**
     * Solo existencia, ubicación y asignación. No crea el producto ni escribe costo.
     *
     * @return array{accion: string}
     */
    public function ejecutar(array $row, array $mapping, int $almacenId): array
    {
        $resultado = $this->cantidades->ejecutar($row, $mapping, $almacenId);

        return ['accion' => $resultado['accion']];
    }
}
