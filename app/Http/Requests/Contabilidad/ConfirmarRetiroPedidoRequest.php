<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarRetiroPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contabilidad.retiros.confirmar') ?? false;
    }

    public function rules(): array
    {
        return [
            'monto_real_banco' => ['required', 'numeric'],
            'fecha_deposito' => ['required', 'date'],
        ];
    }
}
