import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Edit2, Info, AlertOctagon, Upload, Send } from 'lucide-react';

export default function ModalRespuestaSolicitud({ onClose, solicitud, estadoId }) {
    const [previewEvidenciaRespuesta, setPreviewEvidenciaRespuesta] = useState(null);

    const { data, setData, post, processing, reset } = useForm({
        solicitud_id: solicitud?.id || '',
        catalogo_estado_solicitud_id: estadoId || '',
        motivo: '',
        evidencia_respuesta: null,
        _method: 'put',
    });

    const compressToWebp = (file) => {
        return new Promise((resolve) => {
            const reader = new FileReader(); reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image(); img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
                    const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
                    canvas.toBlob((blob) => {
                        const newFile = new File([blob], `captura_respuesta_${Date.now()}.webp`, { type: 'image/webp' });
                        resolve({ file: newFile, preview: canvas.toDataURL('image/webp', 0.8) });
                    }, 'image/webp', 0.8);
                };
            };
        });
    };

    const handlePasteRespuesta = async (e) => {
        const items = e.clipboardData?.items; if (!items) return;
        for (let item of items) {
            if (item.type.indexOf('image') !== -1) {
                const result = await compressToWebp(item.getAsFile());
                setData('evidencia_respuesta', result.file); setPreviewEvidenciaRespuesta(result.preview); break;
            }
        }
    };

    const handleFileUpload = async (e) => {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const result = await compressToWebp(file); setData('evidencia_respuesta', result.file); setPreviewEvidenciaRespuesta(result.preview);
        } else if (file) {
            setData('evidencia_respuesta', file); setPreviewEvidenciaRespuesta(null);
        }
    };

    const enviarRespuesta = (e) => {
        e.preventDefault();
        post(route('solicitudes.actualizar_estado', data.solicitud_id), {
            onSuccess: () => { reset(); setPreviewEvidenciaRespuesta(null); onClose(); },
            forceFormData: true // Garantiza el envío correcto del archivo
        });
    };

    const esReporteError = data.catalogo_estado_solicitud_id === 4;

    return createPortal(
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div onPaste={handlePasteRespuesta} className="w-full max-w-3xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 flex flex-col relative modal-pop" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none hover:scale-110 transition-transform"><X className="w-5 h-5" /></button>
                <div className="flex items-center gap-3 mb-8"><Edit2 className="w-8 h-8" style={{ color: 'var(--color-primario)' }} /><h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0">Actualizar Estado_</h2></div>

                <form onSubmit={enviarRespuesta} className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div className="space-y-8">
                        <div className="p-5 rounded-2xl border flex items-start gap-4 transition-colors" style={{ backgroundColor: esReporteError ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)', borderColor: esReporteError ? 'rgba(239, 68, 68, 0.3)' : 'rgba(52, 211, 153, 0.3)' }}>
                            {esReporteError ? <AlertOctagon className="w-6 h-6 text-red-500 mt-0.5" /> : <Info className="w-6 h-6 text-emerald-500 mt-0.5" />}
                            <div><p className="text-sm font-black uppercase tracking-widest" style={{ color: esReporteError ? '#ef4444' : '#10b981' }}>{esReporteError ? 'Reporte de Error' : 'Aprobación'}</p></div>
                        </div>
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Observaciones / Motivo</label>
                            <textarea required={esReporteError} rows="6" value={data.motivo} onChange={e => setData('motivo', e.target.value)} className="w-full p-5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 shadow-sm resize-none transition-all"></textarea>
                        </div>
                    </div>
                    <div className="space-y-2 flex flex-col">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Evidencia de Respuesta (Ctrl+V)_</label>
                        <label className="flex-1 flex flex-col items-center justify-center border-2 border-dashed theme-border rounded-2xl cursor-pointer theme-element relative overflow-hidden group">
                            <Upload className="w-10 h-10 mb-3 theme-text-muted z-10 group-hover:scale-110 transition-transform" />
                            {previewEvidenciaRespuesta && <img src={previewEvidenciaRespuesta} className="absolute inset-0 w-full h-full object-cover opacity-80" />}
                            <p className="text-[10px] font-bold theme-text-main uppercase z-10 bg-white/50 dark:bg-black/50 px-3 py-1.5 rounded-lg border theme-border backdrop-blur-sm shadow-sm">Adjuntar Captura o PDF</p>
                            {/* Alineación de extensión de archivo con el backend */}
                            <input type="file" className="hidden" accept="image/*,.pdf" onChange={handleFileUpload} />
                        </label>
                        <button type="submit" disabled={processing} className="w-full py-5 text-white rounded-2xl font-black uppercase tracking-widest text-[12px] hover:scale-105 transition-all mt-4 disabled:opacity-50 disabled:hover:scale-100 flex items-center justify-center gap-3 outline-none shadow-md" style={{ backgroundColor: esReporteError ? '#ef4444' : 'var(--color-primario)' }}>
                            <Send className="w-5 h-5" /> {processing ? 'Registrando...' : 'Confirmar Acción'}
                        </button>
                    </div>
                </form>
            </div>
        </div>, document.body
    );
}
