import React from 'react';

function num(valor) {
    return valor ?? 0;
}

export default function ResumenSimulacionImportacion({ resumen }) {
    if (!resumen) return null;

    const costosTotal = num(resumen.costos_creados) + num(resumen.costos_actualizados);
    const lineas = [
        `${num(resumen.total_filas)} filas`,
        `${num(resumen.productos_creados)} productos nuevos`,
        `${num(resumen.productos_actualizados)} productos actualizados`,
        `${num(resumen.sin_cambios)} sin cambios`,
        `${num(resumen.errores)} errores`,
        `${num(resumen.asignaciones_creadas)} vínculos al almacén`,
        `${costosTotal} costos actualizados`,
        `${num(resumen.cantidades_actualizadas)} existencias actualizadas`,
    ];

    return (
        <p className="text-[11px] font-bold theme-text-main">
            {lineas.join(' · ')}
        </p>
    );
}
