<?php

namespace App\Http\Requests\PuntoVenta\VisitasProgramadas;

use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmarLlegadaVisitaProgramadaPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
