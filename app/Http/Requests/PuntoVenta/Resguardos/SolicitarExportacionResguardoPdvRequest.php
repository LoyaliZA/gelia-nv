<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ResguardoPdvExportacionTipo;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarExportacionResguardoPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $alcance = app(ResuelveAlcancePdv::class);

        return $alcance->tieneAlcanceGlobal($user)
            && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', Rule::in(ResguardoPdvExportacionTipo::valores())],
            'bandeja' => ['sometimes', 'string', Rule::in(BandejaResguardoPdv::valores())],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'estado' => ['sometimes', 'nullable', 'string', Rule::in([
                ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                ResguardoPdv::ESTADO_EN_CUSTODIA,
                ResguardoPdv::ESTADO_ENTREGADO,
                ResguardoPdv::ESTADO_DEVUELTO,
            ])],
            'antiguedad' => ['sometimes', 'nullable', 'string', Rule::in(AntiguedadOperativaResguardoPdv::valores())],
            'sucursal_id' => ['sometimes', 'nullable', 'integer', 'exists:sucursales,id'],
            'resguardo_id' => ['required_if:tipo,'.ResguardoPdvExportacionTipo::AUDITORIA, 'nullable', 'integer', 'exists:pdv_resguardos,id'],
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
