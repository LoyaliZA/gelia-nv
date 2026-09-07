import { describe, expect, it } from 'vitest';
import {
    bloqueMetricas,
    estadoFiltrosDesdePayload,
    estimarExportacionPesada,
    fechaLocalDesdeIso,
    filtrosResguardosParaApi,
    formatearDuracion,
    formatearValorMetrica,
    isoDesdeFechaLocal,
    paramsFiltrosReportePdv,
    puedeVerTipoReporte,
    TIPO_REPORTE_CONJUNTO,
    TIPO_REPORTE_RESGUARDOS,
    TIPO_REPORTE_TURNOS_OPERACION,
} from './reportesPdvUtils';

describe('reportesPdvUtils', () => {
    it('omite valores vacíos en parámetros de consulta', () => {
        expect(paramsFiltrosReportePdv({ desde: '2026-09-01', hasta: '', sucursal_id: null }))
            .toEqual({ desde: '2026-09-01' });
    });

    it('convierte fechas locales a ISO para API', () => {
        const iso = isoDesdeFechaLocal('2026-09-07');
        expect(iso).toMatch(/^2026-09-07/);
        expect(fechaLocalDesdeIso(iso)).toBe('2026-09-07');
    });

    it('arma filtros de resguardos sin recalcular métricas', () => {
        expect(filtrosResguardosParaApi({
            desde: '2026-09-01',
            hasta: '2026-09-07',
            sucursal_id: '3',
            estado: '',
        })).toMatchObject({
            sucursal_id: '3',
        });
    });

    it('formatea porcentajes y duraciones desde payload backend', () => {
        expect(formatearValorMetrica({ unidad: 'porcentaje', valor: 12.5 })).toBe('12.5%');
        expect(formatearDuracion(125)).toBe('2m 5s');
        expect(formatearValorMetrica({ unidad: 'entero', valor: 4 })).toBe('4');
    });

    it('selecciona bloque de métricas por dominio sin mezclar tablas', () => {
        const payload = {
            resguardos: { metricas: { 'R-01': { valor: 1 } } },
            turnos_operacion: { metricas: { 'T-01': { valor: 2 } } },
        };
        expect(bloqueMetricas(payload, 'resguardos', TIPO_REPORTE_RESGUARDOS)['R-01'].valor).toBe(1);
        expect(bloqueMetricas(payload, 'turnos', TIPO_REPORTE_CONJUNTO)['T-01'].valor).toBe(2);
    });

    it('restaura filtros desde payload normalizado', () => {
        const estado = estadoFiltrosDesdePayload({
            desde: '2026-09-01T00:00:00-06:00',
            hasta: '2026-09-08T00:00:00-06:00',
            sucursal_id: 2,
        }, TIPO_REPORTE_RESGUARDOS);
        expect(estado.desde).toBe('2026-09-01');
        expect(estado.sucursal_id).toBe(2);
    });

    it('respeta permisos de vistas disponibles', () => {
        const vistas = { resguardos: true, turnos_operacion: false, conjunto: false };
        expect(puedeVerTipoReporte(vistas, TIPO_REPORTE_RESGUARDOS)).toBe(true);
        expect(puedeVerTipoReporte(vistas, TIPO_REPORTE_TURNOS_OPERACION)).toBe(false);
        expect(puedeVerTipoReporte(vistas, TIPO_REPORTE_CONJUNTO)).toBe(false);
    });

    it('estima exportación pesada para PDF y conjunto', () => {
        expect(estimarExportacionPesada(TIPO_REPORTE_CONJUNTO, 'csv', {})).toBe(true);
        expect(estimarExportacionPesada(TIPO_REPORTE_RESGUARDOS, 'pdf', {})).toBe(true);
        expect(estimarExportacionPesada(
            TIPO_REPORTE_RESGUARDOS,
            'csv',
            { desde: '2026-01-01', hasta: '2026-02-15' },
        )).toBe(true);
    });
});
