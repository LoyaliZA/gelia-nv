import { rhBadgeClass } from '../../rhModuleStyles';

export function getSalidaStatusInfo(salida) {
    if (!salida.hora_regreso) {
        return {
            key: 'pendiente_regreso',
            label: 'Fuera (Pendiente Regreso)',
            badgeClass: rhBadgeClass('rose'),
        };
    }
    if (!salida.fecha_deduccion_nomina) {
        return {
            key: 'pendiente_cobro',
            label: 'Completado (Pendiente Cobro)',
            badgeClass: rhBadgeClass('amber'),
        };
    }
    return {
        key: 'cobrado',
        label: `Cobrado (${salida.fecha_deduccion_nomina})`,
        badgeClass: rhBadgeClass('emerald'),
    };
}
