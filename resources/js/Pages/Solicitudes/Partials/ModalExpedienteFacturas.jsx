import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, FileSpreadsheet, FileText, Download, ChevronLeft, ChevronRight, ExternalLink, Copy, Check, Loader2 } from 'lucide-react';

const ETIQUETAS_DEFAULT = {
    rfc: 'RFC',
    codigo_postal: 'Código Postal',
    regimen_fiscal: 'Régimen Fiscal',
    correo_electronico: 'Correo Electrónico',
    uso_factura: 'Uso de Factura',
    nombre_razon_social: 'Nombre (Razón Social)',
};

const copiarTexto = (texto) => {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(texto);
    }
    const textArea = document.createElement('textarea');
    textArea.value = texto;
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
    } catch {
        // ignore
    }
    document.body.removeChild(textArea);
    return Promise.resolve();
};

export default function ModalExpedienteFacturas({ onClose, solicitud }) {
    if (!solicitud) return null;

    const remisiones = solicitud.remisiones_factura || solicitud.remisionesFactura || [];
    const [pdfIndex, setPdfIndex] = useState(0);
    const pdfActual = remisiones[pdfIndex];

    const [datosFiscales, setDatosFiscales] = useState(solicitud.factura_datos_fiscales || null);
    const [etiquetas, setEtiquetas] = useState(ETIQUETAS_DEFAULT);
    const [cargandoDatos, setCargandoDatos] = useState(false);
    const [errorDatos, setErrorDatos] = useState(null);
    const [copiadoKey, setCopiadoKey] = useState(null);

    useEffect(() => {
        if (datosFiscales || !solicitud.archivo_facturas_path) {
            return;
        }

        setCargandoDatos(true);
        setErrorDatos(null);

        fetch(route('solicitudes.datos_fiscales', solicitud.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                const json = await res.json();
                if (json.etiquetas) {
                    setEtiquetas(json.etiquetas);
                }
                if (json.datos) {
                    setDatosFiscales(json.datos);
                } else if (json.error) {
                    setErrorDatos(json.error);
                } else if (!res.ok) {
                    setErrorDatos('No se pudieron extraer los datos fiscales.');
                }
            })
            .catch(() => setErrorDatos('No se pudieron extraer los datos fiscales.'))
            .finally(() => setCargandoDatos(false));
    }, [solicitud.id, solicitud.archivo_facturas_path, datosFiscales]);

    const filasDatos = datosFiscales
        ? Object.keys(etiquetas).filter(k => datosFiscales[k] !== undefined && datosFiscales[k] !== null)
        : [];

    const copiarCampo = (clave, valor) => {
        copiarTexto(String(valor ?? ''));
        setCopiadoKey(clave);
        setTimeout(() => setCopiadoKey(null), 2000);
    };

    const copiarTodo = () => {
        if (!datosFiscales) return;
        const texto = filasDatos.map(k => `${etiquetas[k]}: ${datosFiscales[k]}`).join('\n');
        copiarTexto(texto);
        setCopiadoKey('__all__');
        setTimeout(() => setCopiadoKey(null), 2000);
    };

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className="w-full max-w-4xl theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto custom-scrollbar" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none"><X className="w-5 h-5" /></button>

                <div className="flex items-center gap-3 mb-6">
                    <FileText className="w-7 h-7 text-blue-500" />
                    <div>
                        <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Expediente de Factura_</h3>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1">FOL-{solicitud.id}</p>
                    </div>
                </div>

                <div className="p-4 rounded-2xl border theme-border theme-element mb-6">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Razón Social</p>
                    <p className="text-sm font-black theme-text-main italic">{solicitud.factura_razon_social || '—'}</p>
                </div>

                <div className="space-y-6">
                    <section>
                        <div className="flex items-center justify-between gap-3 mb-3">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Datos fiscales extraídos</p>
                            {datosFiscales && filasDatos.length > 0 && (
                                <button
                                    type="button"
                                    onClick={copiarTodo}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-[var(--color-primario)]/10 text-[var(--color-primario)] border border-[var(--color-primario)]/20 outline-none"
                                >
                                    {copiadoKey === '__all__' ? <Check className="w-3 h-3" /> : <Copy className="w-3 h-3" />}
                                    Copiar todo
                                </button>
                            )}
                        </div>

                        {cargandoDatos && (
                            <div className="flex items-center gap-2 p-4 rounded-2xl border border-dashed theme-border text-xs font-bold theme-text-muted italic">
                                <Loader2 className="w-4 h-4 animate-spin" /> Extrayendo datos del Excel…
                            </div>
                        )}

                        {!cargandoDatos && errorDatos && (
                            <p className="text-xs font-bold text-red-500 p-4 rounded-2xl border border-red-500/20 bg-red-500/5">{errorDatos}</p>
                        )}

                        {!cargandoDatos && !errorDatos && !datosFiscales && !solicitud.archivo_facturas_path && (
                            <p className="text-xs font-bold theme-text-muted italic p-4 rounded-2xl border border-dashed theme-border">Sin Excel adjunto — no hay datos fiscales para mostrar.</p>
                        )}

                        {!cargandoDatos && !errorDatos && datosFiscales && filasDatos.length > 0 && (
                            <div className="rounded-2xl border theme-border overflow-hidden">
                                <table className="w-full text-left">
                                    <thead>
                                        <tr className="theme-element border-b theme-border">
                                            <th className="px-4 py-3 text-[9px] font-black uppercase tracking-widest theme-text-muted w-[40%]">Campo</th>
                                            <th className="px-4 py-3 text-[9px] font-black uppercase tracking-widest theme-text-muted">Valor</th>
                                            <th className="px-4 py-3 w-12" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filasDatos.map(clave => (
                                            <tr key={clave} className="border-b theme-border last:border-b-0">
                                                <td className="px-4 py-3 text-[10px] font-black uppercase theme-text-muted align-top">{etiquetas[clave]}</td>
                                                <td className="px-4 py-3 text-xs font-bold theme-text-main break-all align-top">{datosFiscales[clave]}</td>
                                                <td className="px-4 py-3 align-top">
                                                    <button
                                                        type="button"
                                                        onClick={() => copiarCampo(clave, datosFiscales[clave])}
                                                        className="p-1.5 rounded-lg theme-element border theme-border theme-text-muted hover:text-[var(--color-primario)] outline-none"
                                                        title={`Copiar ${etiquetas[clave]}`}
                                                    >
                                                        {copiadoKey === clave ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Excel de datos fiscales</p>
                        {solicitud.archivo_facturas_path ? (
                            <div className="p-4 rounded-2xl border theme-border theme-element flex items-center justify-between gap-4">
                                <div className="flex items-center gap-3 min-w-0">
                                    <FileSpreadsheet className="w-8 h-8 text-emerald-500 shrink-0" />
                                    <span className="text-xs font-bold theme-text-main truncate">Archivo Excel adjunto</span>
                                </div>
                                <div className="flex gap-2 shrink-0">
                                    <a href={`/storage/${solicitud.archivo_facturas_path}`} target="_blank" rel="noreferrer" className="flex items-center gap-1 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-600 border border-blue-500/20">
                                        <ExternalLink className="w-3 h-3" /> Abrir
                                    </a>
                                    <a href={`/storage/${solicitud.archivo_facturas_path}`} download className="flex items-center gap-1 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest theme-element border theme-border">
                                        <Download className="w-3 h-3" /> Descargar
                                    </a>
                                </div>
                            </div>
                        ) : (
                            <p className="text-xs font-bold theme-text-muted italic p-4 rounded-2xl border border-dashed theme-border">Sin Excel adjunto</p>
                        )}
                    </section>

                    <section>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Remisiones PDF ({remisiones.length})</p>
                        {remisiones.length === 0 ? (
                            <p className="text-xs font-bold theme-text-muted italic p-4 rounded-2xl border border-dashed theme-border">Sin remisiones adjuntas</p>
                        ) : (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-[10px] font-bold theme-text-main truncate">{pdfActual?.nombre_original}</span>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <button type="button" disabled={pdfIndex <= 0} onClick={() => setPdfIndex(i => i - 1)} className="p-2 rounded-lg theme-element border theme-border disabled:opacity-30 outline-none"><ChevronLeft className="w-4 h-4" /></button>
                                        <span className="text-[9px] font-black theme-text-muted">{pdfIndex + 1}/{remisiones.length}</span>
                                        <button type="button" disabled={pdfIndex >= remisiones.length - 1} onClick={() => setPdfIndex(i => i + 1)} className="p-2 rounded-lg theme-element border theme-border disabled:opacity-30 outline-none"><ChevronRight className="w-4 h-4" /></button>
                                        <a href={`/storage/${pdfActual?.path}`} download className="p-2 rounded-lg theme-element border theme-border outline-none"><Download className="w-4 h-4" /></a>
                                    </div>
                                </div>
                                <iframe title={`Remisión ${pdfIndex + 1}`} src={`/storage/${pdfActual?.path}`} className="w-full h-[420px] rounded-2xl border theme-border bg-white" />
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </div>,
        document.body
    );
}
