import { useCallback, useRef, useState } from 'react';
import {
    detectarTipoArchivo,
    esArrastreArchivo,
    primerArchivoDeDrop,
} from '@/utils/mensajeriaArchivoLocal';

export default function usePrepararAdjuntoDrop({ onEnviarAdjunto }) {
    const [adjuntoPendiente, setAdjuntoPendiente] = useState(null);
    const [dragActivo, setDragActivo] = useState(false);
    const dragDepth = useRef(0);

    const cerrarPendiente = useCallback(() => {
        setAdjuntoPendiente((prev) => {
            if (prev?.previewUrl) URL.revokeObjectURL(prev.previewUrl);
            return null;
        });
    }, []);

    const prepararAdjunto = useCallback((file) => {
        if (!file) return;

        const tipo = detectarTipoArchivo(file);

        if (tipo === 'audio') {
            onEnviarAdjunto?.(file, 'audio', null);
            return;
        }

        setAdjuntoPendiente((prev) => {
            if (prev?.previewUrl) URL.revokeObjectURL(prev.previewUrl);
            return {
                file,
                tipo,
                previewUrl: URL.createObjectURL(file),
            };
        });
    }, [onEnviarAdjunto]);

    const confirmarEnvio = useCallback(async (fileRenombrado, tipo, comentario) => {
        await onEnviarAdjunto?.(fileRenombrado, tipo, comentario || null);
        cerrarPendiente();
    }, [onEnviarAdjunto, cerrarPendiente]);

    const onDragEnter = useCallback((e) => {
        if (!esArrastreArchivo(e.dataTransfer)) return;
        e.preventDefault();
        dragDepth.current += 1;
        setDragActivo(true);
    }, []);

    const onDragLeave = useCallback((e) => {
        e.preventDefault();
        dragDepth.current = Math.max(0, dragDepth.current - 1);
        if (dragDepth.current === 0) setDragActivo(false);
    }, []);

    const onDragOver = useCallback((e) => {
        if (!esArrastreArchivo(e.dataTransfer)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    }, []);

    const onDrop = useCallback((e) => {
        if (!esArrastreArchivo(e.dataTransfer)) return;
        e.preventDefault();
        dragDepth.current = 0;
        setDragActivo(false);

        const file = primerArchivoDeDrop(e.dataTransfer);
        if (file) prepararAdjunto(file);
    }, [prepararAdjunto]);

    const zoneProps = {
        onDragEnter,
        onDragLeave,
        onDragOver,
        onDrop,
    };

    return {
        dragActivo,
        zoneProps,
        adjuntoPendiente,
        prepararAdjunto,
        cerrarPendiente,
        confirmarEnvio,
    };
}
