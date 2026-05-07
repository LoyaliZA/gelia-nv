<?php

namespace App\Actions\Organizacion;

use App\Models\Departamento;

class CrearDepartamentoAction
{
    /**
     * Ejecuta la creación de un departamento.
     */
    public function execute(array $datos): Departamento
    {
        return Departamento::create([
            'nombre' => $datos['nombre'],
            'activo' => $datos['activo'] ?? true,
        ]);
    }
}