import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Edit2, Info, AlertOctagon, Upload, Send } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
} from '../../../utils/geliaTheme';

export default function ModalRespuestaOperativa({ onClose, onExito, solicitud, estadoId }) {
    const [previewEvidenciaRespuesta, setPreviewEvidenciaRespuesta] = useState(null);

    const { data, setData, post, processing, reset } = useForm({
        catalogo_estado_solicitud_id: estadoId || '',
        motivo: '',
        evidencia_respuesta: null,
        _method: 'put',
    });

    const compressToWebp = (file) => {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    canvas.toBlob((blob) => {
                        const newFile = new File([blob], `captura_respuesta_${Date.now()}.webp`, { type: 'image/webp' });
                        resolve({ file: newFile, preview: canvas.toDataURL('image/webp', 0.8) });
                    }, 'image/webp', 0.8);
                };
            };
        });
    };

    const handlePasteRespuesta = async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                const result = await compressToWebp(item.getAsFile());
                setData('evidencia_respuesta', result.file);
                setPreviewEvidenciaRespuesta(result.preview);
                break;
            }
        }
    };

    const handleFileUpload = async (e) => {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const result = await compressToWebp(file);
            setData('evidencia_respuesta', result.file);
            setPreviewEvidenciaRespuesta(result.preview);
        } else if (file) {
            setData('evidencia_respuesta', file);
            setPreviewEvidenciaRespuesta(null);
        }
    };

    const enviarRespuesta = (e) => {
        e.preventDefault();
        post(route('cancelaciones_cotizaciones.actualizar_estado', solicitud.id), {
            onSuccess: () => { reset(); setPreviewEvidenciaRespuesta(null); onExito?.(); onClose(); },
            forceFormData: true,
        });
    };

    const esReporteError = data.catalogo_estado_solicitud_id === 4;
    const esVerificacion = data.catalogo_estado_solicitud_id === 3;
    const tituloAccion = esReporteError ? 'Reporte de error' : (esVerificacion ? 'Verificación' : 'Aprobación');
    const avisoClass = esReporteError
        ? 'bg-red-500/10 border-red-500/30 text-red-600'
        : 'border-[color-mix(in_srgb,var(--color-primario)_35%,transparent)] bg-[color-mix(in_srgb,var(--color-primario)_12%,transparent)]';
    const avisoTextClass = esReporteError ? 'text-red-600' : '';
    const avisoTextStyle = esReporteError ? undefined : { color: 'var(--color-primario)' };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                onPaste={handlePasteRespuesta}
                className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <Edit2 className="w-7 h-7 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <h2 className="text-lg md:text-xl font-black italic uppercase theme-text-main m-0 leading-tight">
                                Captura de respuesta
                            </h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1.5 m-0">
                                FOL-{solicitud?.id} · {solicitud?.proceso?.nombre}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={enviarRespuesta} className="flex flex-col flex-1 min-h-0">
                    <div className="gelia-modal-body p-5 md:p-6 grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                        <div className="space-y-6">
                            <div className={`p-4 rounded-2xl border flex items-start gap-3 ${avisoClass}`}>
                                {esReporteError ? (
                                    <AlertOctagon className="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
                                ) : (
                                    <Info className="w-5 h-5 mt-0.5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                )}
                                <p className={`text-sm font-black uppercase tracking-widest m-0 ${avisoTextClass}`} style={avisoTextStyle}>
                                    {tituloAccion}
                                </p>
                            </div>
                            <div className="space-y-1.5">
                                <label className={THEME_LABEL}>Observaciones / motivo</label>
                                <textarea
                                    required={esReporteError}
                                    rows={6}
                                    value={data.motivo}
                                    onChange={(e) => setData('motivo', e.target.value)}
                                    placeholder={esVerificacion ? 'Opcional: comentario de verificación…' : 'Describe la resolución para la vendedora…'}
                                    className={THEME_TEXTAREA}
                                />
                            </div>
                        </div>
                        <div className="space-y-4 flex flex-col">
                            <div className="space-y-1.5 flex-1 flex flex-col min-h-0">
                                <label className={THEME_LABEL}>Evidencia (Ctrl+V)</label>
                                <label className="flex-1 flex flex-col items-center justify-center border-2 border-dashed theme-border rounded-2xl cursor-pointer theme-element relative overflow-hidden group min-h-[180px]">
                                    <Upload className="w-10 h-10 mb-3 theme-text-muted z-10 group-hover:scale-110 transition-transform" />
                                    {previewEvidenciaRespuesta && (
                                        <img src={previewEvidenciaRespuesta} alt="" className="absolute inset-0 w-full h-full object-cover opacity-80" />
                                    )}
                                    <p className="text-[10px] font-bold theme-text-main uppercase z-10 theme-element border theme-border px-3 py-1.5 rounded-lg m-0 text-center max-w-[90%]">
                                        {data.evidencia_respuesta?.name || 'Pegar captura con Ctrl+V o adjuntar imagen/PDF'}
                                    </p>
                                    <input type="file" className="hidden" accept="image/*,.pdf" onChange={handleFileUpload} />
                                </label>
                            </div>
                        </div>
                    </div>
                    <div className="gelia-modal-footer p-5 md:p-6 shrink-0">
                        <button
                            type="submit"
                            disabled={processing}
                            className={`${THEME_BTN_PRIMARY} w-full ${esReporteError ? '!bg-red-600' : ''}`}
                        >
                            <Send className="w-4 h-4 shrink-0" />
                            {processing ? 'Registrando…' : 'Confirmar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
