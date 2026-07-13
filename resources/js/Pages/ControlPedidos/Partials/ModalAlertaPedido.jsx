import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, XCircle, CheckCircle, Info, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, BTN_PRIMARY } from './pedidosBmaStyles';

const CONFIG = {
    error: { icon: XCircle, color: 'text-red-500', bg: 'bg-red-500/10 border-red-500/20', btn: 'bg-red-500 hover:bg-red-600 text-white' },
    warning: { icon: AlertTriangle, color: 'text-orange-500', bg: 'bg-orange-500/10 border-orange-500/20', btn: 'bg-orange-500 hover:bg-orange-600 text-white' },
    success: { icon: CheckCircle, color: 'text-emerald-500', bg: 'bg-emerald-500/10 border-emerald-500/20', btn: 'bg-emerald-500 hover:bg-emerald-600 text-white' },
    info: { icon: Info, color: 'text-blue-500', bg: 'bg-blue-500/10 border-blue-500/20', btn: 'bg-blue-500 hover:bg-blue-600 text-white' },
};

export default function ModalAlertaPedido({ abierto, tipo = 'info', titulo, mensaje, onClose }) {
    useEffect(() => {
        if (abierto) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [abierto]);

    if (!abierto) return null;

    const cfg = CONFIG[tipo] || CONFIG.info;
    const Icon = cfg.icon;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-sm w-full p-6 md:p-8 flex flex-col items-center gap-4 text-center relative`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className={`w-14 h-14 rounded-full flex items-center justify-center border ${cfg.bg}`}>
                    <Icon className={`w-7 h-7 ${cfg.color}`} />
                </div>
                <div className="space-y-2 w-full">
                    <h3 className={`text-base font-black uppercase italic tracking-tighter m-0 ${cfg.color}`}>{titulo}</h3>
                    {mensaje && <p className="text-xs font-bold theme-text-main leading-snug m-0">{mensaje}</p>}
                </div>
                <button type="button" onClick={onClose} className={`${BTN_PRIMARY} w-full py-3 ${cfg.btn}`}>
                    Entendido
                </button>
                <button type="button" onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-full outline-none" aria-label="Cerrar">
                    <X className="w-4 h-4" />
                </button>
            </div>
        </div>,
        document.body
    );
}
