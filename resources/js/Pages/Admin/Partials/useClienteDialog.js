import { useEffect, useRef } from 'react';

/** Mantiene el foco y el desplazamiento dentro de los diálogos de clientes. */
export default function useClienteDialog(onClose) {
    const dialogRef = useRef(null);
    const closeRef = useRef(onClose);

    useEffect(() => {
        closeRef.current = onClose;
    }, [onClose]);

    useEffect(() => {
        const previousFocus = document.activeElement;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialogRef.current?.focus({ preventScroll: true });

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeRef.current?.();
            }
            if (event.key !== 'Tab') return;
            const focusable = [...(dialogRef.current?.querySelectorAll(
                'button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]',
            ) || [])].filter((element) => element.getClientRects().length > 0);
            const first = focusable[0];
            const last = focusable.at(-1);
            if (!first) {
                event.preventDefault();
                return;
            }
            if (event.shiftKey && (document.activeElement === first || document.activeElement === dialogRef.current)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === dialogRef.current)) {
                event.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleKeyDown);
            if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
        };
    }, []);

    return dialogRef;
}
