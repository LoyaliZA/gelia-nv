<?php

namespace App\Http\Requests\PuntoVenta\Alertas;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPreferenciasAlertasPdvRequest extends FormRequest
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
        return [
            'canales' => ['required', 'array'],
            'canales.sonido' => ['required', 'boolean'],
            'canales.voz' => ['required', 'boolean'],
            'canales.web_push' => ['required', 'boolean'],
            'tono_id' => ['required', 'string', 'max:64'],
        ];
    }
}
