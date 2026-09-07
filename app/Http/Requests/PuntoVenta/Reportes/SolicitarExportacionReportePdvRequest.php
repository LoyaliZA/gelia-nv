<?php

namespace App\Http\Requests\PuntoVenta\Reportes;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\ReportePdvExportacionFormato;
use App\Services\PuntoVenta\Reportes\ReportePdvExportacionTipo;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarExportacionReportePdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $alcance = app(ResuelveAlcancePdv::class);
        if (! $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR)) {
            return false;
        }

        $tipo = (string) $this->input('tipo_reporte', ReportePdvExportacionTipo::CONJUNTO);

        if (in_array($tipo, [ReportePdvExportacionTipo::RESGUARDOS, ReportePdvExportacionTipo::CONJUNTO], true)) {
            if (! $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER)) {
                return false;
            }

            if (! $alcance->tieneAlcanceGlobal($user)) {
                return false;
            }
        }

        if (in_array($tipo, [ReportePdvExportacionTipo::TURNOS_OPERACION, ReportePdvExportacionTipo::CONJUNTO], true)) {
            if (! $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_reporte' => ['required', 'string', Rule::in(ReportePdvExportacionTipo::valores())],
            'formato' => ['required', 'string', Rule::in(ReportePdvExportacionFormato::valores())],
            'desde' => ['sometimes', 'nullable', 'date'],
            'hasta' => ['sometimes', 'nullable', 'date', 'after_or_equal:desde'],
            'corte_reporte_at' => ['sometimes', 'nullable', 'date'],
            'sucursal_id' => ['sometimes', 'nullable', 'integer', 'exists:sucursales,id'],
            'estado' => ['sometimes', 'nullable', 'string', Rule::in([
                ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                ResguardoPdv::ESTADO_EN_CUSTODIA,
                ResguardoPdv::ESTADO_ENTREGADO,
                ResguardoPdv::ESTADO_DEVUELTO,
            ])],
            'antiguedad' => ['sometimes', 'nullable', 'string', Rule::in(AntiguedadOperativaResguardoPdv::valores())],
            'tipo_incidencia' => ['sometimes', 'nullable', 'string', Rule::in([
                ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
                ResguardoPdvIncidencia::TIPO_DANO,
                ResguardoPdvIncidencia::TIPO_FALTANTE,
            ])],
            'servicio' => ['sometimes', 'nullable', 'string', Rule::in([TurnoPdv::SERVICIO_VENTAS])],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'fecha_operativa' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'franja_desde' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'franja_hasta' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'alcance' => ['sometimes', 'nullable', 'string', Rule::in([
                MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO,
                MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO,
                MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL,
            ])],
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
