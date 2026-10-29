<?php

namespace App\Http\Requests\PuntoVenta\Reportes;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsultarAuditoriaOperacionPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('Super Admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sucursal_id' => $this->input('sucursal_id') ?: null,
            'user_id' => $this->input('user_id') ?: null,
            'desde' => $this->input('desde') ?: null,
            'hasta' => $this->input('hasta') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'sucursal_id.exists' => 'La sucursal no existe.',
            'user_id.exists' => 'La persona no existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->consultaCompleta()) {
                return;
            }

            $desde = Carbon::parse((string) $this->input('desde'))->startOfDay();
            $hasta = Carbon::parse((string) $this->input('hasta'))->startOfDay();
            if ($desde->diffInDays($hasta) > 30) {
                $validator->errors()->add('hasta', 'El rango no puede superar 31 días.');
            }
        });
    }

    public function consultaCompleta(): bool
    {
        return $this->filled('sucursal_id')
            && $this->filled('user_id')
            && $this->filled('desde')
            && $this->filled('hasta');
    }

    /**
     * @return array{sucursal_id: int, user_id: int, desde: Carbon, hasta: Carbon}|null
     */
    public function filtrosConsulta(): ?array
    {
        if (! $this->consultaCompleta()) {
            return null;
        }

        return [
            'sucursal_id' => (int) $this->input('sucursal_id'),
            'user_id' => (int) $this->input('user_id'),
            'desde' => Carbon::parse((string) $this->input('desde'))->startOfDay(),
            'hasta' => Carbon::parse((string) $this->input('hasta'))->endOfDay(),
        ];
    }
}
