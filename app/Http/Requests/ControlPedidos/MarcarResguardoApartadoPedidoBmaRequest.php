<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class MarcarResguardoApartadoPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis') ?? false;
    }

    public function rules(): array
    {
        return [
            'evidencias' => ['required', 'array', 'min:1', 'max:8'],
            'evidencias.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'detalle' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'evidencias.required' => 'Debe adjuntar al menos una foto de evidencia.',
            'evidencias.min' => 'Debe adjuntar al menos una foto de evidencia.',
            'evidencias.*.mimes' => 'Las evidencias deben ser imágenes (JPG, PNG o WEBP).',
            'evidencias.*.max' => 'Cada imagen no debe superar 5 MB.',
        ];
    }
}
