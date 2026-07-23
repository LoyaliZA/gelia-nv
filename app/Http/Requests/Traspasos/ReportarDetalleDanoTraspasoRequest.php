<?php

namespace App\Http\Requests\Traspasos;

use Illuminate\Foundation\Http\FormRequest;

class ReportarDetalleDanoTraspasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('traspasos.cedis') ?? false;
    }

    public function rules(): array
    {
        return [
            'solicitud_traspaso_producto_id' => ['required', 'integer', 'exists:solicitud_traspaso_productos,id'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'fotos' => ['required', 'array', 'min:1', 'max:8'],
            'fotos.*' => ['required', 'image', 'max:10240'],
        ];
    }
}
