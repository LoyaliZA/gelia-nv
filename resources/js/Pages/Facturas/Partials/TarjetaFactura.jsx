import React from 'react';
import { Receipt, FileText, Paperclip, User, Calendar, Eye, CheckCircle2, XCircle, FileSpreadsheet, Trash2, Download } from 'lucide-react';
import { ESTADO_BADGE, urlArchivoFactura, nombreArchivoFacturaPdf } from './facturasStyles';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { puedePermiso } from '../../../utils/permisos';
import { nombreEstadoFactura } from './facturasFiltros';
import FeedbackResolucionFactura from './FeedbackResolucionFactura';

export default function TarjetaFactura({ factura, auth, onVerExpediente, onAprobar, onReportar, onVerificar, onEliminar }) {
    const puedeResponder = puedePermiso(auth, 'facturas.responder');
    const puedeReportar = puedePermiso(auth, 'facturas.reportar_error');
    const puedeVerificar = puedePermiso(auth, 'facturas.verificar');
    const puedeEliminar = puedePermiso(auth, 'facturas.eliminar');
    const estadoNombre = nombreEstadoFactura(factura) || '—';
    const esPendiente = estadoNombre === 'Pendiente';
    const esRespondida = estadoNombre === 'Respondida';
    const esVerificada = estadoNombre === 'Verificada';
    const puedeDescargarEmitidos = (esRespondida || esVerificada) && (factura.tiene_pdf_emitido || factura.tiene_xml);
    const estadoId = factura.catalogo_estado_solicitud_id ?? factura.estado?.id;
    const rfc = factura.datos_fiscales?.rfc || factura.cliente?.rfc || '—';

    return (
        <article
            className={`${geliaCardClass(
                'h-full flex flex-col min-w-0 p-5 md:p-6 border-l-4 transition-shadow hover:shadow-lg'
            )} border-l-[var(--color-primario)]`}
        >
            <div className="flex flex-wrap items-start justify-between gap-3 mb-4 min-w-0">
                <div className="min-w-0 flex-1">
                    <p className="text-[10px] font-mono font-black uppercase tracking-widest mb-1 m-0" style={{ color: 'var(--color-primario)' }}>
                        {factura.folio}
                    </p>
                    <h3 className="text-sm font-black theme-text-main m-0 leading-snug break-words">{factura.razon_social}</h3>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 m-0">RFC: {rfc}</p>
                </div>
                <span className={`px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border shrink-0 ${ESTADO_BADGE[estadoId] || 'theme-element theme-border theme-text-muted'}`}>
                    {estadoNombre}
                </span>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                {factura.tiene_archivo_fiscal && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase theme-element border theme-border">
                        <FileSpreadsheet className="w-3 h-3 shrink-0" style={{ color: 'var(--color-primario)' }} /> Excel
                    </span>
                )}
                {factura.tiene_voucher && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase theme-element border theme-border">
                        <Paperclip className="w-3 h-3 shrink-0" style={{ color: 'var(--color-primario)' }} /> Voucher
                    </span>
                )}
                {factura.tiene_pdf_emitido && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                        <FileText className="w-3 h-3 shrink-0" /> PDF
                    </span>
                )}
                {factura.tiene_xml && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase theme-element border theme-border">
                        XML
                    </span>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] font-bold theme-text-muted mb-4 min-w-0">
                <span className="inline-flex items-center gap-1 min-w-0">
                    <User className="w-3.5 h-3.5 shrink-0" />
                    <span className="truncate">{factura.vendedor?.name}</span>
                </span>
                <span className="inline-flex items-center gap-1 shrink-0">
                    <Calendar className="w-3.5 h-3.5 shrink-0" />
                    {new Date(factura.created_at).toLocaleDateString('es-MX')}
                </span>
            </div>

            <FeedbackResolucionFactura factura={factura} />

            <div className="flex flex-wrap gap-2 pt-4 mt-auto border-t theme-border">
                <button
                    type="button"
                    onClick={() => onVerExpediente(factura)}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase theme-element border theme-border outline-none hover:border-[var(--color-primario)] transition-colors"
                >
                    <Eye className="w-3.5 h-3.5 shrink-0" /> Expediente
                </button>
                {esPendiente && (
                    <>
                        {puedeResponder && (
                            <button
                                type="button"
                                onClick={() => onAprobar(factura)}
                                className="theme-btn-primary theme-btn-primary--compact !py-2 !px-3 text-[9px]"
                            >
                                <CheckCircle2 className="w-3.5 h-3.5 shrink-0" /> Emitir
                            </button>
                        )}
                        {puedeReportar && (
                            <button
                                type="button"
                                onClick={() => onReportar(factura)}
                                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-red-500/10 text-red-600 dark:text-red-300 border border-red-500/30 outline-none hover:bg-red-500/20 transition-colors"
                            >
                                <XCircle className="w-3.5 h-3.5 shrink-0" /> Error
                            </button>
                        )}
                    </>
                )}
                {esRespondida && puedeVerificar && (
                    <button
                        type="button"
                        onClick={() => onVerificar(factura)}
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 outline-none"
                    >
                        <Receipt className="w-3.5 h-3.5 shrink-0" /> Verificar
                    </button>
                )}
                {puedeDescargarEmitidos && factura.tiene_pdf_emitido && (
                    <a
                        href={urlArchivoFactura(factura.id, 'pdf', 0, { descargar: true })}
                        download={nombreArchivoFacturaPdf(factura)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase theme-element border theme-border outline-none hover:border-[var(--color-primario)] transition-colors"
                    >
                        <Download className="w-3.5 h-3.5 shrink-0" /> PDF
                    </a>
                )}
                {puedeDescargarEmitidos && factura.tiene_xml && (
                    <a
                        href={urlArchivoFactura(factura.id, 'xml')}
                        download
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase theme-element border theme-border outline-none hover:border-[var(--color-primario)] transition-colors"
                    >
                        <Download className="w-3.5 h-3.5 shrink-0" /> XML
                    </a>
                )}
                {puedeEliminar && (
                    <button
                        type="button"
                        onClick={() => onEliminar(factura)}
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/30 outline-none ml-auto hover:bg-red-500/20 transition-colors"
                    >
                        <Trash2 className="w-3.5 h-3.5 shrink-0" /> Eliminar
                    </button>
                )}
            </div>
        </article>
    );
}
