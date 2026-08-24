<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ReportarIncidenciaPreparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.reportar_error') ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_incidencia' => ['required', 'string', 'max:64'],
            'motivo' => ['required', 'string', 'max:2000'],
            'almacen_solicitado_id' => ['required', 'integer', 'exists:almacenes,id'],
            'almacen_aparente_id' => ['nullable', 'integer', 'exists:almacenes,id'],
            'productos_afectados' => ['nullable', 'array'],
            'productos_afectados.*' => ['integer'],
            'observacion' => ['nullable', 'string', 'max:4000'],
            'version' => ['nullable', 'integer'],
            'evidencias' => ['nullable', 'array'],
            'evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
