import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

function clampFlyoutTop(top, maxHeight) {
    const margin = 8;
    const viewport = typeof window !== 'undefined' ? window.innerHeight : 800;
    const cappedHeight = Math.min(maxHeight, viewport - margin * 2);
    const maxTop = viewport - cappedHeight - margin;
    return Math.max(margin, Math.min(top, maxTop));
}

/**
 * Panel flotante lateral para submenús con sidebar profesional contraído.
 */
export default function SidebarCollapsedFlyout({
    open,
    anchorRect,
    title,
    onClose,
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
        const panelHeight = panel?.offsetHeight || 280;
        const top = clampFlyoutTop(anchorRect.top, panelHeight);

        setStyle({
            top: `${top}px`,
            left: `${left}px`,
            maxHeight: `min(24rem, calc(100dvh - ${top}px - 8px))`,
        });
    }, [open, anchorRect, title, children]);

    useEffect(() => {
        if (!open) return undefined;

        const onKeyDown = (event) => {
            if (event.key === 'Escape') onClose?.();
        };

        const onPointer = (event) => {
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
    }, [open, onClose]);

    if (!open || !anchorRect || typeof document === 'undefined') return null;

    return createPortal(
        <>
            <div className="gelia-pro-flyout-backdrop" onClick={onClose} aria-hidden />
            <div
                ref={panelRef}
                className="gelia-pro-flyout"
                style={style || {
                    top: `${anchorRect.top}px`,
                    left: `${anchorRect.right + 8}px`,
                    maxHeight: 'min(24rem, calc(100dvh - 16px))',
                }}
                role="dialog"
                aria-label={title}
            >
                <div className="gelia-pro-flyout__header">
                    <span className="gelia-pro-flyout__title">{title}</span>
                    <button
                        type="button"
                        className="gelia-pro-flyout__close"
                        onClick={onClose}
                        aria-label="Cerrar menú"
                    >
                        <X className="w-4 h-4" aria-hidden />
                    </button>
                </div>
                <div className="gelia-pro-flyout__body custom-scrollbar">
                    {children}
                </div>
            </div>
        </>,
        document.body
    );
}
