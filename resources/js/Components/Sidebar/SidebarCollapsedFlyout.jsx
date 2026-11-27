import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

const VIEWPORT_MARGIN = 8;

/** Alinea el panel con su fila y lo sube lo necesario para que quepa entero en la pantalla. */
function clampFlyoutTop(top, panelHeight) {
    const viewport = typeof window !== 'undefined' ? window.innerHeight : 800;
    const alto = Math.min(panelHeight, viewport - VIEWPORT_MARGIN * 2);
    const maxTop = viewport - alto - VIEWPORT_MARGIN;
    return Math.max(VIEWPORT_MARGIN, Math.min(top, maxTop));
}

/**
 * Panel flotante lateral para submenús con sidebar profesional contraído.
 */
export default function SidebarCollapsedFlyout({
    open,
    anchorRect,
    title,
    eyebrow = '',
    icon: Icon,
    onClose,
    onMouseEnter,
    onMouseLeave,
    hoverMode = false,
    children,
}) {
    const panelRef = useRef(null);
    const [style, setStyle] = useState(null);

    useLayoutEffect(() => {
        if (!open || !anchorRect) {
            setStyle(null);
            return;
        }

        const gap = 8;
        const left = anchorRect.right + gap;
        const panel = panelRef.current;
        // El alto sigue a las opciones; solo se limita al de la pantalla (con scroll interno si no caben).
        const cabecera = panel?.firstElementChild?.offsetHeight ?? 0;
        const cuerpo = panel?.querySelector('.gelia-pro-flyout__body')?.scrollHeight ?? 0;
        const panelHeight = cabecera + cuerpo + 2 || panel?.offsetHeight || 280;
        const top = clampFlyoutTop(anchorRect.top, panelHeight);

        setStyle({
            top: `${top}px`,
            left: `${left}px`,
            maxHeight: `calc(100dvh - ${top}px - ${VIEWPORT_MARGIN}px)`,
        });
    }, [open, anchorRect, title, children]);

    useEffect(() => {
        if (!open) return undefined;

        const onKeyDown = (event) => {
            if (event.key === 'Escape') onClose?.();
        };

        const onPointer = (event) => {
            if (hoverMode) return;
            if (panelRef.current?.contains(event.target)) return;
            onClose?.();
        };

        document.addEventListener('keydown', onKeyDown);
        const timer = setTimeout(() => document.addEventListener('mousedown', onPointer), 0);
        return () => {
            clearTimeout(timer);
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('mousedown', onPointer);
        };
    }, [open, onClose, hoverMode]);

    if (!open || !anchorRect || typeof document === 'undefined') return null;

    return createPortal(
        <>
            {!hoverMode && (
                <div className="gelia-pro-flyout-backdrop" onClick={onClose} aria-hidden />
            )}
            <div
                ref={panelRef}
                className={`gelia-pro-flyout ${hoverMode ? 'gelia-pro-flyout--hover' : ''}`}
                style={style || {
                    top: `${anchorRect.top}px`,
                    left: `${anchorRect.right + 8}px`,
                    maxHeight: 'calc(100dvh - 16px)',
                }}
                role="dialog"
                aria-label={title}
                onMouseEnter={onMouseEnter}
                onMouseLeave={onMouseLeave}
            >
                <div className="gelia-pro-flyout__header">
                    {Icon && (
                        <span className="gelia-pro-flyout__tile" aria-hidden>
                            <Icon className="w-[1.125rem] h-[1.125rem]" />
                        </span>
                    )}
                    <div className="gelia-pro-flyout__heading">
                        {eyebrow && <span className="gelia-pro-flyout__eyebrow">{eyebrow}</span>}
                        <span className="gelia-pro-flyout__title">{title}</span>
                    </div>
                    {!hoverMode && (
                        <button
                            type="button"
                            className="gelia-pro-flyout__close"
                            onClick={onClose}
                            aria-label="Cerrar menú"
                        >
                            <X className="w-4 h-4" aria-hidden />
                        </button>
                    )}
                </div>
                <div className="gelia-pro-flyout__body custom-scrollbar">
                    {children}
                </div>
            </div>
        </>,
        document.body
    );
}
