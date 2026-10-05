import { badgeClaseEstatusPedido } from '../../../ControlPedidos/Partials/pedidosBmaStyles';

export const BANNER_SUCURSAL_VISITA =
    'text-sm font-black uppercase tracking-widest text-center py-2 px-3 m-0 bg-[var(--color-primario)]/10';

export const CELDA_ETIQUETA = 'text-[9px] font-black m-0 opacity-70 uppercase';
export const CELDA_VALOR = 'text-xs theme-text-main m-0 mt-0.5 normal-case font-bold';

export function badgeEstadoVisita(estado, etiqueta) {
    const hexPorEstado = {
        programada: '#A855F7',
        asistio: '#22C55E',
        no_asistio: '#94A3B8',
    };
    const hex = hexPorEstado[estado] || '#94A3B8';
    return {
        label: etiqueta || estado || '—',
        ...badgeClaseEstatusPedido({ color_hex: hex }),
    };
}

export function badgeIntencionVisita(intencion, etiqueta) {
    const hexPorIntencion = {
        confirmo_asistencia: '#22C55E',
        posible_asistencia: '#EAB308',
    };
    const hex = hexPorIntencion[intencion] || '#3B82F6';
    return {
        label: etiqueta || 'Intención',
        ...badgeClaseEstatusPedido({ color_hex: hex }),
    };
}

export function badgeTiempoVisita(estadoTiempo) {
    if (estadoTiempo === 'retrasado') {
        return { label: 'Retrasado', ...badgeClaseEstatusPedido({ color_hex: '#F59E0B' }) };
    }
    if (estadoTiempo === 'en_tiempo') {
        return { label: 'En tiempo', ...badgeClaseEstatusPedido({ color_hex: '#22C55E' }) };
    }
    return null;
}

export function formatearFechaVisita(fechaIso) {
    if (!fechaIso) return '—';
    try {
        const [y, m, d] = fechaIso.split('-').map(Number);
        const dt = new Date(y, m - 1, d);
        return dt.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
        return fechaIso;
    }
}
