import React, { useEffect, useRef, useState } from 'react';
import { Mic, Square, Trash2 } from 'lucide-react';
import useIconoHover from '@/hooks/useIconoHover';

export default function GrabadorAudio({ onGrabado, onCancelar, onError, disabled = false }) {
    const [grabando, setGrabando] = useState(false);
    const [segundos, setSegundos] = useState(0);
    const mediaRecorderRef = useRef(null);
    const chunksRef = useRef([]);
    const timerRef = useRef(null);
    const streamRef = useRef(null);
    const mic = useIconoHover(Mic, { size: 20, className: 'w-5 h-5' });
    const detenerIcono = useIconoHover(Square, { size: 16, className: 'w-4 h-4' });
    const borrarIcono = useIconoHover(Trash2, { size: 16, className: 'w-4 h-4' });

    useEffect(() => () => {
        clearInterval(timerRef.current);
        streamRef.current?.getTracks().forEach((t) => t.stop());
    }, []);

    const iniciar = async () => {
        try {
            if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                onError?.('El navegador necesita acceso seguro (HTTPS) y permiso de micrófono para grabar audio.');
                return;
            }
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            streamRef.current = stream;
            chunksRef.current = [];

            const preferido = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'].find((tipo) => MediaRecorder.isTypeSupported(tipo));
            const recorder = new MediaRecorder(stream, preferido ? { mimeType: preferido } : undefined);
            const mimeType = recorder.mimeType || preferido || 'audio/webm';
            mediaRecorderRef.current = recorder;

            recorder.ondataavailable = (e) => {
                if (e.data.size > 0) chunksRef.current.push(e.data);
            };

            recorder.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                const blob = new Blob(chunksRef.current, { type: mimeType });
                const ext = mimeType.includes('mp4') ? 'm4a' : mimeType.includes('webm') ? 'webm' : 'ogg';
                const file = new File([blob], `audio_${Date.now()}.${ext}`, { type: mimeType });
                onGrabado?.(file);
                setSegundos(0);
            };

            recorder.start(250);
            setGrabando(true);
            timerRef.current = setInterval(() => setSegundos((s) => s + 1), 1000);
        } catch {
            streamRef.current?.getTracks().forEach((t) => t.stop());
            onError?.('No se pudo acceder al micrófono. Revisa el permiso del navegador.');
        }
    };

    const detener = () => {
        clearInterval(timerRef.current);
        mediaRecorderRef.current?.stop();
        setGrabando(false);
    };

    const cancelar = () => {
        clearInterval(timerRef.current);
        if (mediaRecorderRef.current?.state === 'recording') {
            mediaRecorderRef.current.onstop = null;
            mediaRecorderRef.current.stop();
        }
        streamRef.current?.getTracks().forEach((t) => t.stop());
        setGrabando(false);
        setSegundos(0);
        onCancelar?.();
    };

    if (!grabando) {
        return (
            <button
                type="button"
                onClick={iniciar}
                disabled={disabled}
                className="gelia-toque gelia-mensajeria-icon-button inline-flex items-center justify-center rounded-full theme-text-main disabled:opacity-40"
                title="Grabar audio"
                aria-label="Grabar audio"
                {...mic.triggerProps}
            >
                {mic.nodo}
            </button>
        );
    }

    return (
        <div className="flex items-center gap-2 px-3 py-2 rounded-2xl theme-element border theme-border">
            <span className="gelia-grabacion-punto" />
            <span className="text-xs font-mono">{segundos}s</span>
            <button type="button" onClick={detener} className="gelia-toque gelia-mensajeria-icon-button inline-flex items-center justify-center rounded-full bg-[var(--color-primario)] text-white" aria-label="Detener grabación" {...detenerIcono.triggerProps}>
                {detenerIcono.nodo}
            </button>
            <button type="button" onClick={cancelar} className="gelia-toque gelia-mensajeria-icon-button inline-flex items-center justify-center rounded-full opacity-60 hover:opacity-100" aria-label="Cancelar grabación" {...borrarIcono.triggerProps}>
                {borrarIcono.nodo}
            </button>
        </div>
    );
}
