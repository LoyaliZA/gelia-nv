<?php

namespace App\Http\Requests\ControlPedidos;

use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportarErrorDatosPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('control_pedidos.delegado') || $user?->can('control_pedidos.cedis')) ?? false;
    }

    public function rules(): array
    {
        return [
            'campos_incorrectos' => ['required', 'array', 'min:1'],
            'campos_incorrectos.*' => ['string', Rule::in(CamposIncorrectosPedidoBma::ALLOWLIST)],
            'detalle' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'campos_incorrectos.required' => 'Seleccione al menos un dato incorrecto.',
            'campos_incorrectos.min' => 'Seleccione al menos un dato incorrecto.',
            'campos_incorrectos.*.in' => 'Uno de los campos seleccionados no es válido.',
        ];
    }
}
