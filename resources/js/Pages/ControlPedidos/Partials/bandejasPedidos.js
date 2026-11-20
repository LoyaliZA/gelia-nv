import { TABS_PEDIDOS, TABS_PEDIDOS_ADMIN } from './pedidosBmaStyles';

/**
 * Fuente única de bandejas de Gestión de pedidos: grupo por flujo, clave de métrica y tono.
 * `tono` solo se muestra cuando la bandeja tiene pedidos pendientes de acción.
 */
const BANDEJAS = [
    { id: 'PESAJE_PENDIENTE', metrica: 'pesaje_pendiente', grupo: 'atender', tono: 'aviso' },
    { id: 'PESAJE_RESPONDIDO', metrica: 'pesaje_respondido', grupo: 'atender', tono: 'info' },
    { id: 'RECHAZADAS', metrica: 'rechazadas', grupo: 'atender', tono: 'error' },
    { id: 'OBS_CEDIS', metrica: 'obs_cedis', grupo: 'atender', tono: 'aviso' },
    { id: 'SIN_EXISTENCIA', metrica: 'sin_existencia', grupo: 'atender', tono: 'error' },
    { id: 'PENDIENTE_AUXILIAR', metrica: 'pendiente_auxiliar', grupo: 'proceso', tono: 'info' },
    { id: 'EN_CEDIS', metrica: 'en_cedis', grupo: 'proceso', tono: 'aviso' },
    { id: 'PENDIENTE_GUIA_CLIENTE', metrica: 'pendiente_guia_cliente', grupo: 'proceso', tono: 'aviso' },
    { id: 'BORRADORES', metrica: 'borradores', grupo: 'cerrados', tono: 'neutro' },
    { id: 'ENVIADOS', metrica: 'enviados', grupo: 'cerrados', tono: 'neutro' },
    { id: 'TODAS', metrica: 'todas', grupo: 'cerrados', tono: 'neutro' },
    { id: 'ELIMINADAS', metrica: 'eliminadas', grupo: 'cerrados', tono: 'neutro', admin: true },
];

const GRUPOS = [
    { key: 'atender', label: 'Por atender' },
    { key: 'proceso', label: 'En proceso' },
    { key: 'cerrados', label: 'Otras vistas' },
];

const ETIQUETAS = Object.fromEntries([...TABS_PEDIDOS, ...TABS_PEDIDOS_ADMIN].map((t) => [t.id, t.label]));

export const etiquetaBandeja = (id) => ETIQUETAS[id] || id;

export function gruposBandejas({ incluirEliminadas = false } = {}) {
    return GRUPOS.map((grupo) => ({
        ...grupo,
        bandejas: BANDEJAS
            .filter((b) => b.grupo === grupo.key && (!b.admin || incluirEliminadas))
            .map((b) => ({ ...b, label: etiquetaBandeja(b.id) })),
    }));
}

export const conteoBandeja = (metricas, id) => metricas?.[BANDEJAS.find((b) => b.id === id)?.metrica];

/** Días naturales desde la fecha del pedido; null si no es interpretable. */
export function diasDesde(valor, ahora = new Date()) {
    if (!valor) return null;
    const texto = String(valor).slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(texto)) return null;
    const inicio = Date.parse(`${texto}T00:00:00Z`);
    const hoy = Date.UTC(ahora.getFullYear(), ahora.getMonth(), ahora.getDate());
    if (Number.isNaN(inicio)) return null;
    return Math.max(0, Math.round((hoy - inicio) / 86400000));
}

export function textoAntiguedad(dias) {
    if (dias === null) return '—';
    if (dias === 0) return 'Hoy';
    if (dias === 1) return 'Ayer';
    return `Hace ${dias} días`;
}
