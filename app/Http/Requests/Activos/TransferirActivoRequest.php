<?php

namespace App\Http\Requests\Activos;

use Illuminate\Foundation\Http\FormRequest;

class TransferirActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('activos.transferir');
    }

    public function rules(): array
    {
        return [
            'departamento_destino_id' => 'required|exists:departamentos,id',
            'motivo' => 'nullable|string|max:255',
            'notas' => 'nullable|string|max:1000',
        ];
    }
}
