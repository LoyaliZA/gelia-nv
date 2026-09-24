<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarAuditoriaResguardoPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $resguardo = $this->route('resguardo');
        if (! $resguardo instanceof ResguardoPdv) {
            return false;
        }

        return app(AutorizacionConsultaResguardoPdv::class)->permiteDetalleResguardo($user, $resguardo);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_evento' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(EtiquetasResguardoPdv::eventos()))],
            'categoria' => ['sometimes', 'nullable', 'string', Rule::in([
                'recepcion',
                'incidencia',
                'entrega',
                'devolucion',
                'correccion',
                'sistema',
                'integracion',
                'operacion',
            ])],
            'desde' => ['sometimes', 'nullable', 'date'],
            'hasta' => ['sometimes', 'nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo_evento' => $this->filled('tipo_evento') ? (string) $this->input('tipo_evento') : null,
            'categoria' => $this->filled('categoria') ? (string) $this->input('categoria') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return $this->validated();
    }
}
