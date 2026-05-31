import { useCallback, useEffect } from 'react';
import { actualizarPresenciaEnConversacion } from '@/utils/presenciaUsuario';

export default function usePresenciaContactos(setConversaciones, setConversacionActiva = null) {
    const aplicar = useCallback((presencia) => {
        const userId = presencia?.user_id;
        if (!userId) return;

        setConversaciones((prev) =>
            prev.map((c) => actualizarPresenciaEnConversacion(c, userId, presencia))
        );

        if (setConversacionActiva) {
            setConversacionActiva((prev) =>
                prev ? actualizarPresenciaEnConversacion(prev, userId, presencia) : prev
            );
        }
    }, [setConversaciones, setConversacionActiva]);

    useEffect(() => {
        const handler = (event) => {
            const presencia = event.detail;
            if (presencia) aplicar(presencia);
        };

        window.addEventListener('gelia-presencia-contacto', handler);
        return () => window.removeEventListener('gelia-presencia-contacto', handler);
    }, [aplicar]);

    return aplicar;
}
