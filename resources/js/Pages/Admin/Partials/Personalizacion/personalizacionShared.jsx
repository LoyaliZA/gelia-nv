import React from 'react';
import { createPortal } from 'react-dom';
import { X, AlertTriangle, Plus } from 'lucide-react';
import {
    THEME_INPUT,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../../utils/geliaTheme';

export const INPUT_CLASS = THEME_INPUT;
export const LABEL_CLASS = THEME_LABEL;

export {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
};

export const TH_LEFT = 'px-4 md:px-6 py-3 md:py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted text-left whitespace-nowrap';
export const TH_RIGHT = 'px-4 md:px-6 py-3 md:py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted text-right whitespace-nowrap';

export const BTN_ICON_ACTION =
    'p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:scale-105 hover:border-[var(--color-primario)]';

export const BTN_ICON_DANGER =
    'p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:bg-red-500 hover:border-red-500 group/del';

export function PanelEncabezado({ icon: Icon, titulo, subtitulo, accionLabel, onAccion }) {
    return (
        <div className="p-5 md:p-8 border-b theme-border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-start gap-3 min-w-0">
                <div className="p-3 rounded-2xl theme-element border theme-border shrink-0">
                    <Icon className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                </div>
                <div className="min-w-0">
                    <h2 className="text-lg md:text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 leading-tight">
                        {titulo}
                    </h2>
                    {subtitulo && (
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-1.5 m-0">
                            {subtitulo}
                        </p>
                    )}
                </div>
            </div>
            {accionLabel && onAccion && (
                <button type="button" onClick={onAccion} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact w-full sm:w-auto shrink-0`}>
                    <Plus className="w-4 h-4 shrink-0" />
                    {accionLabel}
                </button>
            )}
        </div>
    );
}

export function EstadoVacio({ icon: Icon, titulo, mensaje, accionLabel, onAccion }) {
    return (
        <div className="p-10 md:p-14 text-center flex flex-col items-center gap-4">
            <div className="p-4 rounded-2xl theme-element border theme-border border-dashed">
                <Icon className="w-10 h-10 theme-text-muted opacity-50" />
            </div>
            <div>
                <p className="text-sm font-black italic uppercase theme-text-main m-0">{titulo}</p>
                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 max-w-md mx-auto leading-relaxed m-0">
                    {mensaje}
                </p>
            </div>
            {accionLabel && onAccion && (
                <button type="button" onClick={onAccion} className={THEME_BTN_PRIMARY}>
                    <Plus className="w-4 h-4 shrink-0" />
                    {accionLabel}
                </button>
            )}
        </div>
    );
}

export function ModalPersonalizacion({ abierto, onClose, titulo, children, tamano = 'max-w-lg' }) {
    if (!abierto) return null;

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`}
            onClick={onClose}
        >
            <div
                className={`${THEME_MODAL_SHELL} ${tamano} modal-pop text-left w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-center gap-3 shrink-0">
                    <h2 className="text-lg md:text-xl font-black italic uppercase theme-text-main m-0 leading-tight">
                        {titulo}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                {children}
            </div>
        </div>,
        document.body
    );
}

export function ModalConfirmarEliminar({ abierto, onClose, onConfirm, titulo, mensaje }) {
    if (!abierto) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} py-4`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-md modal-pop p-6 md:p-8 space-y-6`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start gap-4">
                    <AlertTriangle className="w-8 h-8 text-red-500 shrink-0" />
                    <div>
                        <h3 className="text-base font-black uppercase theme-text-main m-0">{titulo}</h3>
                        <p className="text-sm theme-text-muted mt-2 m-0 leading-relaxed">{mensaje}</p>
                    </div>
                </div>
                <div className="flex flex-col sm:flex-row gap-3">
                    <button type="button" onClick={onClose} className={`${THEME_BTN_SECONDARY} flex-1 py-3 rounded-xl border theme-border theme-element`}>
                        Cancelar
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        className="theme-btn-danger flex-1 py-3 rounded-xl justify-center"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}

export function CampoFormulario({ label, error, children, className = '' }) {
    return (
        <div className={`space-y-2 ${className}`}>
            <label className={LABEL_CLASS}>{label}</label>
            {children}
            {error && <p className="text-xs text-red-500 font-bold">{error}</p>}
        </div>
    );
}

/** Selector binario en formularios (no usar como navegación de página). */
export function OpcionBinaria({ value, onChange, opciones, className = '' }) {
    return (
        <div className={`gelia-opcion-binaria w-full ${className}`} role="group">
            {opciones.map((op) => (
                <button
                    key={op.value}
                    type="button"
                    className="gelia-opcion-binaria__btn"
                    data-active={value === op.value}
                    onClick={() => onChange(op.value)}
                >
                    {op.label}
                </button>
            ))}
        </div>
    );
}
