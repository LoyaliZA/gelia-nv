<?php

namespace App\Http\Requests\PuntoVenta\Pantallas;

use App\Models\PuntoVenta\PdvPantallaPublicidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestionarPublicidadPantallaSalaPdvRequest extends FormRequest
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
        $requiereArchivo = $this->isMethod('POST');

        return [
            'sucursal_id' => ['required', 'integer', 'min:1'],
            'alcance' => ['required', Rule::in(['global', 'sucursal'])],
            'ajuste' => ['nullable', Rule::in([PdvPantallaPublicidad::AJUSTE_COVER, PdvPantallaPublicidad::AJUSTE_CONTAIN])],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activa' => ['nullable', 'boolean'],
            'duracion_seg' => ['nullable', 'integer', 'min:3', 'max:300'],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'archivo' => array_filter([
                $requiereArchivo ? 'required' : 'nullable',
                'file',
                'max:51200',
                'mimes:jpg,jpeg,png,webp,gif,mp4,webm,ogg',
            ]),
        ];
    }
}
