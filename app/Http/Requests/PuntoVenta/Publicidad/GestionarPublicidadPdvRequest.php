<?php

namespace App\Http\Requests\PuntoVenta\Publicidad;

use App\Models\PuntoVenta\PdvPantallaPublicidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestionarPublicidadPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esAlta = $this->isMethod('POST') && ! $this->route('publicidad');

        return [
            'sucursal_id' => ['required', 'integer', 'min:1'],
            'medio_id' => [$esAlta ? 'required' : 'nullable', 'integer', 'min:1'],
            'alcance' => [$esAlta ? 'required' : 'nullable', Rule::in(['global', 'sucursal'])],
            'ajuste' => ['nullable', Rule::in([PdvPantallaPublicidad::AJUSTE_COVER, PdvPantallaPublicidad::AJUSTE_CONTAIN])],
            'volumen_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'activa' => ['nullable', 'boolean'],
            'duracion_seg' => ['nullable', 'integer', 'min:3', 'max:300'],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'eliminar_automaticamente' => ['nullable', 'boolean'],
            'conservar_dias' => ['nullable', 'integer', Rule::in([7, 30, 60, 90])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $auto = filter_var($this->input('eliminar_automaticamente'), FILTER_VALIDATE_BOOLEAN);
            if (! $auto) {
                return;
            }
            if (! $this->filled('vigente_hasta')) {
                $validator->errors()->add('vigente_hasta', 'La eliminación automática requiere una fecha de fin.');
            }
            if (! $this->filled('conservar_dias')) {
                $validator->errors()->add('conservar_dias', 'Indica cuántos días conservar la pieza.');
            }
        });
    }
}
