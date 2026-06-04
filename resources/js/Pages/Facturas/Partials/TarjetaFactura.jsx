import React from 'react';
import { Receipt, FileText, Paperclip, User, Calendar, Eye, CheckCircle2, XCircle, FileSpreadsheet, Trash2 } from 'lucide-react';
import { ESTADO_BADGE } from './facturasStyles';
import { geliaCardClass } from '../../../utils/geliaTheme';

export default function TarjetaFactura({ factura, auth, onVerExpediente, onAprobar, onReportar, onVerificar, onEliminar }) {
    const permisos = auth?.user?.permissions || [];
    const puedeResponder = permisos.includes('facturas.responder');
    const puedeVerificar = permisos.includes('facturas.verificar');
    const puedeEliminar = permisos.includes('facturas.eliminar');
    const estadoId = factura.catalogo_estado_solicitud_id ?? factura.estado?.id;
    const estadoNombre = factura.estado?.nombre || '—';
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

            <div className="flex flex-wrap gap-2 pt-4 mt-auto border-t theme-border">
                <button
                    type="button"
                    onClick={() => onVerExpediente(factura)}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase theme-element border theme-border outline-none hover:border-[var(--color-primario)] transition-colors"
                >
                    <Eye className="w-3.5 h-3.5 shrink-0" /> Expediente
                </button>
                {estadoId === 1 && puedeResponder && (
                    <>
                        <button
                            type="button"
                            onClick={() => onAprobar(factura)}
                            className="theme-btn-primary theme-btn-primary--compact !py-2 !px-3 text-[9px]"
                        >
                            <CheckCircle2 className="w-3.5 h-3.5 shrink-0" /> Emitir
                        </button>
                        <button
                            type="button"
                            onClick={() => onReportar(factura)}
                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-red-500/10 text-red-600 dark:text-red-300 border border-red-500/30 outline-none"
                        >
                            <XCircle className="w-3.5 h-3.5 shrink-0" /> Error
                        </button>
                    </>
                )}
                {estadoId === 2 && puedeVerificar && (
                    <button
                        type="button"
                        onClick={() => onVerificar(factura)}
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 outline-none"
                    >
                        <Receipt className="w-3.5 h-3.5 shrink-0" /> Verificar
                    </button>
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
