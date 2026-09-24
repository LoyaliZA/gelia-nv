<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Models\User;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarHistorialEntregadosResguardoPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return app(AutorizacionConsultaResguardoPdv::class)->permiteHistorialEntregados($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'desde' => ['sometimes', 'nullable', 'date'],
            'hasta' => ['sometimes', 'nullable', 'date', 'after_or_equal:desde'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return $this->validated();
    }
}
