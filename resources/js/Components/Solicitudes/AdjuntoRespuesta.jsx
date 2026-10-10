import React, { useEffect, useId, useRef, useState } from 'react';
import { Camera, FileText, Loader2, Paperclip, Trash2 } from 'lucide-react';
import { compressImageToWebp, validateImageSource } from '@/utils/compressImage';

export function useAdjuntoRespuesta(file, onChange, { maxBytes = 5 * 1024 * 1024 } = {}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [preview, setPreview] = useState(null);
    const sequence = useRef(0);
    const cambiar = useRef(onChange);
    cambiar.current = onChange;
    useEffect(() => {
        if (!file?.type.startsWith('image/')) { setPreview(null); return undefined; }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);
    useEffect(() => () => { sequence.current += 1; }, []);
    const adjuntar = async (original) => {
        if (!original) return;
        const current = ++sequence.current;
        setError('');
        const imagen = original.type.startsWith('image/');
        const fallo = imagen ? validateImageSource(original)
            : original.type !== 'application/pdf' ? 'Usa una imagen JPG, PNG o WebP, o un PDF.'
                : original.size > maxBytes ? 'El PDF excede 5 MB. Selecciona un archivo más pequeño.' : null;
        if (fallo) { setBusy(false); setError(fallo); return; }
        setBusy(true);
        try {
            const result = imagen ? await compressImageToWebp(original, { maxBytes }) : original;
            if (current === sequence.current) cambiar.current(result);
        } catch {
            if (current === sequence.current) setError('No se pudo preparar la imagen. Prueba con otra foto o una captura JPG o PNG.');
        } finally {
            if (current === sequence.current) setBusy(false);
        }
    };
    const onPaste = (event) => {
        const item = [...(event.clipboardData?.items || [])].find((item) => item.type.startsWith('image/'));
        if (item) adjuntar(item.getAsFile());
    };
    const quitar = () => { sequence.current += 1; setBusy(false); setError(''); cambiar.current(null); };
    return { adjuntar, onPaste, quitar, busy, error, preview };
}

export default function AdjuntoRespuesta({ file, attachment, error, disabled = false, label = 'Evidencia de la respuesta', required = false }) {
    const id = useId();
    const mensaje = error || attachment.error;
    return (
        <section className="gelia-adjunto-respuesta space-y-3" aria-labelledby={`${id}-label`} aria-busy={attachment.busy}>
            <div className="flex justify-between items-center gap-2">
                <h3 id={`${id}-label`} className="m-0 text-sm font-medium theme-text-main">{label} {required ? '(obligatoria)' : '(opcional)'}</h3>
                {file && <button type="button" className="gelia-workflow-close theme-text-peligro" disabled={disabled} onClick={attachment.quitar} aria-label={`Quitar ${file.name}`}><Trash2 className="w-4 h-4" aria-hidden="true" /></button>}
            </div>
            {attachment.preview && <img src={attachment.preview} alt="Vista previa del archivo que se enviará" width="640" height="480" className="gelia-adjunto-preview" />}
            {file && <div className="flex items-center gap-2 min-w-0 text-sm theme-text-main"><FileText className="w-4 h-4 shrink-0" aria-hidden="true" /><span className="break-words min-w-0">{file.name} <span className="theme-text-muted">({new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(file.size / 1024)} KB)</span></span></div>}
            <div className="flex flex-wrap gap-2">
                <label className={`gelia-adjunto-control ${disabled || attachment.busy ? 'opacity-50 pointer-events-none' : ''}`}>
                    {attachment.busy ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Paperclip className="w-4 h-4" aria-hidden="true" />}
                    {attachment.busy ? 'Preparando…' : file ? 'Cambiar archivo' : 'Adjuntar archivo'}
                    <input aria-label="Adjuntar imagen o PDF" type="file" accept="image/jpeg,image/png,image/webp,image/gif,.pdf" disabled={disabled || attachment.busy} className="sr-only" onChange={(event) => { attachment.adjuntar(event.target.files?.[0]); event.target.value = ''; }} />
                </label>
                <label className={`gelia-adjunto-control ${disabled || attachment.busy ? 'opacity-50 pointer-events-none' : ''}`}>
                    <Camera className="w-4 h-4" aria-hidden="true" /> Tomar foto
                    <input aria-label="Tomar foto de evidencia" type="file" accept="image/*" capture="environment" disabled={disabled || attachment.busy} className="sr-only" onChange={(event) => { attachment.adjuntar(event.target.files?.[0]); event.target.value = ''; }} />
                </label>
            </div>
            <p className="text-xs theme-text-muted m-0">JPG, PNG, WebP o PDF · hasta 5 MB al enviar. También puedes pegar una captura con Ctrl+V o ⌘V.</p>
            {mensaje && <p id={`${id}-error`} role="alert" className="text-sm theme-text-peligro m-0">{mensaje}</p>}
        </section>
    );
}
