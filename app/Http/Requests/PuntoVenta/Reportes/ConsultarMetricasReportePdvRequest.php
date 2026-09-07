<?php

namespace App\Http\Requests\PuntoVenta\Reportes;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\ReportePdvExportacionTipo;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarMetricasReportePdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $alcance = app(ResuelveAlcancePdv::class);
        if (! $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_ACCEDER)) {
            return false;
        }

        $tipo = $this->tipoReporte();

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

    public function tipoReporte(): string
    {
        $nombreRuta = (string) ($this->route()?->getName() ?? '');

        return match ($nombreRuta) {
            'punto_venta.reportes.resguardos' => ReportePdvExportacionTipo::RESGUARDOS,
            'punto_venta.reportes.turnos_operacion' => ReportePdvExportacionTipo::TURNOS_OPERACION,
            'punto_venta.reportes.conjunto' => ReportePdvExportacionTipo::CONJUNTO,
            default => (string) $this->input('tipo_reporte', ReportePdvExportacionTipo::CONJUNTO),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
        $filtros = $this->validated();
        $filtros['tipo_reporte'] = $this->tipoReporte();

        return $filtros;
    }

    public function puedeExportar(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return app(ResuelveAlcancePdv::class)->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR);
    }

    public function tieneAlcanceGlobal(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return app(AlcancePdv::class)->tieneAlcanceGlobal($user);
    }
}
