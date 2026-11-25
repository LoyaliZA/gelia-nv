import React from 'react';

const dinero = (valor) => `$${Number(valor || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Lista de conceptos y montos de una cotización. `filas`: [[nombre, monto], …]. */
export default function DesgloseCotizacionNacional({ filas = [], cargando = false, etiqueta = 'Desglose de la cotización' }) {
    if (!filas.length) return null;

    return (
        <dl className="m-0 grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg border theme-border p-3 text-sm sm:grid-cols-4"
            aria-label={etiqueta} aria-busy={cargando ? 'true' : undefined}>
            {filas.map(([nombre, valor]) => (
                <div key={nombre} className={`min-w-0 ${cargando ? 'opacity-60' : ''}`}>
                    <dt className="theme-text-muted text-xs">{nombre}</dt>
                    <dd className="m-0 font-semibold">{dinero(valor)}</dd>
                </div>
            ))}
        </dl>
    );
}
