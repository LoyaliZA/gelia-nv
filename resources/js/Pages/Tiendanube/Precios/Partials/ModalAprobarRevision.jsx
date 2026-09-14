import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { GELIA_BTN_OUTLINE, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';

export default function ModalAprobarRevision({ lote, onCancelar, onConfirmar, busy }) {
    const resumen = lote?.revision?.resumen || {};
    const moneda = lote?.moneda || resumen.moneda || 'MXN';

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape' && !busy) onCancelar();
        };
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = 'unset';
            window.removeEventListener('keydown', onKey);
        };
    }, [busy, onCancelar]);

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-end md:items-center`}
            onClick={onCancelar}
            role="presentation"
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full p-4 md:p-6 space-y-3 modal-pop`}
                role="dialog"
                aria-modal="true"
                aria-labelledby="tn-aprobar"
                onClick={(e) => e.stopPropagation()}
            >
                <h2 id="tn-aprobar" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                    Aprobar revisión
                </h2>
                <p className="text-sm theme-text-main m-0">
                    {resumen.variantes_normal || 0} variantes cambiarán precio normal, {resumen.variantes_promo || 0} promoción, {resumen.variantes_costo || 0} costo.
                    Moneda {moneda}.
                    {resumen.excluidas ? ` ${resumen.excluidas} filas quedan fuera de esta aprobación.` : ''}
                    {resumen.excluidas_invalidas ? ` Incluye ${resumen.excluidas_invalidas} inválidas excluidas con evidencia.` : ''}
                </p>
                <p className="text-xs theme-text-muted m-0">
                    Aprobar no publica todavía. Después podrá aplicar por API o exportar CSV sobre esta revisión exacta.
                </p>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={onCancelar}
                        className={GELIA_BTN_OUTLINE}
                    >
                        Volver
                    </button>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={onConfirmar}
                        className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Confirmar aprobación
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
