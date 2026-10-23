<?php

namespace App\Http\Requests\Almacenes;

use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('almacenes.inventarios.gestionar');
    }

    public function rules(): array
    {
        $inventario = $this->route('inventario');

        return [
            'producto_id' => [
                'required',
                'exists:productos,id',
                Rule::unique('inventarios')
                    ->where(fn ($q) => $q->where('almacen_id', $this->input('almacen_id')))
                    ->ignore($inventario?->id),
            ],
            'almacen_id' => 'required|exists:almacenes,id',
            'ubicacion' => 'nullable|string|max:50',
            'existencia' => 'nullable|numeric|min:0',
            'apartado' => 'nullable|numeric|min:0',
            'transito_oc' => 'nullable|numeric|min:0',
            'transito_ot' => 'nullable|numeric|min:0',
            'minimo' => 'nullable|numeric|min:0',
            'maximo' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! $this->user()) {
                return;
            }

            $alcance = app(AlcanceAlmacenesService::class);
            $registro = $this->route('inventario');
            $almacenOriginal = $registro?->almacen_id;
            if (! $almacenOriginal) {
                $v->errors()->add('almacen_id', 'No autorizado para operar en el almacén del registro.');
            } else {
                try {
                    $alcance->asegurarAlmacenOperable($this->user(), (int) $almacenOriginal);
                } catch (\Throwable $e) {
                    $v->errors()->add('almacen_id', 'No autorizado para operar en el almacén del registro.');
                }
            }

            $almacenId = $this->input('almacen_id');
            if (! $almacenId) {
                return;
            }
            try {
                $alcance->asegurarAlmacenOperable($this->user(), (int) $almacenId);
            } catch (\Throwable $e) {
                $v->errors()->add('almacen_id', 'No autorizado para operar en ese almacén.');
            }
        });
    }
}
