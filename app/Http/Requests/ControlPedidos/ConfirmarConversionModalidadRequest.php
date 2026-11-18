<?php

namespace App\Http\Requests\ControlPedidos;

use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarConversionModalidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.crear') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('error_captura')) {
            $this->merge([
                'error_captura' => filter_var($this->input('error_captura'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        $exigeEvidencia = ! $this->boolean('error_captura');

        return [
            'destino' => ['required', 'string', Rule::in([CodigosConversionModalidad::DESTINO_MUNICIPAL])],
            'instancia_id' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'max:200'],
            'error_captura' => ['sometimes', 'boolean'],
            'evidencias' => array_values(array_filter([
                $exigeEvidencia ? 'required' : 'nullable',
                'array',
                'max:8',
                $exigeEvidencia ? 'min:1' : null,
            ])),
            'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'catalogo_paqueteria_id' => ['required', 'integer'],
            'consignatario_id' => ['required', 'integer'],
            'almacen_id' => ['nullable', 'integer'],
            'leyenda_terminal' => ['required', 'in:PAGADO,POR_COBRAR'],
            'costo_transporte' => ['required', 'in:conocido,diferido'],
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
            'revision_esperada' => ['required', 'integer', 'min:0'],
            'conversion_version' => ['required', 'integer', 'min:0'],
            'clave_idempotencia' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indique el motivo del cambio de modalidad.',
            'evidencias.required' => 'Adjunte la petición del cliente o notifique un error de captura.',
            'evidencias.min' => 'Adjunte la petición del cliente o notifique un error de captura.',
            'destino.in' => 'El destino seleccionado no está habilitado.',
        ];
    }
}
