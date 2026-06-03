import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, CheckCircle2, AlertOctagon, Upload, Send, FileText } from 'lucide-react';
import { FACTURA_ACCENT, BTN_PRIMARY } from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

export default function ModalResponderFactura({ onClose, factura, estadoId }) {
    const esAprobacion = estadoId === 2;
    const esError = estadoId === 4;

    const { data, setData, post, processing } = useForm({
        catalogo_estado_solicitud_id: estadoId,
        motivo: '',
        factura_pdf: null,
        factura_xml: null,
        evidencia_error: null,
        _method: 'put',
    });

    const enviar = (e) => {
        e.preventDefault();
        post(route('facturas.actualizar_estado', factura.id), {
            forceFormData: true,
            onSuccess: () => onClose(),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={e => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        {esError ? <AlertOctagon className="w-7 h-7 text-red-500 shrink-0" /> : <CheckCircle2 className="w-7 h-7 shrink-0" style={{ color: FACTURA_ACCENT }} />}
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic theme-text-main uppercase m-0 leading-tight">
                                {esError ? 'Reportar Error_' : 'Emitir Factura_'}
                            </h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">{factura.folio}</p>
                        </div>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"><X className="w-5 h-5" /></button>
                </div>

                <form onSubmit={enviar} className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div className="space-y-6">
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                Observaciones {esError ? '(obligatorio)' : ''}
                            </label>
                            <textarea
                                required={esError}
                                rows={5}
                                value={data.motivo}
                                onChange={e => setData('motivo', e.target.value)}
                                className="w-full p-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none resize-none"
                            />
                        </div>
                    </div>

                    <div className="space-y-4">
                        {esAprobacion && (
                            <>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">PDF de factura emitida *</label>
                                    <label className="flex flex-col items-center justify-center border-2 border-dashed theme-border rounded-2xl p-6 cursor-pointer hover:border-[var(--color-primario)] transition-colors">
                                        <FileText className="w-8 h-8 mb-2" style={{ color: 'var(--color-primario)' }} />
                                        <span className="text-[10px] font-bold theme-text-main uppercase text-center">
                                            {data.factura_pdf ? data.factura_pdf.name : 'Seleccionar PDF'}
                                        </span>
                                        <input type="file" className="hidden" accept=".pdf,application/pdf" onChange={e => setData('factura_pdf', e.target.files[0] || null)} />
                                    </label>
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">XML CFDI (opcional)</label>
                                    <label className="flex flex-col items-center justify-center border-2 border-dashed theme-border rounded-2xl p-4 cursor-pointer hover:border-[var(--color-primario)] transition-colors">
                                        <Upload className="w-6 h-6 theme-text-muted mb-1" />
                                        <span className="text-[10px] font-bold theme-text-muted uppercase">
                                            {data.factura_xml ? data.factura_xml.name : 'Adjuntar XML'}
                                        </span>
                                        <input type="file" className="hidden" accept=".xml,application/xml,text/xml" onChange={e => setData('factura_xml', e.target.files[0] || null)} />
                                    </label>
                                </div>
                            </>
                        )}

                        {esError && (
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Evidencia (opcional)</label>
                                <label className="flex flex-col items-center justify-center border-2 border-dashed theme-border rounded-2xl p-6 cursor-pointer">
                                    <Upload className="w-8 h-8 theme-text-muted mb-2" />
                                    <span className="text-[10px] font-bold theme-text-muted uppercase">Captura o PDF</span>
                                    <input type="file" className="hidden" accept="image/*,.pdf" onChange={e => setData('evidencia_error', e.target.files[0] || null)} />
                                </label>
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            className={`${BTN_PRIMARY} w-full !py-4 disabled:opacity-50 ${esError ? '!bg-red-600 hover:!bg-red-700 dark:!bg-red-600' : ''}`}
                        >
                            <Send className="w-4 h-4 inline mr-2" />
                            {processing ? 'Procesando…' : 'Confirmar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
