<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ResponderDisponibilidadInicialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmacion_explicita' => ['nullable', 'boolean'],
            'excepciones' => ['nullable', 'array', 'max:50'],
            'excepciones.*.descripcion' => ['required', 'string', 'max:255'],
            'excepciones.*.cantidad' => ['required', 'integer', 'min:1'],
            'excepciones.*.condicion' => ['required', 'in:detalle,faltante'],
            'excepciones.*.detalle' => ['required', 'string', 'max:500'],
            'excepciones.*.sku' => ['nullable', 'string', 'max:64'],
            'evidencias' => ['nullable', 'array', 'max:8'],
            'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'evidencias_guardadas' => ['nullable', 'array', 'max:20'],
            'evidencias_guardadas.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'evidencias.required' => 'Adjunte una foto del conjunto físico. El PDF del pedido no la sustituye.',
            'evidencias.min' => 'Adjunte una foto del conjunto físico. El PDF del pedido no la sustituye.',
        ];
    }
}
