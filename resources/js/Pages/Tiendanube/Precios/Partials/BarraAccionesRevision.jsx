import React from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';

export default function BarraAccionesRevision({
    lote,
    puedeAprobar,
    onVolver,
    onRecalcular,
    onAprobar,
    busy,
}) {
    const estado = lote?.estado;
    const resumen = lote?.revision?.resumen || {};
    const motivo = resumen.motivo_sin_aprobacion;
    const puedeIntentarAprobar = estado === 'simulado' && resumen.puede_aprobar && puedeAprobar && lote?.aprobacion_habilitada !== false;

    return (
        <div className="sticky bottom-2 z-20 border theme-border rounded-xl px-3 py-3 theme-surface flex flex-col sm:flex-row sm:items-center gap-2">
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={onVolver}
                    className={GELIA_BTN_OUTLINE}
                >
                    Volver a selección
                </button>
                <button
                    type="button"
                    disabled={busy || estado === 'aprobado'}
                    onClick={onRecalcular}
                    className={`${GELIA_BTN_OUTLINE} disabled:opacity-50`}
                >
                    Simular / Recalcular
                </button>
                <button
                    type="button"
                    disabled={busy || !puedeIntentarAprobar}
                    title={!puedeAprobar ? 'Requiere permiso de aprobación masiva' : (motivo || 'Aprobar revisión')}
                    onClick={onAprobar}
                    className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    Aprobar revisión
                </button>
            </div>
            {estado === 'simulado' && !resumen.puede_aprobar && (
                <p className="text-xs theme-text-muted m-0 sm:ml-auto">{motivo}</p>
            )}
        </div>
    );
}
