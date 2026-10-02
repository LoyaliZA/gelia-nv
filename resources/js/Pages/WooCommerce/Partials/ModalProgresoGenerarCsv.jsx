import React from 'react';
import { createPortal } from 'react-dom';
import { Download, FileSpreadsheet, AlertTriangle } from 'lucide-react';
import GeliaLogo from '../../../Components/GeliaLogo';
import {
    calcularPorcentajeExportCsv,
    contadorExportCsv,
    etiquetaFaseExportCsv,
} from '../../../utils/wooCsvExportProgress';

export default function ModalProgresoGenerarCsv({ log, onClose }) {
    if (!log) return null;

    const completado = log.estado === 'completado';
    const error = log.estado === 'error';
    const resultado = log.payload?.resultado;
    const pct = calcularPorcentajeExportCsv(log);
    const contador = contadorExportCsv(log);
    const enAplicacion = log.payload?.fase === 'aplicando' || log.payload?.fase === 'generando_csv' || completado;

    return createPortal(
        <div className="fixed inset-0 z-[99999] flex flex-col items-center justify-center p-4 bg-black/80 backdrop-blur-xl animate-fade-in">
            <div className="theme-surface border border-white/20 dark:border-zinc-700/50 p-8 sm:p-10 rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.5)] flex flex-col items-center gap-6 relative overflow-hidden max-w-md w-full">
                <div
                    className="absolute inset-0 opacity-20 animate-pulse pointer-events-none"
                    style={{ background: 'radial-gradient(circle at center, var(--color-primario) 0%, transparent 70%)' }}
                />

                {completado ? (
                    <GeliaLogo variant="sparkle" className="w-24 h-24 relative z-10 drop-shadow-2xl" />
                ) : (
                    <GeliaLogo variant="fluid-fill" progress={pct} className="w-24 h-24 relative z-10 drop-shadow-2xl" />
                )}

                <div className="text-center space-y-2 relative z-10 w-full">
                    <h3 className="text-lg font-black uppercase italic tracking-tight theme-text-main m-0">
                        {error ? 'No se pudo generar el CSV' : completado ? 'CSV listo' : 'Generando CSV WooCommerce'}
                    </h3>

                    {!completado && !error && (
                        <>
                            <p className="text-3xl font-black m-0" style={{ color: 'var(--color-primario)' }}>
                                {pct}%
                            </p>
                            <p className="text-xs font-bold theme-text-muted m-0">{etiquetaFaseExportCsv(log)}</p>
                        </>
                    )}

                    {contador && !error && (
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 pt-1">
                            {contador.etiqueta}: {contador.actual} / {contador.total}
                            {enAplicacion && log.payload?.cambios_detectados && !completado ? (
                                <span className="block mt-1 normal-case tracking-normal font-bold theme-text-main">
                                    Total detectados: {log.payload.cambios_detectados}
                                </span>
                            ) : null}
                        </p>
                    )}

                    {!completado && !error && (
                        <div className="w-full h-2 rounded-full theme-element overflow-hidden mt-3">
                            <div
                                className="h-full transition-all duration-500 rounded-full"
                                style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }}
                            />
                        </div>
                    )}

                    {error && (
                        <div className="flex items-start gap-2 text-left p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm font-bold">
                            <AlertTriangle className="w-5 h-5 shrink-0" />
                            <span>{log.mensaje_error || 'Ocurrió un error durante la exportación.'}</span>
                        </div>
                    )}

                    {completado && resultado && (
                        <div className="space-y-4 text-left w-full pt-2">
                            <p className="text-sm font-bold theme-text-main m-0 text-center">{resultado.message}</p>
                            <div className="grid grid-cols-2 gap-2 text-center">
                                <div className="rounded-xl border theme-border px-3 py-2">
                                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">En CSV</p>
                                    <p className="text-lg font-black theme-text-main m-0">{resultado.productos_exportados}</p>
                                </div>
                                <div className="rounded-xl border theme-border px-3 py-2">
                                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">BD GELIANV</p>
                                    <p className="text-lg font-black theme-text-main m-0">{resultado.productos_actualizados_local}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 p-3 rounded-xl border theme-border bg-black/5 dark:bg-white/5">
                                <FileSpreadsheet className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                <span className="text-[10px] font-mono font-bold theme-text-main truncate">
                                    {resultado.nombre_archivo}
                                </span>
                                <span className="text-[9px] font-bold theme-text-muted shrink-0">{resultado.tamano_kb} KB</span>
                            </div>
                            <a
                                href={resultado.download_url}
                                className="w-full inline-flex items-center justify-center gap-2 px-6 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-lg transition-all hover:scale-[1.02]"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Download className="w-4 h-4" /> Descargar CSV
                            </a>
                        </div>
                    )}

                    {(completado || error) && (
                        <button
                            type="button"
                            onClick={onClose}
                            className="w-full mt-2 px-6 py-3 rounded-2xl border theme-border font-black uppercase tracking-widest text-[10px] theme-text-main hover:border-[var(--color-primario)] transition-all"
                        >
                            {completado ? 'Cerrar' : 'Entendido'}
                        </button>
                    )}

                    {!completado && !error && (
                        <p className="text-[10px] font-bold theme-text-muted tracking-widest uppercase mt-4 m-0">
                            Por favor, no cierres esta ventana
                        </p>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
