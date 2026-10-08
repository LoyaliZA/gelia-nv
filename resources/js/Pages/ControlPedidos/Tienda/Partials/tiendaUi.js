import {
    Clock, Package, Truck, AlertTriangle, CheckCircle2, Undo2, Store,
} from 'lucide-react';
import { GELIA_ESTADO_VIVO_TONO } from '../../../../utils/geliaTheme';

/** Métricas rápidas → tab de bandeja (sin colores hex; tono semántico GELIA). */
export const KPI_METRICAS_TIENDA = [
    { key: 'pendientes', label: 'Pendientes', tab: 'PENDIENTES', icon: Clock, tono: 'aviso' },
    { key: 'en_atencion', label: 'En atención', tab: 'EN_ATENCION', icon: Package, tono: 'info' },
    { key: 'con_incidencia', label: 'Incidencia', tab: 'CON_INCIDENCIA', icon: AlertTriangle, tono: 'error' },
    { key: 'listas_traslado', label: 'Listas traslado', tab: 'LISTAS_TRASLADO', icon: Truck, tono: 'info' },
    { key: 'listas_caratula', label: 'Listas carátula', tab: 'LISTAS_CARATULA', icon: Package, tono: 'exito' },
    { key: 'en_traslado', label: 'En traslado', tab: 'EN_TRASLADO', icon: Truck, tono: 'info' },
    { key: 'rechazadas_cedis', label: 'Rechazadas CEDIS', tab: 'RECHAZADAS_CEDIS', icon: Undo2, tono: 'error' },
    { key: 'respondidas_hoy', label: 'Respondidas hoy', tab: 'RESPONDIDAS_HOY', icon: CheckCircle2, tono: 'exito' },
    { key: 'pendientes_liberacion', label: 'Liberación', tab: 'PENDIENTES_LIBERACION', icon: Store, tono: 'aviso' },
    { key: 'devolucion_pendiente', label: 'Devolución', tab: 'DEVOLUCION_PENDIENTE', icon: Undo2, tono: 'aviso' },
    { key: 'historial_devueltas', label: 'Devueltas', tab: 'HISTORIAL_DEVUELTAS', icon: CheckCircle2, tono: 'neutro' },
];

export const TONO_ESTADO_TIENDA = {
    PENDIENTE: 'aviso',
    EN_ATENCION: 'info',
    CON_INCIDENCIA: 'error',
    LISTA_PARA_TRASLADO: 'info',
    LISTA_PARA_CARATULA: 'exito',
    EN_TRASLADO: 'info',
    RECIBIDA_CEDIS: 'exito',
    RECHAZADA_CEDIS: 'error',
    RESPONDIDA: 'exito',
    LIBERACION_SOLICITADA: 'aviso',
    LIBERADA: 'neutro',
    CANCELADA: 'neutro',
};

export const ETIQUETA_ESTADO_TIENDA = {
    PENDIENTE: 'Pendiente',
    EN_ATENCION: 'En atención',
    CON_INCIDENCIA: 'Incidencia',
    LISTA_PARA_TRASLADO: 'Lista traslado',
    LISTA_PARA_CARATULA: 'Lista carátula',
    EN_TRASLADO: 'En traslado',
    RECIBIDA_CEDIS: 'Recibida CEDIS',
    RECHAZADA_CEDIS: 'Rechazada CEDIS',
    RESPONDIDA: 'Respondida',
    LIBERACION_SOLICITADA: 'Liberación',
    LIBERADA: 'Liberada',
    CANCELADA: 'Cancelada',
};

export function claseBadgeEstadoTienda(estado, estadoLabel) {
    const tono = TONO_ESTADO_TIENDA[estado] || 'neutro';
    const tonoClass = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;
    const label = estadoLabel || ETIQUETA_ESTADO_TIENDA[estado] || estado || '—';
    return {
        label,
        className: `gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-[11px] font-semibold ${tonoClass}`,
    };
}

export function fmtRelativo(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    const mins = Math.floor((Date.now() - d.getTime()) / 60000);
    if (mins < 60) return `hace ${Math.max(0, mins)} min`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 48) return `hace ${hrs} h`;
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

export function fmtVencimiento(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    const diff = d.getTime() - Date.now();
    if (diff < 0) return { texto: 'Vencida', urgente: true };
    const dias = Math.ceil(diff / 86400000);
    return { texto: `Vence en ${dias} día${dias === 1 ? '' : 's'}`, urgente: dias <= 1 };
}

export function avisoParaTarea(t, venc) {
    if (t.estado === 'CON_INCIDENCIA' || t.estado === 'RECHAZADA_CEDIS') {
        return {
            label: t.estado === 'RECHAZADA_CEDIS' ? 'Rechazo CEDIS' : 'Incidencia',
            tono: 'danger',
            icon: AlertTriangle,
            texto: t.motivo_rechazo_cedis
                || t.observaciones_respuesta
                || 'Hay un problema reportado. Revise el detalle.',
        };
    }
    if (t.estado === 'PENDIENTE') {
        return {
            label: 'Nueva solicitud',
            tono: 'warning',
            icon: Package,
            texto: t.requiere_traslado_cedis
                ? 'Tome la tarea, localice piezas y prepare el traslado a CEDIS.'
                : 'Tome la tarea, localice piezas y responda con evidencia.',
        };
    }
    if (t.estado === 'LISTA_PARA_TRASLADO') {
        return {
            label: 'Lista para traslado',
            tono: 'info',
            icon: Truck,
            texto: t.solicitud_traspaso?.folio
                ? `Traspaso ${t.solicitud_traspaso.folio}. Confirme la salida hacia CEDIS.`
                : 'Mercancía lista. Genere o confirme el traspaso a CEDIS.',
        };
    }
    if (t.estado === 'LISTA_PARA_CARATULA') {
        return {
            label: 'Lista para carátula',
            tono: 'info',
            icon: Package,
            texto: t.entrega_municipal?.municipio_destino
                ? `Destino ${t.entrega_municipal.municipio_destino}. Genere e imprima la carátula.`
                : 'Genere e imprima la carátula municipal.',
        };
    }
    if (t.estado === 'EN_TRASLADO') {
        return {
            label: 'En traslado',
            tono: 'blue',
            icon: Truck,
            texto: 'Mercancía en camino. CEDIS debe confirmar recepción.',
        };
    }
    if (venc?.urgente) {
        return {
            label: 'Plazo',
            tono: 'warning',
            icon: Clock,
            texto: venc.texto,
        };
    }
    return null;
}

export const STORAGE_VISTA_LISTADO = 'control_pedidos.tienda.vista_listado';

export const ORIGEN_TABLERO = {
    CALL_CENTER: 'Call Center',
    BELLAROMA: 'Bellaroma',
    SIN_ORIGEN: 'Sin origen',
};
