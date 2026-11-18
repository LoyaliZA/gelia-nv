<?php

namespace App\Http\Requests\ControlPedidos;

use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VistaPreviaConversionModalidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'destino' => ['required', 'string', Rule::in([
                CodigosConversionModalidad::DESTINO_MUNICIPAL,
                CodigosConversionModalidad::DESTINO_TIENDA,
                CodigosConversionModalidad::DESTINO_NACIONAL,
            ])],
            'instancia_id' => ['required', 'uuid'],
            'catalogo_paqueteria_id' => ['nullable', 'integer'],
            'consignatario_id' => ['nullable', 'integer'],
            'almacen_id' => ['nullable', 'integer'],
            'leyenda_terminal' => ['nullable', 'in:PAGADO,POR_COBRAR'],
            'costo_transporte' => ['nullable', 'in:conocido,diferido'],
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
