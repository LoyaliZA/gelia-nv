export function getSalidaStatusInfo(salida) {
    if (!salida.hora_regreso) {
        return {
            key: 'pendiente_regreso',
            label: 'Fuera (Pendiente Regreso)',
            badgeClass: 'bg-rose-500/10 text-rose-600 border-rose-500/20 border',
        };
    }
    if (!salida.fecha_deduccion_nomina) {
        return {
            key: 'pendiente_cobro',
            label: 'Completado (Pendiente Cobro)',
            badgeClass: 'bg-amber-500/10 text-amber-600 border-amber-500/20 border',
        };
    }
    return {
        key: 'cobrado',
        label: `Cobrado (${salida.fecha_deduccion_nomina})`,
        badgeClass: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 border',
    };
}
