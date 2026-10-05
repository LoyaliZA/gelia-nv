<?php

namespace App\Http\Requests\Comercial;

use App\Models\Comercial\VisitaClienteProgramada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarVisitaProgramadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('visitas_programadas.gestionar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'tipo_hora' => ['required', Rule::in([
                VisitaClienteProgramada::TIPO_HORA_SIN,
                VisitaClienteProgramada::TIPO_HORA_EXACTA,
                VisitaClienteProgramada::TIPO_HORA_RANGO,
            ])],
            'hora_exacta' => ['nullable', 'date_format:H:i'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'intencion' => ['required', Rule::in([
                VisitaClienteProgramada::INTENCION_CONFIRMO,
                VisitaClienteProgramada::INTENCION_POSIBLE,
            ])],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
