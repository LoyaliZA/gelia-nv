<?php

namespace App\Http\Requests\PuntoVenta\Alertas;

use Illuminate\Foundation\Http\FormRequest;

class LatidoTerminalAlertasSucursalPdvRequest extends FormRequest
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
            'sucursal_id' => ['required', 'integer', 'min:1'],
            'terminal_id' => ['required', 'uuid'],
        ];
    }
}
