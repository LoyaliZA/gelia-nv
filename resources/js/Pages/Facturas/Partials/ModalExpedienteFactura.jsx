import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, FileSpreadsheet, Download, ChevronLeft, ChevronRight, Copy, Check, Loader2, Receipt, AlertOctagon, RefreshCw } from 'lucide-react';
import { ACCENT, BTN_PRIMARY, BTN_SECONDARY, esImagenVoucher, esPdfVoucher, urlArchivoFactura, nombreArchivoFacturaPdf } from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

const ETIQUETAS_DEFAULT = {
    rfc: 'RFC',
    codigo_postal: 'Código Postal',
    regimen_fiscal: 'Régimen Fiscal',
    correo_electronico: 'Correo Electrónico',
    uso_factura: 'Uso de Factura',
    nombre_razon_social: 'Nombre (Razón Social)',
    telefono: 'Número Telefónico',
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

export default function ModalExpedienteFactura({ onClose, factura: facturaInicial, puedeActualizarCliente = false }) {
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
    const [aplicandoCliente, setAplicandoCliente] = useState(false);
    const [mensajeSync, setMensajeSync] = useState(null);
    const [errorSync, setErrorSync] = useState(null);

    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = 'unset'; };
    }, []);

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

    const puedeSyncCliente = puedeActualizarCliente
        && factura?.cliente_id
        && !cargandoDatos
        && !errorDatos
        && filasDatos.length > 0;

    const copiarCampo = (clave, valor) => {
        copiarTexto(String(valor ?? ''));
        setCopiadoKey(clave);
        setTimeout(() => setCopiadoKey(null), 2000);
    };

    const aplicarDatosAlCliente = () => {
        if (!factura?.id || aplicandoCliente) return;
        setAplicandoCliente(true);
        setMensajeSync(null);
        setErrorSync(null);
        fetch(route('facturas.aplicar_datos_fiscales_cliente', factura.id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.message || 'No se pudieron actualizar los datos del cliente.');
                setMensajeSync(json.message || 'Datos fiscales del cliente actualizados.');
            })
            .catch((err) => setErrorSync(err.message || 'Error al actualizar el cliente.'))
            .finally(() => setAplicandoCliente(false));
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-4xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <Receipt className="w-7 h-7 shrink-0" style={{ color: ACCENT }} />
                        <div className="min-w-0">
                            <h3 className="text-lg font-black italic uppercase theme-text-main m-0 leading-tight">Expediente fiscal</h3>
                            <p className="text-[10px] font-mono font-bold theme-text-muted mt-1 m-0">{factura?.folio || facturaInicial.folio}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 theme-text-muted hover:theme-text-main rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0">
                    {cargandoFactura && (
                        <div className="flex items-center gap-2 p-4 text-xs font-bold theme-text-muted italic mb-4">
                            <Loader2 className="w-4 h-4 animate-spin" /> Cargando archivos adjuntos…
                        </div>
                    )}

                    {errorFactura && (
                        <p className="text-xs font-bold text-red-600 dark:text-red-400 p-4 rounded-2xl border border-red-500/20 bg-red-500/5 mb-4">{errorFactura}</p>
                    )}

                    {!cargandoFactura && factura && (
                        <>
                            <div className="p-4 rounded-2xl border theme-border mb-6 theme-element">
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Razón Social</p>
                                <p className="text-sm font-black theme-text-main m-0">{factura.razon_social || '—'}</p>
                            </div>

                            <div className="space-y-6">
                                {((factura.estado?.nombre === 'Incorrecta') || factura.evidencia_error_path) && (
                                    <section>
                                        <p className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 mb-3">
                                            <AlertOctagon className="w-3.5 h-3.5" /> Error Reportado
                                        </p>
                                        {factura.motivo_respuesta && (
                                            <div className="p-4 rounded-2xl border border-red-500/20 bg-red-500/5 mb-4">
                                                <p className="text-sm font-bold text-red-600 dark:text-red-400 m-0 whitespace-pre-wrap">{factura.motivo_respuesta}</p>
                                            </div>
                                        )}
                                        {factura.evidencia_error_path && (
                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between gap-2 px-1">
                                                    <span className="text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-widest">Evidencia del Error</span>
                                                    <a href={urlArchivoFactura(factura.id, 'evidencia_error')} target="_blank" rel="noopener noreferrer" className={`${BTN_SECONDARY} !p-2 !border-red-500/30 !text-red-600 dark:!text-red-400 hover:!bg-red-500/10`}>
                                                        <Download className="w-4 h-4" />
                                                    </a>
                                                </div>
                                                {factura.evidencia_error_path.toLowerCase().endsWith('.pdf') ? (
                                                    <iframe title="Evidencia Error PDF" src={urlArchivoFactura(factura.id, 'evidencia_error')} className="w-full h-[360px] rounded-2xl border border-red-500/30 bg-white" />
                                                ) : (
                                                    <div className="rounded-2xl border border-red-500/30 overflow-hidden bg-white">
                                                        <img src={urlArchivoFactura(factura.id, 'evidencia_error')} alt="Evidencia Error" className="w-full max-h-[360px] object-contain" />
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </section>
                                )}

                                <section>
                                    <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Datos fiscales</p>
                                        {puedeSyncCliente && (
                                            <button
                                                type="button"
                                                onClick={aplicarDatosAlCliente}
                                                disabled={aplicandoCliente}
                                                className={`${BTN_PRIMARY} !py-1.5 !px-3 text-[10px]`}
                                            >
                                                {aplicandoCliente
                                                    ? <Loader2 className="w-3.5 h-3.5 animate-spin shrink-0" />
                                                    : <RefreshCw className="w-3.5 h-3.5 shrink-0" />}
                                                Actualizar datos del cliente
                                            </button>
                                        )}
                                    </div>
                                    {mensajeSync && (
                                        <p className="text-xs font-bold text-emerald-600 dark:text-emerald-400 p-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 mb-3">{mensajeSync}</p>
                                    )}
                                    {errorSync && (
                                        <p className="text-xs font-bold text-red-600 dark:text-red-400 p-3 rounded-2xl border border-red-500/20 bg-red-500/5 mb-3">{errorSync}</p>
                                    )}
                                    {cargandoDatos && (
                                        <div className="flex items-center gap-2 p-4 text-xs font-bold theme-text-muted italic">
                                            <Loader2 className="w-4 h-4 animate-spin" /> Extrayendo datos…
                                        </div>
                                    )}
                                    {!cargandoDatos && errorDatos && (
                                        <p className="text-xs font-bold text-red-600 dark:text-red-400 p-4 rounded-2xl border border-red-500/20 bg-red-500/5">{errorDatos}</p>
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
                                                                <button type="button" onClick={() => copiarCampo(clave, datosFiscales[clave])} className={`${BTN_SECONDARY} !py-1.5 !px-2`}>
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
                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Excel fiscal</p>
                                        <a
                                            href={urlArchivoFactura(factura.id, 'fiscal')}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className={`${BTN_SECONDARY} inline-flex items-center gap-2`}
                                        >
                                            <FileSpreadsheet className="w-4 h-4 shrink-0" style={{ color: ACCENT }} /> Descargar / ver Excel
                                        </a>
                                    </section>
                                )}

                                <section>
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Vouchers ({vouchers.length})</p>
                                    {vouchers.length === 0 ? (
                                        <p className="text-xs italic theme-text-muted">Sin vouchers adjuntos.</p>
                                    ) : (
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-[10px] font-bold truncate">{voucherActual?.nombre_original || 'Voucher'}</span>
                                                <div className="flex items-center gap-2 shrink-0">
                                                    <button type="button" disabled={voucherIndex <= 0} onClick={() => setVoucherIndex(i => i - 1)} className={`${BTN_SECONDARY} !p-2 disabled:opacity-30`}><ChevronLeft className="w-4 h-4" /></button>
                                                    <span className="text-[9px] font-black">{voucherIndex + 1}/{vouchers.length}</span>
                                                    <button type="button" disabled={voucherIndex >= vouchers.length - 1} onClick={() => setVoucherIndex(i => i + 1)} className={`${BTN_SECONDARY} !p-2 disabled:opacity-30`}><ChevronRight className="w-4 h-4" /></button>
                                                    <a href={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} target="_blank" rel="noopener noreferrer" className={`${BTN_SECONDARY} !p-2`}><Download className="w-4 h-4" /></a>
                                                </div>
                                            </div>
                                            {esImagenVoucher(voucherActual) ? (
                                                <div className="rounded-2xl border theme-border overflow-hidden bg-white">
                                                    <img src={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} alt="Voucher" className="w-full max-h-[360px] object-contain" />
                                                </div>
                                            ) : esPdfVoucher(voucherActual) ? (
                                                <iframe title="Voucher PDF" src={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} className="w-full h-[360px] rounded-2xl border theme-border bg-white" />
                                            ) : (
                                                <a href={urlArchivoFactura(factura.id, 'voucher', voucherIndex)} target="_blank" rel="noopener noreferrer" className={`${BTN_PRIMARY} inline-flex items-center gap-2`}>
                                                    Abrir archivo adjunto
                                                </a>
                                            )}
                                        </div>
                                    )}
                                </section>

                                {(factura.tiene_pdf_emitido || factura.tiene_xml) && (
                                    <section>
                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Factura emitida</p>
                                        <div className="flex flex-wrap gap-2">
                                            {factura.tiene_pdf_emitido && (
                                                <a
                                                    href={urlArchivoFactura(factura.id, 'pdf', 0, { descargar: true })}
                                                    download={nombreArchivoFacturaPdf(factura)}
                                                    className={`${BTN_SECONDARY} inline-flex items-center gap-2`}
                                                >
                                                    <Download className="w-4 h-4 shrink-0" /> Descargar PDF
                                                </a>
                                            )}
                                            {factura.tiene_xml && (
                                                <a href={urlArchivoFactura(factura.id, 'xml')} className={`${BTN_SECONDARY} inline-flex items-center gap-2`}>
                                                    <Download className="w-4 h-4 shrink-0" /> XML CFDI
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

                <div className="gelia-modal-footer p-5 md:p-6 border-t theme-border flex justify-end shrink-0">
                    <button type="button" onClick={onClose} className={BTN_SECONDARY}>Cerrar</button>
                </div>
            </div>
        </div>,
        document.body
    );
}
