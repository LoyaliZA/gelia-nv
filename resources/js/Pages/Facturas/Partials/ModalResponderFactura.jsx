import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, CheckCircle2, AlertOctagon, Upload, Send, FileText } from 'lucide-react';
import { FACTURA_ACCENT } from './facturasStyles';

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
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className="w-full max-w-3xl theme-surface border theme-border rounded-[2.5rem] p-10 shadow-2xl relative" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main rounded-2xl outline-none"><X className="w-5 h-5" /></button>

                <div className="flex items-center gap-3 mb-8">
                    {esError ? <AlertOctagon className="w-8 h-8 text-red-500" /> : <CheckCircle2 className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />}
                    <div>
                        <h2 className="text-2xl font-black italic theme-text-main uppercase m-0">
                            {esError ? 'Reportar Error_' : 'Emitir Factura_'}
                        </h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1">{factura.folio}</p>
                    </div>
                </div>

                <form onSubmit={enviar} className="grid grid-cols-1 md:grid-cols-2 gap-8">
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
                            className="w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-xs disabled:opacity-50 outline-none shadow-md"
                            style={{ backgroundColor: esError ? '#ef4444' : FACTURA_ACCENT }}
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
