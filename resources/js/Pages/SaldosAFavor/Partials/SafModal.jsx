import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from './safStyles';

/**
 * Modal SAF: portal a document.body (fuera de AppLayout / gelia-ui-scale)
 * para que fixed + blur cubran toda la ventana y quede centrado sin scroll de página.
 */
export default function SafModal({
    abierto,
    onClose,
    children,
    maxWidth = 'max-w-md',
    labelledBy,
}) {
    useEffect(() => {
        if (!abierto) return undefined;
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prev;
        };
    }, [abierto]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') onClose?.();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [abierto, onClose]);

    if (!abierto || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} !z-[9999] !items-center !justify-center overflow-y-auto`}
            role="dialog"
            aria-modal="true"
            aria-labelledby={labelledBy}
            onClick={onClose}
        >
            <div
                className={`${THEME_MODAL_SHELL} ${maxWidth} w-full my-auto modal-pop`}
                onClick={(e) => e.stopPropagation()}
            >
                {children}
            </div>
        </div>,
        document.body
    );
}
