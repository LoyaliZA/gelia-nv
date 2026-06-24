<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarLoteRetiroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contabilidad.retiros.confirmar') ?? false;
    }

    public function rules(): array
    {
        return [
            'plataforma_pago_id' => ['required', 'integer', 'exists:contabilidad_plataformas_pago,id'],
            'fecha_deposito' => ['required', 'date'],
            'pedidos' => ['required', 'array', 'min:1'],
            'pedidos.*.id' => ['required', 'integer'],
            'pedidos.*.monto_real' => ['required', 'numeric'],
        ];
    }
}
