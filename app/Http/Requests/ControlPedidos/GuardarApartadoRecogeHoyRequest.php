<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class GuardarApartadoRecogeHoyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('control_pedidos.tienda.apartado.separar') || $this->user()?->can('control_pedidos.tienda.responder')) ?? false;
    }

    public function rules(): array
    {
        return array_merge(ReglasLineasPreparacion::reglas(! $this->filled('motivo_incidencia')), [
            'version' => ['required', 'integer', 'min:1'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'evidencias' => ['nullable', 'array', 'max:10'],
            'evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'motivo_incidencia' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
