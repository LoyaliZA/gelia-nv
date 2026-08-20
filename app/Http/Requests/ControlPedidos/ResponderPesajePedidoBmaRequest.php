<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResponderPesajePedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis') ?? false;
    }

    public function rules(): array
    {
        $estados = PedidoBmaRevisionProducto::ESTADOS;

        return [
            'cajas' => ['required', 'array', 'min:1'],
            'cajas.*.catalogo_tipo_caja_id' => ['required', 'integer', 'exists:catalogo_tipos_caja_pedido,id'],
            'cajas.*.largo' => ['required', 'numeric', 'min:0'],
            'cajas.*.ancho' => ['required', 'numeric', 'min:0'],
            'cajas.*.alto' => ['required', 'numeric', 'min:0'],
            'cajas.*.peso_real_kg' => ['required', 'numeric', 'min:0'],
            'cajas.*.peso_volumetrico_kg' => ['required', 'numeric', 'min:0'],
            'estado_fisico_general' => ['nullable', 'string', Rule::in($estados)],
            'comentario_fisico_general' => ['nullable', 'string', 'max:2000'],
            'evidencias_generales' => ['nullable', 'array'],
            'evidencias_generales.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'evidencias_envios' => ['nullable', 'array'],
            'evidencias_envios.*' => ['nullable', 'array'],
            'evidencias_envios.*.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'revisiones' => ['nullable', 'array'],
            'revisiones.*.descripcion_producto' => ['required_with:revisiones', 'string', 'max:255'],
            'revisiones.*.producto_id' => ['nullable', 'integer'],
            'revisiones.*.sku' => ['nullable', 'string', 'max:64'],
            'revisiones.*.estado_fisico' => ['required_with:revisiones', 'string', Rule::in($estados)],
            'revisiones.*.comentario' => ['nullable', 'string', 'max:2000'],
            'revisiones.*.unica_pieza' => ['nullable', 'boolean'],
            'revisiones.*.mejor_ejemplar' => ['nullable', 'boolean'],
            'revisiones.*.evidencias' => ['nullable', 'array'],
            'revisiones.*.evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'revisiones.*.client_uuid' => ['nullable', 'string', 'max:64'],
            'cajas.*.client_uuid' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'cajas.required' => 'Indique al menos un envío.',
            'cajas.min' => 'Indique al menos un envío.',
            'cajas.*.catalogo_tipo_caja_id.required' => 'Cada envío requiere tipo de caja.',
            'cajas.*.peso_real_kg.required' => 'Cada envío requiere peso real.',
            'cajas.*.peso_volumetrico_kg.required' => 'Cada envío requiere peso volumétrico.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'estado_fisico_general' => $this->input('estado_fisico_general') ?: PedidoBmaRevisionProducto::ESTADO_BUENO,
        ];

        $revisiones = $this->input('revisiones');
        if (is_array($revisiones)) {
            $normalizadas = [];
            foreach ($revisiones as $rev) {
                if (! is_array($rev)) {
                    continue;
                }
                $normalizadas[] = [
                    ...$rev,
                    'unica_pieza' => filter_var($rev['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'mejor_ejemplar' => filter_var($rev['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
            $merge['revisiones'] = $normalizadas;
        }

        $this->merge($merge);
    }
}
