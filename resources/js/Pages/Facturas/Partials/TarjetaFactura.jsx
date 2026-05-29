import React from 'react';
import { Receipt, FileText, Paperclip, User, Calendar, Eye, CheckCircle2, XCircle, FileSpreadsheet } from 'lucide-react';
import { ACCENT, ESTADO_BADGE } from './facturasStyles';
import { geliaCardClass } from '../../../utils/geliaTheme';

export default function TarjetaFactura({ factura, auth, onVerExpediente, onAprobar, onReportar, onVerificar }) {
    const permisos = auth?.user?.permissions || [];
    const puedeResponder = permisos.includes('facturas.responder');
    const puedeVerificar = permisos.includes('facturas.verificar');
    const estadoId = factura.catalogo_estado_solicitud_id ?? factura.estado?.id;
    const estadoNombre = factura.estado?.nombre || '—';
    const rfc = factura.datos_fiscales?.rfc || factura.cliente?.rfc || '—';

    return (
        <article
            className={geliaCardClass('rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden')}
            style={{ borderLeftWidth: '4px', borderLeftColor: ACCENT }}
        >
            <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div className="min-w-0">
                    <p className="text-[10px] font-mono font-black uppercase tracking-widest mb-1" style={{ color: ACCENT }}>
                        {factura.folio}
                    </p>
                    <h3 className="text-sm font-black theme-text-main truncate m-0">{factura.razon_social}</h3>
                    <p className="text-[10px] font-bold theme-text-muted mt-0.5">RFC: {rfc}</p>
                </div>
                <span className={`px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border ${ESTADO_BADGE[estadoId] || 'theme-element theme-border theme-text-muted'}`}>
                    {estadoNombre}
                </span>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                {factura.tiene_archivo_fiscal && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase theme-element border theme-border">
                        <FileSpreadsheet className="w-3 h-3" style={{ color: ACCENT }} /> Excel
                    </span>
                )}
                {factura.tiene_voucher && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase theme-element border theme-border">
                        <Paperclip className="w-3 h-3" style={{ color: ACCENT }} /> Voucher
                    </span>
                )}
                {factura.tiene_pdf_emitido && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-700 border border-emerald-500/20">
                        <FileText className="w-3 h-3" /> PDF
                    </span>
                )}
                {factura.tiene_xml && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase theme-element border theme-border">
                        XML
                    </span>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-4 text-[10px] font-bold theme-text-muted mb-4">
                <span className="inline-flex items-center gap-1"><User className="w-3.5 h-3.5" /> {factura.vendedor?.name}</span>
                <span className="inline-flex items-center gap-1"><Calendar className="w-3.5 h-3.5" /> {new Date(factura.created_at).toLocaleDateString('es-MX')}</span>
            </div>

            <div className="flex flex-wrap gap-2 pt-3 border-t theme-border">
                <button type="button" onClick={() => onVerExpediente(factura)} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase theme-element border theme-border outline-none hover:border-[var(--color-primario)]">
                    <Eye className="w-3.5 h-3.5" /> Expediente
                </button>
                {estadoId === 1 && puedeResponder && (
                    <>
                        <button type="button" onClick={() => onAprobar(factura)} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase text-white outline-none" style={{ backgroundColor: ACCENT }}>
                            <CheckCircle2 className="w-3.5 h-3.5" /> Emitir
                        </button>
                        <button type="button" onClick={() => onReportar(factura)} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-red-500/10 text-red-600 border border-red-500/30 outline-none">
                            <XCircle className="w-3.5 h-3.5" /> Error
                        </button>
                    </>
                )}
                {estadoId === 2 && puedeVerificar && (
                    <button type="button" onClick={() => onVerificar(factura)} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-700 border border-emerald-500/30 outline-none">
                        <Receipt className="w-3.5 h-3.5" /> Verificar
                    </button>
                )}
            </div>
        </article>
    );
}
