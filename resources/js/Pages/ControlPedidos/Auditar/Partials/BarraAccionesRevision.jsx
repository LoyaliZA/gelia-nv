import React from 'react';
import { CheckCircle2, Ellipsis } from 'lucide-react';
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
        <div className="gelia-pedidos-auditoria-acciones flex flex-wrap items-start gap-2 min-w-0">
            {(puedeLiberarResguardo || esPendiente) && <details className="gelia-pedidos-acciones-secundarias">
                <summary className={`${BTN_SECONDARY} h-11 w-11 !p-0`} title="Más acciones">
                    <Ellipsis size={20} aria-hidden="true" />
                    <span className="sr-only">Más acciones</span>
                </summary>
                <div className="gelia-pedidos-acciones-secundarias-opciones grid gap-2 border theme-border theme-surface-solid theme-no-blur rounded-xl p-2">
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
                </div>
            </details>}
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
        </div>
    );
}
