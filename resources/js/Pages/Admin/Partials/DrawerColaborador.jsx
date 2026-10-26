import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

function nodosEnfocables(panel) {
    return [...panel.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )].filter((nodo) => !nodo.closest('[hidden]') && nodo.tabIndex !== -1);
}

export default function DrawerColaborador({
    abierto,
    modo = 'superpuesto',
    onCerrar,
    movimiento = false,
    sucio = false,
    clave = '',
    ancho = null,
    children,
}) {
    const panelRef = useRef(null);
    const cerrarRef = useRef(onCerrar);
    cerrarRef.current = onCerrar;

    useEffect(() => {
        if (!abierto) return undefined;
        const panel = panelRef.current;
        panel?.focus();

        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                cerrarRef.current();
                return;
            }
            if (modo !== 'superpuesto' || event.key !== 'Tab' || !panel) return;
            const nodos = nodosEnfocables(panel);
            if (nodos.length === 0) return;
            const primero = nodos[0];
            const ultimo = nodos[nodos.length - 1];
            if (event.shiftKey && document.activeElement === primero) {
                event.preventDefault();
                ultimo.focus();
            } else if (!event.shiftKey && document.activeElement === ultimo) {
                event.preventDefault();
                primero.focus();
            }
        };

        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [abierto, modo, clave]);

    if (!abierto || typeof document === 'undefined') return null;

    const superpuesto = modo === 'superpuesto';

    return createPortal(
            <div
                className={`usuarios-drawer-raiz usuarios-drawer-raiz--${modo}`}
                style={modo === 'lateral' && ancho ? { width: `${ancho}px` } : undefined}
            >
            {superpuesto && (
                <button
                    type="button"
                    className="usuarios-drawer-scrim"
                    aria-label="Cerrar panel"
                    onClick={onCerrar}
                />
            )}
            <div
                ref={panelRef}
                className="usuarios-drawer gelia-modal-shell"
                role="dialog"
                aria-modal={superpuesto ? 'true' : 'false'}
                aria-labelledby="drawer-colaborador-titulo"
                tabIndex={-1}
                data-movimiento={movimiento ? '1' : '0'}
                {...(sucio ? { 'data-gelia-unsaved-form': '1' } : {})}
            >
                {children}
            </div>
        </div>,
        document.body,
    );
}
