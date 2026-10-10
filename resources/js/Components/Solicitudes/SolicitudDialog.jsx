import React, { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import usePedidoDialog from '@/Pages/ControlPedidos/Partials/usePedidoDialog';

/** Dialog behavior is shared; styling is scoped to commercial request workflows. */
export default function SolicitudDialog({ children, onClose, busy = false, dirty = false, title = 'Detalle de solicitud', className = '' }) {
    const [confirmar, setConfirmar] = useState(false);
    const id = useId();
    const volverRef = useRef(null);
    const cerrar = () => {
        if (busy) return;
        if (dirty) setConfirmar(true);
        else onClose();
    };
    const dialog = usePedidoDialog({ abierto: true, onClose: cerrar, bloqueado: busy });
    useEffect(() => {
        if (confirmar) volverRef.current?.focus();
    }, [confirmar]);
    useEffect(() => {
        if (!dirty) return undefined;
        const avisar = (event) => { event.preventDefault(); event.returnValue = ''; };
        window.addEventListener('beforeunload', avisar);
        return () => window.removeEventListener('beforeunload', avisar);
    }, [dirty]);

    return createPortal(
        <div className={`gelia-modal-overlay gelia-workflow-overlay ${className}`} onMouseDown={(event) => { if (event.target === event.currentTarget) cerrar(); }}>
            <div {...dialog} aria-label={title} aria-busy={busy} className="gelia-workflow-dialog"
                onMouseDown={(event) => { if (event.target === event.currentTarget) cerrar(); }}
                onClickCapture={(event) => {
                    const boton = event.target.closest('button[data-dialog-close]');
                    if (boton && event.currentTarget.contains(boton)) { event.preventDefault(); event.stopPropagation(); cerrar(); }
                }}>
                {confirmar ? (
                    <div className="gelia-modal-shell gelia-workflow-confirm p-6 space-y-4" aria-labelledby={id}>
                        <h2 id={id} className="m-0 theme-text-main">¿Descartar los cambios?</h2>
                        <p className="text-sm theme-text-muted m-0">La información y los archivos de esta respuesta aún no se han enviado.</p>
                        <div className="flex flex-wrap justify-end gap-2">
                            <button ref={volverRef} type="button" className="theme-btn-secondary" onClick={() => { setConfirmar(false); dialog.ref.current?.focus(); }}>Seguir editando</button>
                            <button type="button" className="theme-btn-danger" onClick={onClose}>Descartar cambios</button>
                        </div>
                    </div>
                ) : children}
            </div>
        </div>, document.body
    );
}
