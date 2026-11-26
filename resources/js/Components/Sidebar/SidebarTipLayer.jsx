import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const SHOW_DELAY_MS = 280;

function tipTarget(node) {
    const el = node instanceof Element ? node.closest('[data-tip]') : null;
    return el && el.getAttribute('data-tip') ? el : null;
}

/**
 * Tooltips del rail contraído. El aside recorta su contenido (overflow + contain),
 * así que el texto se pinta en un portal con posición fija junto al elemento enfocado.
 */
export default function SidebarTipLayer({ rootRef, enabled = false }) {
    const [tip, setTip] = useState(null);
    const timerRef = useRef(null);

    useEffect(() => {
        const root = rootRef?.current;
        if (!root || !enabled) {
            setTip(null);
            return undefined;
        }

        const hide = () => {
            clearTimeout(timerRef.current);
            setTip(null);
        };

        const show = (event) => {
            const el = tipTarget(event.target);
            clearTimeout(timerRef.current);
            if (!el) {
                setTip(null);
                return;
            }
            timerRef.current = setTimeout(() => {
                const rect = el.getBoundingClientRect();
                setTip({
                    text: el.getAttribute('data-tip'),
                    top: rect.top + rect.height / 2,
                    left: rect.right + 8,
                });
            }, SHOW_DELAY_MS);
        };

        const nav = root.querySelector('.gelia-pro-sidebar__nav');
        root.addEventListener('mouseover', show);
        root.addEventListener('focusin', show);
        root.addEventListener('mouseleave', hide);
        root.addEventListener('focusout', hide);
        root.addEventListener('click', hide);
        nav?.addEventListener('scroll', hide, { passive: true });

        return () => {
            clearTimeout(timerRef.current);
            root.removeEventListener('mouseover', show);
            root.removeEventListener('focusin', show);
            root.removeEventListener('mouseleave', hide);
            root.removeEventListener('focusout', hide);
            root.removeEventListener('click', hide);
            nav?.removeEventListener('scroll', hide);
        };
    }, [rootRef, enabled]);

    if (!tip || typeof document === 'undefined') return null;

    return createPortal(
        <div className="gelia-pro-tip" role="tooltip" style={{ top: `${tip.top}px`, left: `${tip.left}px` }}>
            {tip.text}
        </div>,
        document.body
    );
}
