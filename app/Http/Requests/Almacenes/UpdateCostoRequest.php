<?php

namespace App\Http\Requests\Almacenes;

use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('almacenes.costos.gestionar');
    }

    public function rules(): array
    {
        $costo = $this->route('costo');

        return [
            'producto_id' => [
                'required',
                'exists:productos,id',
                Rule::unique('producto_costos')
                    ->where(fn ($q) => $q->where('almacen_id', $this->input('almacen_id')))
                    ->ignore($costo?->id),
            ],
            'almacen_id' => 'required|exists:almacenes,id',
            'costo' => 'nullable|numeric|min:0',
            'costo_reposicion' => 'nullable|numeric|min:0',
            'precio_venta' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $almacenId = $this->input('almacen_id');
            if (! $almacenId || ! $this->user()) {
                return;
            }
            try {
                app(AlcanceAlmacenesService::class)->asegurarAlmacenOperable($this->user(), (int) $almacenId);
            } catch (\Throwable $e) {
                $v->errors()->add('almacen_id', 'No autorizado para operar en ese almacén.');
            }
        });
    }
}
