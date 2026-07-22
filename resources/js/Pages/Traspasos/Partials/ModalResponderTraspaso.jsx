import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, CheckCircle2, AlertOctagon, Upload, Send } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { compressImageToWebp } from '../../../utils/compressImage';

export default function ModalResponderTraspaso({ onClose, traspaso, estadoId, modo = 'responder', onExito }) {
    const esAprobacion = modo === 'responder';
    const esError = modo === 'reportar';
    const [preview, setPreview] = useState(null);

    const { data, setData, post, processing, errors } = useForm({
        catalogo_estado_solicitud_id: estadoId,
        motivo: '',
        folio_traspaso: '',
        evidencia_respuesta: null,
        _method: 'put',
    });

    const aplicarEvidencia = async (file) => {
        if (!file) return;
        if (file.type.startsWith('image/')) {
            try {
                const compressed = await compressImageToWebp(file, { maxBytes: 5120 * 1024 });
                setData('evidencia_respuesta', compressed);
                setPreview(URL.createObjectURL(compressed));
            } catch {
                setData('evidencia_respuesta', file);
                setPreview(URL.createObjectURL(file));
            }
        } else {
            setData('evidencia_respuesta', file);
            setPreview(null);
        }
    };

    const handlePaste = async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                await aplicarEvidencia(item.getAsFile());
                break;
            }
        }
    };

    const cerrar = () => {
        if (preview) URL.revokeObjectURL(preview);
        onClose();
    };

    const enviar = (e) => {
        e.preventDefault();
        post(route('traspasos.actualizar_estado', traspaso.id), {
            forceFormData: true,
            onSuccess: () => {
                if (preview) URL.revokeObjectURL(preview);
                onExito?.();
                cerrar();
            },
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={cerrar}>
            <div
                onPaste={handlePaste}
                className={`${THEME_MODAL_SHELL} max-w-xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        {esError
                            ? <AlertOctagon className="w-7 h-7 text-red-500 shrink-0" />
                            : <CheckCircle2 className="w-7 h-7 shrink-0" style={{ color: 'var(--color-primario)' }} />}
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic theme-text-main uppercase m-0 leading-tight">
                                {esError ? 'Reportar Error_' : 'Responder Traspaso_'}
                            </h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">{traspaso.folio}</p>
                        </div>
                    </div>
                    <button type="button" onClick={cerrar} className="p-2 theme-text-muted rounded-full hover:bg-black/5 dark:hover:bg-white/5"><X className="w-5 h-5" /></button>
                </div>

                <form onSubmit={enviar} className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-5">
                    {esAprobacion && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Folio del traspaso</label>
                            <input
                                value={data.folio_traspaso}
                                onChange={(e) => setData('folio_traspaso', e.target.value)}
                                className="theme-input w-full px-4 py-3 font-bold font-mono"
                                placeholder="Folio generado"
                                required
                            />
                            {errors.folio_traspaso && <p className="text-xs text-red-500">{errors.folio_traspaso}</p>}
                        </div>
                    )}

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                            {esError ? 'Motivo (obligatorio)' : 'Observaciones'}
                        </label>
                        <textarea
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            className="theme-input w-full px-4 py-3 font-bold min-h-[90px]"
                            required={esError}
                        />
                        {errors.motivo && <p className="text-xs text-red-500">{errors.motivo}</p>}
                    </div>

                    {esAprobacion && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                Captura del traspaso (Ctrl+V o archivo)
                            </label>
                            <label className="flex flex-col items-center justify-center gap-2 p-6 rounded-xl border-2 border-dashed theme-border cursor-pointer hover:border-[var(--color-primario)] transition-colors">
                                <Upload className="w-6 h-6 theme-text-muted" />
                                <span className="text-[10px] font-black uppercase theme-text-muted">Pegar imagen o seleccionar</span>
                                <input type="file" accept="image/*,.pdf" className="hidden" onChange={(e) => aplicarEvidencia(e.target.files?.[0])} />
                            </label>
                            {preview && (
                                <img src={preview} alt="Captura" className="max-h-48 rounded-xl border theme-border object-contain mx-auto" />
                            )}
                            {data.evidencia_respuesta && !preview && (
                                <p className="text-xs font-bold theme-text-main m-0">{data.evidencia_respuesta.name}</p>
                            )}
                            {errors.evidencia_respuesta && <p className="text-xs text-red-500">{errors.evidencia_respuesta}</p>}
                        </div>
                    )}

                    <button type="submit" disabled={processing} className="theme-btn-primary w-full !py-3">
                        <Send className="w-4 h-4" /> {processing ? 'Guardando…' : 'Confirmar'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
