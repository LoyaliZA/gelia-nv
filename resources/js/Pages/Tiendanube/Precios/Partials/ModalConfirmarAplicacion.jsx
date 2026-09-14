import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { GELIA_BTN_OUTLINE, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';

export default function ModalConfirmarAplicacion({
    lote,
    ejecucionPrevia,
    vigencia,
    onCancelar,
    onConfirmar,
    busy,
}) {
    const resumen = lote?.revision?.resumen || {};
    const campos = ejecucionPrevia?.resumen_campos || {
        normal: resumen.variantes_normal || 0,
        promocional: resumen.variantes_promo || 0,
        costo_remoto: resumen.variantes_costo || 0,
    };

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
                aria-labelledby="tn-aplicar"
                onClick={(e) => e.stopPropagation()}
            >
                <h2 id="tn-aplicar" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                    Aplicar en TiendaNube
                </h2>
                <p className="text-sm theme-text-main m-0">
                    Tienda {lote?.store_id || '—'}. Se enviarán los importes ya aprobados:
                    {' '}{campos.normal || 0} precio normal, {campos.promocional || 0} promoción, {campos.costo_remoto || 0} costo.
                    {resumen.excluidas ? ` ${resumen.excluidas} variantes quedan fuera.` : ''}
                </p>
                <p className="text-xs theme-text-muted m-0">
                    {vigencia?.ok
                        ? 'La revisión sigue vigente para esta tienda y generación.'
                        : (vigencia?.mensaje || 'La vigencia se comprobará en el servidor al enviar.')}
                </p>
                <p className="text-xs theme-text-muted m-0">
                    No hace falta volver a escribir precios. Cerrar el navegador no detiene el trabajo en el servidor.
                </p>
                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={onCancelar} className={GELIA_BTN_OUTLINE}>
                        Volver
                    </button>
                    <button
                        type="button"
                        disabled={busy || vigencia?.ok === false}
                        onClick={onConfirmar}
                        className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Confirmar envío
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
