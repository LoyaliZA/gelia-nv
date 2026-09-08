<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

class DesactivarEquipoPdvRequest extends GestionEquipoPdvRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
