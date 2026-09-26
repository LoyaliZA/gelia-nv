<?php

namespace App\Services\Almacenes;

use App\Models\Almacen;
use App\Services\Catalogos\NormalizarTextoImportacionService;

class GuardarAlmacenService
{
    public function __construct(
        private readonly NormalizarTextoImportacionService $normalizador,
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function crear(array $data): Almacen
    {
        $data['nombre'] = $this->normalizador->texto((string) $data['nombre']);
        $almacen = Almacen::create($data);
        $this->auditoria->catalogoCrud('creado', 'almacen', $almacen->id, $almacen->codigo);

        return $almacen;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizar(Almacen $almacen, array $data): Almacen
    {
        $data['nombre'] = $this->normalizador->texto((string) $data['nombre']);
        $almacen->update($data);
        $this->auditoria->catalogoCrud('actualizado', 'almacen', $almacen->id, $almacen->codigo);

        return $almacen;
    }
}
