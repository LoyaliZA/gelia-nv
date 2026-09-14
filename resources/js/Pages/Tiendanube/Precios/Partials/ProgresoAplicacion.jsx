import React from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';

const LABELS = {
    pendientes: 'Pendientes',
    procesando: 'Procesando',
    confirmadas: 'Confirmadas',
    conflictos: 'Conflictos',
    por_verificar: 'Por verificar',
    fallidas: 'Fallidas',
    canceladas: 'Canceladas',
};

export default function ProgresoAplicacion({ ejecucion, onDetener, puedeAplicar, busy }) {
    if (!ejecucion) return null;

    const abierta = ['pendiente', 'procesando', 'suspendida'].includes(ejecucion.estado);
    const porcentaje = ejecucion.porcentaje || 0;

    let titulo = `Aplicación ${ejecucion.estado}`;
    if (ejecucion.estado === 'completada' && ejecucion.exito_global) {
        titulo = 'Aplicación confirmada';
    } else if (ejecucion.estado === 'parcial') {
        titulo = 'Aplicación parcial: hay filas con conflicto o error';
    } else if (ejecucion.estado === 'suspendida') {
        titulo = ejecucion.error_mensaje || 'Envíos suspendidos';
    }

    return (
        <div className="rounded-xl border theme-border px-3 py-3 space-y-2" role="status">
            <p className="text-xs theme-text-main m-0">{titulo} ({porcentaje}%).</p>
            <div className="h-1.5 rounded-full bg-black/10 overflow-hidden">
                <div className="h-full rounded-full" style={{ width: `${porcentaje}%`, backgroundColor: 'var(--color-primario)' }} />
            </div>
            <div className="flex flex-wrap gap-x-3 gap-y-1 text-[11px] theme-text-muted">
                {Object.entries(LABELS).map(([clave, label]) => (
                    <span key={clave}>{label}: {ejecucion[clave] || 0}</span>
                ))}
            </div>
            {abierta && puedeAplicar && (
                <button
                    type="button"
                    disabled={busy}
                    onClick={onDetener}
                    className={GELIA_BTN_OUTLINE}
                >
                    Detener pendientes
                </button>
            )}
            {abierta && (
                <p className="text-[11px] theme-text-muted m-0">
                    Detener pendientes no revierte las filas ya confirmadas. Un trabajo en curso puede terminar y se reflejará su resultado.
                </p>
            )}
        </div>
    );
}
