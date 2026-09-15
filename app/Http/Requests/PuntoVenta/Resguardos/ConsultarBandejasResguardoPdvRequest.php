<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarBandejasResguardoPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return app(ResuelveAlcancePdv::class)->permiteConsultaPiso(
            $user,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bandeja' => ['sometimes', 'string', Rule::in(BandejaResguardoPdv::valores())],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'paso' => ['sometimes', 'nullable', 'string', Rule::in(BandejaResguardoPdv::pasosPorRecibir())],
            'estado' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(EtiquetasResguardoPdv::estados()))],
            'antiguedad' => ['sometimes', 'nullable', 'string', Rule::in(AntiguedadOperativaResguardoPdv::valores())],
            'sucursal_id' => ['sometimes', 'nullable', 'integer', 'exists:sucursales,id'],
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
