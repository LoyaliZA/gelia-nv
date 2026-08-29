import React from 'react';
import { CheckCircle2, Download, FileSpreadsheet, FileText, RefreshCw } from 'lucide-react';

function IconoFormato({ formato }) {
    if (formato?.startsWith('csv')) return <FileSpreadsheet className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />;
    return <FileText className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />;
}

export default function VistaCompletadoPagosPedidos({ resultado, onDescargar, onGenerarOtro, onCerrar }) {
    const registrosLabel = resultado?.num_paginas
        ? `${resultado.num_paginas} páginas · ${(resultado.num_registros ?? 0).toLocaleString('es-MX')} pedidos`
        : `${(resultado?.num_registros ?? 0).toLocaleString('es-MX')} registros`;

    return (
        <div className="p-8 md:p-10 flex flex-col items-center gap-6 flex-1 justify-center min-h-[22rem]">
            <div className="w-16 h-16 rounded-full flex items-center justify-center bg-green-500/10">
                <CheckCircle2 className="w-9 h-9 text-green-600" />
            </div>

            <div className="text-center space-y-1">
                <h3 className="text-xl font-black uppercase italic tracking-tighter theme-text-main m-0">Reporte listo</h3>
                <p className="text-sm theme-text-muted m-0">El archivo quedó disponible para descarga.</p>
            </div>

            <div className="w-full max-w-md rounded-2xl border theme-border p-5 space-y-4">
                <div className="flex items-start gap-3">
                    <IconoFormato formato={resultado?.formato} />
                    <div className="min-w-0">
                        <p className="text-sm font-bold theme-text-main m-0 truncate">{resultado?.nombre_archivo || '—'}</p>
                        <p className="text-xs theme-text-muted m-0">{resultado?.formato_label || resultado?.formato}</p>
                    </div>
                </div>

                <dl className="grid grid-cols-2 gap-3 text-sm m-0">
                    <div>
                        <dt className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Tamaño</dt>
                        <dd className="font-semibold theme-text-main m-0 mt-0.5">{resultado?.tamano_etiqueta || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Alcance</dt>
                        <dd className="font-semibold theme-text-main m-0 mt-0.5">{registrosLabel}</dd>
                    </div>
                    <div className="col-span-2">
                        <dt className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Expira</dt>
                        <dd className="font-semibold theme-text-main m-0 mt-0.5">{resultado?.expira_etiqueta || '—'}</dd>
                    </div>
                </dl>
            </div>

            <div className="flex flex-wrap gap-3 justify-center w-full max-w-md">
                <button
                    type="button"
                    onClick={onDescargar}
                    className="px-6 py-2.5 rounded-xl text-sm font-bold text-white flex items-center gap-2"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Download className="w-4 h-4" />
                    Descargar
                </button>
                <button
                    type="button"
                    onClick={onGenerarOtro}
                    className="px-5 py-2.5 rounded-xl border theme-border text-sm font-semibold theme-text-main flex items-center gap-2 hover:border-[var(--color-primario)] transition-colors"
                >
                    <RefreshCw className="w-4 h-4" />
                    Generar otro
                </button>
                <button
                    type="button"
                    onClick={onCerrar}
                    className="px-5 py-2.5 rounded-xl border theme-border text-sm font-semibold theme-text-muted hover:theme-text-main transition-colors"
                >
                    Cerrar
                </button>
            </div>
        </div>
    );
}
