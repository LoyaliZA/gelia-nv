import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { CornerDownRight } from 'lucide-react';

export default function MensajeMenuAcciones({ anchor, onCerrar, onResponder }) {
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => {
            if (ref.current?.contains(e.target)) return;
            onCerrar?.();
        };
        const keyHandler = (e) => {
            if (e.key === 'Escape') onCerrar?.();
        };
        document.addEventListener('mousedown', handler);
        document.addEventListener('keydown', keyHandler);
        return () => {
            document.removeEventListener('mousedown', handler);
            document.removeEventListener('keydown', keyHandler);
        };
    }, [onCerrar]);

    if (!anchor) return null;

    const ancho = 168;
    const left = Math.min(anchor.x, window.innerWidth - ancho - 8);
    const top = Math.min(anchor.y, window.innerHeight - 56);

    return createPortal(
        <div
            ref={ref}
            className="gelia-mensaje-menu fixed z-[500] py-1 rounded-xl border theme-border theme-surface-solid shadow-lg"
            style={{ left: Math.max(8, left), top: Math.max(8, top), minWidth: ancho }}
            role="menu"
        >
            <button
                type="button"
                role="menuitem"
                className="gelia-mensaje-menu__item w-full flex items-center gap-2 px-3 py-2 text-xs font-bold theme-text-main"
                onClick={() => {
                    onResponder?.();
                    onCerrar?.();
                }}
            >
                <CornerDownRight className="w-4 h-4" />
                Responder
            </button>
        </div>,
        document.body
    );
}
