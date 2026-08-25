import React from 'react';
import { CheckCircle2 } from 'lucide-react';
import { BTN_PRIMARY, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

/** Footer operativo: Cerrar / Liberar / Reportar / Aprobar (Validar-Rechazar viven en el bloque de pago). */
export default function BarraAccionesRevision({
    procesando,
    puedeLiberarResguardo,
    esPendiente,
    muestraAprobar,
    puedeAprobar,
    pagoValidado,
    tieneRemision,
    onClose,
    onLiberar,
    onReportarError,
    onAprobar,
}) {
    return (
        <div className="gelia-modal-footer flex flex-wrap gap-3 p-5 md:p-6 border-t theme-border shrink-0">
            <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border outline-none`}>
                Cerrar
            </button>
            {puedeLiberarResguardo && (
                <button
                    type="button"
                    onClick={onLiberar}
                    disabled={procesando}
                    className={`${BTN_SECONDARY} theme-element border border-blue-500/40 text-blue-600 outline-none`}
                >
                    Liberar resguardo
                </button>
            )}
            {esPendiente && (
                <button
                    type="button"
                    onClick={onReportarError}
                    disabled={procesando}
                    className={`${BTN_SECONDARY} theme-element border border-red-500/40 text-red-500 outline-none`}
                >
                    Reportar error
                </button>
            )}
            {muestraAprobar && (
                <button
                    type="button"
                    onClick={onAprobar}
                    disabled={!puedeAprobar || procesando}
                    className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                    title={!pagoValidado ? 'Valide el pago antes de aprobar' : !tieneRemision ? 'Adjunte la remisión PDF antes de aprobar' : ''}
                >
                    <CheckCircle2 className="w-4 h-4" /> Aprobar y enviar a Registro General
                </button>
            )}
        </div>
    );
}
