<?php

namespace App\Http\Requests\Traspasos;

use Illuminate\Foundation\Http\FormRequest;

class AbastecimientoPreparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $crear = $this->route('tarea') !== null;

        return [
            'version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'almacen_origen_id' => [$crear ? 'required' : 'prohibited', 'integer', 'exists:almacenes,id'],
            'tipo' => [$crear ? 'prohibited' : 'required', 'in:SALIDA,LLEGADA,FALTANTE,RECHAZO_AUTORIZADO'],
            'lineas' => [$crear ? 'required' : 'sometimes', 'array', 'max:200'],
            'lineas.*.id' => ['required', 'integer', 'distinct'],
            'lineas.*.cantidad' => [$crear ? 'required' : 'sometimes', 'integer', 'min:1', 'max:9999'],
            'lineas.*.buenas' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'lineas.*.danadas' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'evidencia' => [$crear ? 'prohibited' : 'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'revisiones' => ['sometimes', 'array', 'max:10000'],
            'revisiones.*.solicitud_traspaso_producto_id' => ['required', 'integer'],
            'revisiones.*.estado_fisico' => ['required', 'in:bueno,regular,malo,danado,sin_existencia'],
            'revisiones.*.comentario' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
