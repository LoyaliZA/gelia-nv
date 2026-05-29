import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, FileSpreadsheet, FileText, Download, ChevronLeft, ChevronRight, Copy, Check, Loader2, Receipt } from 'lucide-react';
import { ACCENT, esImagenVoucher, esPdfVoucher, urlArchivoFactura } from './facturasStyles';
import { geliaCardClass } from '../../../utils/geliaTheme';

const ETIQUETAS_DEFAULT = {
    rfc: 'RFC',
    codigo_postal: 'Código Postal',
    regimen_fiscal: 'Régimen Fiscal',
    correo_electronico: 'Correo Electrónico',
    uso_factura: 'Uso de Factura',
    nombre_razon_social: 'Nombre (Razón Social)',
};

const copiarTexto = (texto) => {
    if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(texto);
    const ta = document.createElement('textarea');
    ta.value = texto;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    return Promise.resolve();
};

export default function ModalExpedienteFactura({ onClose, factura: facturaInicial }) {
    const [factura, setFactura] = useState(facturaInicial);
    const [cargandoFactura, setCargandoFactura] = useState(true);
    const [errorFactura, setErrorFactura] = useState(null);

    const vouchers = factura?.vouchers || [];
    const [voucherIndex, setVoucherIndex] = useState(0);
    const voucherActual = vouchers[voucherIndex];

    const [datosFiscales, setDatosFiscales] = useState(facturaInicial?.datos_fiscales || null);
    const [etiquetas, setEtiquetas] = useState(ETIQUETAS_DEFAULT);
    const [cargandoDatos, setCargandoDatos] = useState(false);
    const [errorDatos, setErrorDatos] = useState(null);
    const [copiadoKey, setCopiadoKey] = useState(null);

    useEffect(() => {
        if (!facturaInicial?.id) return;

        setCargandoFactura(true);
        setErrorFactura(null);
        setVoucherIndex(0);

        fetch(route('facturas.show', facturaInicial.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) throw new Error('No se pudo cargar el expediente.');
                const json = await res.json();
                if (json.factura) {
                    setFactura(json.factura);
                    if (json.factura.datos_fiscales) {
                        setDatosFiscales(json.factura.datos_fiscales);
                    }
                }
            })
            .catch((err) => setErrorFactura(err.message || 'Error al cargar expediente.'))
            .finally(() => setCargandoFactura(false));
    }, [facturaInicial?.id]);

    useEffect(() => {
        if (!factura?.id || datosFiscales || !factura.tiene_archivo_fiscal) return;

        setCargandoDatos(true);
        fetch(route('facturas.datos_fiscales', factura.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                const json = await res.json();
                if (json.etiquetas) setEtiquetas(json.etiquetas);
                if (json.datos) setDatosFiscales(json.datos);
                else if (json.error) setErrorDatos(json.error);
            })
            .catch(() => setErrorDatos('No se pudieron extraer los datos fiscales.'))
            .finally(() => setCargandoDatos(false));
    }, [factura?.id, factura?.tiene_archivo_fiscal, datosFiscales]);

    if (!facturaInicial) return null;

    const filasDatos = datosFiscales
        ? Object.keys(etiquetas).filter(k => datosFiscales[k] !== undefined && datosFiscales[k] !== '')
        : [];

    const copiarCampo = (clave, valor) => {
        copiarTexto(String(valor ?? ''));
        setCopiadoKey(clave);
        setTimeout(() => setCopiadoKey(null), 2000);
    };

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className={`w-full max-w-4xl ${geliaCardClass('rounded-[2rem] p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto custom-scrollbar')}`} onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none"><X className="w-5 h-5" /></button>

                <div className="flex items-center gap-3 mb-6">
                    <Receipt className="w-7 h-7" style={{ color: ACCENT }} />
                    <div>
                        <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Expediente Fiscal_</h3>
                        <p className="text-[10px] font-mono font-bold theme-text-muted mt-1">{factura?.folio || facturaInicial.folio}</p>
                    </div>
                </div>

                {cargandoFactura && (
                    <div className="flex items-center gap-2 p-6 text-xs font-bold theme-text-muted italic mb-4">
                        <Loader2 className="w-4 h-4 animate-spin" /> Cargando archivos adjuntos…
                    </div>
                )}

                {errorFactura && (
                    <p className="text-xs font-bold text-red-500 p-4 rounded-2xl border border-red-500/20 bg-red-500/5 mb-4">{errorFactura}</p>
                )}

                {!cargandoFactura && factura && (
                    <>
                        <div className="p-4 rounded-2xl border theme-border mb-6 theme-element">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Razón Social</p>
                            <p className="text-sm font-black theme-text-main">{factura.razon_social || '—'}</p>
                        </div>

                        <div className="space-y-6">
                            <section>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Datos fiscales</p>
                                {cargandoDatos && (
                                    <div className="flex items-center gap-2 p-4 text-xs font-bold theme-text-muted italic">
                                        <Loader2 className="w-4 h-4 animate-spin" /> Extrayendo datos…
                                    </div>
                                )}
                                {!cargandoDatos && errorDatos && (
                                    <p className="text-xs font-bold text-red-500 p-4 rounded-2xl border border-red-500/20 bg-red-500/5">{errorDatos}</p>
                                )}
                                {!cargandoDatos && !errorDatos && filasDatos.length > 0 && (
                                    <div className="rounded-2xl border theme-border overflow-hidden">
                                        <table className="w-full text-left">
                                            <tbody>
                                                {filasDatos.map(clave => (
                                                    <tr key={clave} className="border-b theme-border last:border-b-0">
                                                        <td className="px-4 py-3 text-[10px] font-black uppercase theme-text-muted w-[40%]">{etiquetas[clave]}</td>
                                                        <td className="px-4 py-3 text-xs font-bold theme-text-main break-all">{datosFiscales[clave]}</td>
                                                        <td className="px-4 py-3">
                                                            <button type="button" onClick={() => copiarCampo(clave, datosFiscales[clave])} className="p-1.5 rounded-lg theme-element border theme-border outline-none">
                                                                {copiadoKey === clave ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {!cargandoDatos && !datosFiscales && !factura.tiene_archivo_fiscal && (
                                    <p className="text-xs italic theme-text-muted p-4 border border-dashed theme-border rounded-2xl">Sin datos fiscales registrados.</p>
                                )}
                            </section>

                            {factura.tiene_archivo_fiscal && (
                                <section>
                                    <p className="text-[10px] font-black uppercase theme-text-muted mb-3">Excel fiscal</p>
                                    <a
                                        href={urlArchivoFactura(factura.id, 'fiscal')}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-element hover:border-[var(--color-primario)]"
                                    >
                                        <FileSpreadsheet className="w-4 h-4" style={{ color: ACCENT }} /> Descargar / ver Excel
                                    </a>
                                </section>
                            )}

                            <section>
                                <p className="text-[10px] font-black uppercase theme-text-muted mb-3">Vouchers ({vouchers.length})</p>
                                {vouchers.length === 0 ? (
                                    <p className="text-xs italic theme-text-muted">Sin vouchers adjuntos.</p>
                                ) : (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-[10px] font-bold truncate">{voucherActual?.nombre_original || 'Voucher'}</span>
                                            <div className="flex items-center gap-2 shrink-0">
                                                <button type="button" disabled={voucherIndex <= 0} onClick={() => setVoucherIndex(i => i - 1)} className="p-2 rounded-lg theme-element border theme-border disabled:opacity-30 outline-none"><ChevronLeft className="w-4 h-4" /></button>
                                                <span className="text-[9px] font-black">{voucherIndex + 1}/{vouchers.length}</span>
                                                <button type="button" disabled={voucherIndex >= vouchers.length - 1} onClick={() => setVoucherIndex(i => i + 1)} className="p-2 rounded-lg theme-element border theme-border disabled:opacity-30 outline-none"><ChevronRight className="w-4 h-4" /></button>
                                                <a href={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} target="_blank" rel="noopener noreferrer" className="p-2 rounded-lg theme-element border theme-border"><Download className="w-4 h-4" /></a>
                                            </div>
                                        </div>
                                        {esImagenVoucher(voucherActual) ? (
                                            <img src={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} alt="Voucher" className="w-full max-h-[360px] object-contain rounded-2xl border theme-border bg-white" />
                                        ) : esPdfVoucher(voucherActual) ? (
                                            <iframe title="Voucher PDF" src={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} className="w-full h-[360px] rounded-2xl border theme-border bg-white" />
                                        ) : (
                                            <a href={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 text-xs font-bold" style={{ color: ACCENT }}>
                                                Abrir archivo adjunto
                                            </a>
                                        )}
                                    </div>
                                )}
                            </section>

                            {(factura.tiene_pdf_emitido || factura.tiene_xml) && (
                                <section>
                                    <p className="text-[10px] font-black uppercase theme-text-muted mb-3">Factura emitida</p>
                                    <div className="flex flex-wrap gap-2">
                                        {factura.tiene_pdf_emitido && (
                                            <a href={urlArchivoFactura(factura.id, 'pdf')} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase theme-element border theme-border hover:border-[var(--color-primario)]">
                                                <FileText className="w-4 h-4" style={{ color: ACCENT }} /> PDF Factura
                                            </a>
                                        )}
                                        {factura.tiene_xml && (
                                            <a href={urlArchivoFactura(factura.id, 'xml')} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase theme-element border theme-border">
                                                <Download className="w-4 h-4" /> XML CFDI
                                            </a>
                                        )}
                                    </div>
                                    {factura.tiene_pdf_emitido && (
                                        <iframe title="Factura PDF" src={urlArchivoFactura(factura.id, 'pdf')} className="w-full h-[420px] mt-4 rounded-2xl border theme-border bg-white" />
                                    )}
                                </section>
                            )}
                        </div>
                    </>
                )}
            </div>
        </div>,
        document.body
    );
}
