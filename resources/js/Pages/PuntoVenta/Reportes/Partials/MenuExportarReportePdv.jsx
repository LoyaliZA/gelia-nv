import React, { useState } from 'react';
import { Download, FileText, Loader2 } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import {
    estimarExportacionPesada,
    filtrosConjuntoParaApi,
    filtrosResguardosParaApi,
    filtrosTurnosOperacionParaApi,
    TIPO_REPORTE_CONJUNTO,
    TIPO_REPORTE_RESGUARDOS,
    TIPO_REPORTE_TURNOS_OPERACION,
} from '../reportesPdvUtils';
import {
    reintentarPdvReporteExportacion,
    solicitarPdvReporteExportacion,
    startPdvReporteTracking,
    urlDescargaPdvReporte,
} from '../../../../utils/pdvReporteTracker';

export default function MenuExportarReportePdv({
    tipoReporte,
    filtrosLocales = {},
    puedeExportar = false,
    exportacionesRecientes = [],
    onError,
    onJobIniciado,
}) {
    const [formato, setFormato] = useState('csv');
    const [cargando, setCargando] = useState(false);

    if (!puedeExportar) {
        return null;
    }

    const filtrosApi = () => {
        if (tipoReporte === TIPO_REPORTE_RESGUARDOS) {
            return { ...filtrosResguardosParaApi(filtrosLocales), tipo_reporte: tipoReporte, formato };
        }
        if (tipoReporte === TIPO_REPORTE_TURNOS_OPERACION) {
            return { ...filtrosTurnosOperacionParaApi(filtrosLocales), tipo_reporte: tipoReporte, formato };
        }
        return { ...filtrosConjuntoParaApi(filtrosLocales), tipo_reporte: tipoReporte, formato };
    };

    const solicitar = async () => {
        setCargando(true);
        onError?.(null);
        try {
            const payload = filtrosApi();
            const resultado = await solicitarPdvReporteExportacion(payload);
            if (resultado.modo === 'asincrono' || resultado.job_id) {
                const jobId = resultado.job_id || resultado.exportacion?.id;
                if (jobId) {
                    startPdvReporteTracking(jobId);
                    onJobIniciado?.(jobId);
                }
            }
        } catch (err) {
            onError?.(err.message || 'No se pudo solicitar la exportación.');
        } finally {
            setCargando(false);
        }
    };

    const reintentar = async (id) => {
        setCargando(true);
        onError?.(null);
        try {
            const resultado = await reintentarPdvReporteExportacion(id);
            const jobId = resultado.job_id || resultado.exportacion?.id;
            if (jobId) {
                startPdvReporteTracking(jobId);
                onJobIniciado?.(jobId);
            }
        } catch (err) {
            onError?.(err.message || 'No se pudo reintentar.');
        } finally {
            setCargando(false);
        }
    };

    const pesada = estimarExportacionPesada(tipoReporte, formato, filtrosApi());

    return (
        <div className={geliaCardClass('p-4 md:p-5 space-y-4')}>
            <div className="flex items-center gap-2">
                <Download className="w-4 h-4 theme-text-muted" />
                <h2 className="text-sm font-semibold theme-text-main m-0">Exportar</h2>
            </div>

            <div className="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                <label className="flex flex-col gap-1.5 flex-1 min-w-[140px]">
                    <span className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted">Formato</span>
                    <select
                        className="rounded-lg border theme-border theme-element px-3 py-2 text-sm"
                        value={formato}
                        onChange={(e) => setFormato(e.target.value)}
                    >
                        <option value="csv">CSV</option>
                        <option value="pdf">PDF</option>
                    </select>
                </label>
                <button type="button" className={THEME_BTN_PRIMARY} onClick={solicitar} disabled={cargando}>
                    {cargando ? <Loader2 className="w-4 h-4 animate-spin" /> : <FileText className="w-4 h-4" />}
                    {cargando ? 'Solicitando…' : 'Exportar con filtros actuales'}
                </button>
            </div>

            {pesada && (
                <p className="text-xs theme-text-muted m-0">
                    Esta exportación se procesará en segundo plano y no bloqueará la consulta.
                </p>
            )}

            {exportacionesRecientes.length > 0 && (
                <div className="border-t theme-border pt-3 space-y-2">
                    <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0">
                        Exportaciones recientes
                    </p>
                    <ul className="m-0 p-0 list-none space-y-2">
                        {exportacionesRecientes.map((exp) => (
                            <li key={exp.id} className="flex flex-col sm:flex-row sm:items-center gap-2 text-sm">
                                <span className="theme-text-main flex-1 min-w-0 truncate">
                                    {exp.titulo || exp.tipo_reporte_label} · {exp.formato_label}
                                </span>
                                <span className="theme-text-muted text-xs">{exp.estado_label}</span>
                                {exp.puede_descargar && (
                                    <a
                                        href={exp.descarga_url || urlDescargaPdvReporte(exp.id)}
                                        className={THEME_BTN_SECONDARY + ' text-xs py-1.5'}
                                    >
                                        Descargar
                                    </a>
                                )}
                                {exp.puede_reintentar && (
                                    <button
                                        type="button"
                                        className={THEME_BTN_SECONDARY + ' text-xs py-1.5'}
                                        onClick={() => reintentar(exp.id)}
                                        disabled={cargando}
                                    >
                                        Reintentar
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
