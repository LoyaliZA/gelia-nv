import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, X } from 'lucide-react';
import { THEME_INPUT } from '../../../utils/geliaTheme';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_LABEL, BTN_PRIMARY, BTN_SECONDARY } from './pedidosBmaStyles';

export default function ModalMotivoRechazo({
    abierto,
    onClose,
    onConfirm,
    titulo = 'Reportar problema',
    descripcion = 'El pedido se devolverá a la vendedora',
    labelCampo = 'Motivo del reporte',
    placeholder = 'Describe el problema encontrado...',
    confirmLabel = 'Enviar reporte',
    minLength = 5,
}) {
    const [motivo, setMotivo] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        if (abierto) {
            setMotivo('');
            setError('');
        }
    }, [abierto]);

    if (!abierto) return null;

    const enviar = () => {
        const texto = motivo.trim();
        if (texto.length < minLength) {
            setError(`El texto debe tener al menos ${minLength} caracteres.`);
            return;
        }
        onConfirm(texto);
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} onClick={onClose} style={{ zIndex: 'calc(var(--gelia-z-modal) + 20)' }}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3">
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 text-orange-500" />
                            {titulo}
                        </h2>
                        {descripcion ? (
                            <p className="text-xs theme-text-muted font-bold mt-1 m-0">{descripcion}</p>
                        ) : null}
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="p-5 space-y-4">
                    <div>
                        <label className={THEME_LABEL}>{labelCampo}</label>
                        <textarea
                            value={motivo}
                            onChange={(e) => { setMotivo(e.target.value); setError(''); }}
                            rows={4}
                            className={`${THEME_INPUT} w-full py-3`}
                            placeholder={placeholder}
                        />
                        {error && <p className="text-xs text-red-500 font-bold mt-1 m-0">{error}</p>}
                    </div>
                    <div className="flex flex-col sm:flex-row gap-3">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} flex-1 py-3 rounded-xl border theme-border theme-element`}>
                            Cancelar
                        </button>
                        <button type="button" onClick={enviar} className={`${BTN_PRIMARY} flex-1 py-3`}>
                            {confirmLabel}
                        </button>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
