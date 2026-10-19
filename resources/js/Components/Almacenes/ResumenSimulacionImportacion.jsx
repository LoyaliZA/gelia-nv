import React from 'react';

function num(valor) {
    return valor ?? 0;
}

export default function ResumenSimulacionImportacion({ resumen }) {
    if (!resumen) return null;

    const filas = resumen.detalle_filas || [];
    const omitidas = num(resumen.detalle_filas_omitidas);
    const costosTotal = num(resumen.costos_creados) + num(resumen.costos_actualizados);
    const conteos = [
        `${num(resumen.total_filas)} filas`,
        `${num(resumen.altas_total || resumen.productos_creados)} productos nuevos`,
        `${num(resumen.productos_actualizados)} productos actualizados`,
        `${num(resumen.asignaciones_creadas)} vínculos`,
        `${costosTotal} costos`,
        `${num(resumen.cantidades_actualizadas)} existencias`,
        `${num(resumen.sin_cambios)} sin cambios`,
        `${num(resumen.errores)} errores`,
    ];

    return (
        <div className="space-y-3">
            <ul className="flex flex-wrap gap-2">
                {conteos.map((texto) => (
                    <li key={texto} className="rounded-full theme-element px-3 py-1 text-sm theme-text-main">{texto}</li>
                ))}
            </ul>
            {filas.length > 0 && (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[36rem] text-sm border-collapse">
                        <caption className="sr-only">Filas de la simulación</caption>
                        <thead>
                            <tr className="theme-text-muted text-left">
                                <th className="sticky left-0 bg-[var(--theme-surface-bg)] py-2 pr-3 font-semibold">Folio</th>
                                <th className="py-2 pr-3 font-semibold">Fila</th>
                                <th className="py-2 pr-3 font-semibold">SKU</th>
                                <th className="py-2 font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((fila) => (
                                <tr key={`${fila.fila}-${fila.accion}`} className="border-t theme-border">
                                    <td className="sticky left-0 bg-[var(--theme-surface-bg)] py-2 pr-3">{fila.folio ?? 'vacío'}</td>
                                    <td className="py-2 pr-3 tabular-nums">{fila.fila}</td>
                                    <td className="py-2 pr-3">{fila.sku || '—'}</td>
                                    <td className="py-2">{fila.accion}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {omitidas > 0 && (
                <p className="text-sm theme-text-muted">
                    Hay {omitidas} filas más fuera de esta tabla. El conteo de arriba incluye el archivo completo.
                </p>
            )}
        </div>
    );
}
