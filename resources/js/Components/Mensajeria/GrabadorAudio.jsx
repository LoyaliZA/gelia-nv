import React, { useEffect, useRef, useState } from 'react';
import { Mic, Square, Trash2 } from 'lucide-react';

export default function GrabadorAudio({ onGrabado, onCancelar, disabled = false }) {
    const [grabando, setGrabando] = useState(false);
    const [segundos, setSegundos] = useState(0);
    const mediaRecorderRef = useRef(null);
    const chunksRef = useRef([]);
    const timerRef = useRef(null);
    const streamRef = useRef(null);

    useEffect(() => () => {
        clearInterval(timerRef.current);
        streamRef.current?.getTracks().forEach((t) => t.stop());
    }, []);

    const iniciar = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            streamRef.current = stream;
            chunksRef.current = [];

            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? 'audio/webm;codecs=opus'
                : 'audio/webm';

            const recorder = new MediaRecorder(stream, { mimeType });
            mediaRecorderRef.current = recorder;

            recorder.ondataavailable = (e) => {
                if (e.data.size > 0) chunksRef.current.push(e.data);
            };

            recorder.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                const blob = new Blob(chunksRef.current, { type: mimeType });
                const ext = mimeType.includes('webm') ? 'webm' : 'ogg';
                const file = new File([blob], `audio_${Date.now()}.${ext}`, { type: mimeType });
                onGrabado?.(file);
                setSegundos(0);
            };

            recorder.start(250);
            setGrabando(true);
            timerRef.current = setInterval(() => setSegundos((s) => s + 1), 1000);
        } catch {
            alert('No se pudo acceder al micrófono.');
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
                className="p-2 rounded-full theme-element hover:bg-[var(--color-primario)] hover:text-white transition-colors disabled:opacity-40"
                title="Grabar audio"
            >
                <Mic className="w-5 h-5" />
            </button>
        );
    }

    return (
        <div className="flex items-center gap-2 px-3 py-2 rounded-2xl theme-element border theme-border">
            <span className="w-2 h-2 rounded-full bg-red-500 animate-pulse" />
            <span className="text-xs font-mono">{segundos}s</span>
            <button type="button" onClick={detener} className="p-1.5 rounded-full bg-[var(--color-primario)] text-white">
                <Square className="w-4 h-4" />
            </button>
            <button type="button" onClick={cancelar} className="p-1.5 rounded-full opacity-60 hover:opacity-100">
                <Trash2 className="w-4 h-4" />
            </button>
        </div>
    );
}
