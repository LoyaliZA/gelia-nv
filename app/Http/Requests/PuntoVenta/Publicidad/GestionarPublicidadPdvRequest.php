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
            'activa' => ['nullable', 'boolean'],
            'duracion_seg' => ['nullable', 'integer', 'min:3', 'max:300'],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
        ];
    }
}
