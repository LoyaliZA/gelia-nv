import React from 'react';
import { CheckCircle2 } from 'lucide-react';
import { BTN_PRIMARY, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

/** Acciones operativas; la X de la cabecera cierra la revisión. */
export default function BarraAccionesRevision({
    procesando,
    puedeLiberarResguardo,
    esPendiente,
    muestraAprobar,
    puedeAprobar,
    pagoValidado,
    tieneRemision,
    onLiberar,
    onReportarError,
    onAprobar,
}) {
    if (!puedeLiberarResguardo && !esPendiente && !muestraAprobar) return null;
    return (
        <div className="gelia-pedidos-auditoria-acciones flex flex-wrap items-center justify-end gap-2 min-w-0">
            {puedeLiberarResguardo && (
                <button
                    type="button"
                    onClick={onLiberar}
                    disabled={procesando}
                    className={`${BTN_SECONDARY} theme-element border theme-border theme-text-info outline-none`}
                >
                    Liberar resguardo
                </button>
            )}
            {esPendiente && (
                <button
                    type="button"
                    onClick={onReportarError}
                    disabled={procesando}
                    className={`${BTN_SECONDARY} theme-element border theme-border theme-text-peligro outline-none`}
                >
                    Reportar error
                </button>
            )}
            {muestraAprobar && (
                <button
                    type="button"
                    onClick={onAprobar}
                    disabled={!puedeAprobar || procesando}
                    className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 `}
                    title={!pagoValidado ? 'Valide el pago antes de aprobar' : !tieneRemision ? 'Adjunte la remisión PDF antes de aprobar' : ''}
                >
                    <CheckCircle2 className="w-4 h-4" /> Aprobar pedido
                </button>
            )}
            {muestraAprobar && !puedeAprobar && (
                <p className="text-xs theme-text-muted m-0 w-full">{!pagoValidado ? 'Valida el pago para aprobar el pedido.' : !tieneRemision ? 'Adjunta la remisión PDF para aprobar el pedido.' : ''}</p>
            )}
        </div>
    );
}
