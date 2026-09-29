import { useEffect } from 'react';

const SELECTOR_FOCO = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

export default function useModalPublicidad(abierto, onClose, contenedorRef) {
    useEffect(() => {
        if (!abierto) return undefined;
        const previo = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const nodo = contenedorRef.current;
        const focoPrevio = document.activeElement;
        const primero = nodo?.querySelector(SELECTOR_FOCO);
        primero?.focus();

        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
                return;
            }
            if (event.key !== 'Tab' || !nodo) return;
            const focos = [...nodo.querySelectorAll(SELECTOR_FOCO)].filter((el) => !el.disabled && el.getAttribute('aria-hidden') !== 'true');
            if (!focos.length) return;
            const inicio = focos[0];
            const fin = focos[focos.length - 1];
            if (event.shiftKey && document.activeElement === inicio) {
                event.preventDefault();
                fin.focus();
            } else if (!event.shiftKey && document.activeElement === fin) {
                event.preventDefault();
                inicio.focus();
            }
        };

        document.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = previo;
            document.removeEventListener('keydown', onKey);
            if (focoPrevio instanceof HTMLElement) focoPrevio.focus();
        };
    }, [abierto, onClose, contenedorRef]);
}
