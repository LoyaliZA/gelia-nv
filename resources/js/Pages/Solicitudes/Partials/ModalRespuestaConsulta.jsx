import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, MessageSquare, CheckCircle2, XCircle, Upload, Send } from 'lucide-react';

export default function ModalRespuestaConsulta({ onClose, onExito, solicitud, consulta }) {
    const [previewEvidencia, setPreviewEvidencia] = useState(null);

    const { data, setData, post, processing } = useForm({
        respuesta_positiva: true,
        comentario_encargada: '',
        evidencia_respuesta: null,
        _method: 'put',
    });

    const temas = [
        consulta?.consulta_tag ? 'TAG' : null,
        consulta?.consulta_lista ? 'Lista' : null,
    ].filter(Boolean);

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
                        const newFile = new File([blob], `captura_consulta_${Date.now()}.webp`, { type: 'image/webp' });
                        resolve({ file: newFile, preview: canvas.toDataURL('image/webp', 0.8) });
                    }, 'image/webp', 0.8);
                };
            };
        });
    };

    const aplicarImagen = async (file) => {
        if (!file) return;
        if (file.type.startsWith('image/')) {
            const result = await compressToWebp(file);
            setData('evidencia_respuesta', result.file);
            setPreviewEvidencia(result.preview);
        } else {
            setData('evidencia_respuesta', file);
            setPreviewEvidencia(null);
        }
    };

    const handlePaste = async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                await aplicarImagen(item.getAsFile());
                break;
            }
        }
    };

    const handleFile = async (e) => {
        await aplicarImagen(e.target.files?.[0]);
    };

    const enviar = (e) => {
        e.preventDefault();
        post(route('solicitudes.consultas.responder', [solicitud.id, consulta.id]), {
            forceFormData: true,
            onSuccess: () => { onExito?.(); onClose(); },
        });
    };

    return createPortal(
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div
                onPaste={handlePaste}
                className="w-full max-w-lg theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-8 md:p-10 relative modal-pop"
                onClick={e => e.stopPropagation()}
            >
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none">
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 mb-6">
                    <MessageSquare className="w-8 h-8 text-emerald-500" />
                    <div>
                        <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0">Responder Consulta_</h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1">FOL-{solicitud?.id} · {temas.join(' + ')}</p>
                    </div>
                </div>

                {consulta?.comentario_vendedor && (
                    <div className="mb-5 p-4 rounded-2xl bg-black/5 dark:bg-white/5 border theme-border">
                        <p className="text-[10px] font-black uppercase theme-text-muted mb-1">Pregunta de la vendedora</p>
                        <p className="text-sm font-bold theme-text-main">{consulta.comentario_vendedor}</p>
                    </div>
                )}

                <form onSubmit={enviar} className="space-y-5">
                    <div className="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            onClick={() => setData('respuesta_positiva', true)}
                            className={`p-4 rounded-2xl border flex flex-col items-center gap-2 transition-all ${data.respuesta_positiva ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600' : 'theme-border theme-element theme-text-muted'}`}
                        >
                            <CheckCircle2 className="w-6 h-6" />
                            <span className="text-[10px] font-black uppercase">Confirmar</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setData('respuesta_positiva', false)}
                            className={`p-4 rounded-2xl border flex flex-col items-center gap-2 transition-all ${!data.respuesta_positiva ? 'border-red-500 bg-red-500/10 text-red-600' : 'theme-border theme-element theme-text-muted'}`}
                        >
                            <XCircle className="w-6 h-6" />
                            <span className="text-[10px] font-black uppercase">Rechazar</span>
                        </button>
                    </div>

                    <div>
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2 block">Comentario (opcional)</label>
                        <textarea
                            value={data.comentario_encargada}
                            onChange={e => setData('comentario_encargada', e.target.value)}
                            rows={3}
                            className="w-full theme-element border theme-border rounded-2xl p-4 text-sm font-bold theme-text-main outline-none focus:border-[var(--color-primario)] resize-none"
                            placeholder="Detalle de la respuesta..."
                        />
                    </div>

                    <div>
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2 block">Evidencia (Ctrl+V)_</label>
                        <label className="flex flex-col items-center justify-center gap-3 p-6 rounded-2xl border-2 border-dashed theme-border theme-element cursor-pointer hover:border-[var(--color-primario)] transition-colors relative overflow-hidden min-h-[120px]">
                            <Upload className="w-8 h-8 theme-text-muted" />
                            <span className="text-xs font-bold theme-text-muted text-center">
                                {data.evidencia_respuesta?.name || 'Pegar captura con Ctrl+V o adjuntar imagen/PDF'}
                            </span>
                            {previewEvidencia && (
                                <img src={previewEvidencia} alt="Vista previa" className="absolute inset-0 w-full h-full object-cover opacity-80" />
                            )}
                            <input type="file" accept="image/*,.pdf" onChange={handleFile} className="hidden" />
                        </label>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] flex items-center justify-center gap-2 transition-transform hover:scale-[1.02] disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Send className="w-4 h-4" /> Enviar Respuesta
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
