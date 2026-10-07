import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

/**
 * Modal de escalonamiento montado en document.body, encima de AppLayout,
 * para que el overlay aplique el desenfoque del tema.
 */
export default function EscalonamientoModal({
    abierto,
    onClose,
    children,
    maxWidth = 'max-w-lg',
    labelledBy,
}) {
    useEffect(() => {
        if (!abierto) return undefined;
        const previo = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previo;
        };
    }, [abierto]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onKey = (event) => {
            if (event.key === 'Escape') onClose?.();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [abierto, onClose]);

    if (!abierto || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} !items-center !justify-center overflow-y-auto p-3 sm:p-4`}
            data-gelia-modal="1"
            role="dialog"
            aria-modal="true"
            aria-labelledby={labelledBy || undefined}
            onClick={onClose}
        >
            <div
                className={`${THEME_MODAL_SHELL} modal-pop ${maxWidth} w-full my-auto min-w-0`}
                onClick={(event) => event.stopPropagation()}
            >
                {children}
            </div>
        </div>,
        document.body,
    );
}
