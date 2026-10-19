/** Reglas de presentación de Gelia IA: qué se muestra según lo que informa el servidor. */

/** Trabajo en curso. El servidor no informa pasos intermedios: solo se describe la petición abierta. */
export const TRABAJO = {
    subida: 'Subiendo archivos…',
    consulta: 'Esperando respuesta de GELIA…',
    recomendacion: 'Preparando la ficha del perfume…',
    accion: 'Ejecutando la acción confirmada…',
    cancelacion: 'Cancelando la propuesta…',
};

/** Segundos tras los que se explica que una consulta larga es normal. */
export const AVISO_ESPERA_SEGUNDOS = 20;

/**
 * Estados de la tarjeta de propuesta.
 * `recuperable` ofrece preparar una nueva propuesta (nunca reintenta la misma).
 */
export const ESTADO_PROPUESTA = {
    pendiente: { etiqueta: 'Pendiente de confirmar', tono: 'info', icono: 'reloj' },
    ejecutando: { etiqueta: 'Ejecutando…', tono: 'info', icono: 'carga' },
    cancelando: { etiqueta: 'Cancelando…', tono: 'neutro', icono: 'carga' },
    ejecutada: { etiqueta: 'Confirmada', tono: 'exito', icono: 'ok' },
    procesando: { etiqueta: 'En procesamiento', tono: 'info', icono: 'carga' },
    cancelada: { etiqueta: 'Cancelada · no se ejecutó', tono: 'neutro', icono: 'cancelada', recuperable: true },
    vencida: { etiqueta: 'Vencida · no se ejecutó', tono: 'aviso', icono: 'reloj', recuperable: true },
    usada: { etiqueta: 'Ya utilizada', tono: 'neutro', icono: 'ok' },
    fallida: { etiqueta: 'No se completó', tono: 'error', icono: 'error', recuperable: true },
    inexistente: { etiqueta: 'No disponible', tono: 'aviso', icono: 'error', recuperable: true },
};

/** Traduce `estado_propuesta` del servidor al estado de la tarjeta. */
export function estadoTarjetaDesdeServidor(estado) {
    const mapa = {
        ejecutada: 'usada',
        procesando: 'procesando',
        ocupada: 'procesando',
        cancelada: 'cancelada',
        vencida: 'vencida',
        fallida: 'fallida',
        inexistente: 'inexistente',
    };
    return mapa[estado] || 'fallida';
}

export function textoConfirmar(propuesta) {
    return propuesta?.detalle?.confirmar || 'Confirmar acción';
}

/** Mensaje para el compositor al preparar de nuevo; la persona usuaria decide si lo envía. */
export function textoPrepararDeNuevo(propuesta) {
    const resumen = propuesta?.resumen_corto || propuesta?.detalle?.operacion || 'la acción anterior';
    return `Prepara de nuevo: ${resumen}`;
}

const KIND_ARCHIVO = {
    costos: 'Costos',
    existencias: 'Existencias',
    inventarios: 'Inventarios',
    precios: 'Precios',
};

export function etiquetaArchivo(kind) {
    return KIND_ARCHIVO[kind] ? `Hoja de cálculo · ${KIND_ARCHIVO[kind]}` : 'Hoja de cálculo';
}

export function formatearHora(iso) {
    if (!iso) return '';
    const fecha = new Date(iso);
    if (Number.isNaN(fecha.getTime())) return '';
    return new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit' }).format(fecha);
}

export function formatearNumero(n) {
    return new Intl.NumberFormat('es-MX').format(Number(n) || 0);
}

/** Resultado real de una acción ejecutada. */
export function resultadoDesdeReporte(reporte = {}) {
    if (reporte.estado === 'en_cola') {
        return {
            tono: 'info',
            etiqueta: 'En cola',
            nota: 'La importación aún no se aplica: se procesa en segundo plano. Revisa el resultado por fila en Almacenes → Importaciones.',
        };
    }
    if (reporte.estado === 'completado') {
        return { tono: 'exito', etiqueta: 'Completado', nota: '' };
    }
    return { tono: 'neutro', etiqueta: 'Terminado', nota: '' };
}

/** Mensaje de error legible para respuestas HTTP de Gelia IA. */
export function mensajeErrorHttp(err, porDefecto) {
    const data = err?.response?.data;
    if (data?.errors && typeof data.errors === 'object') {
        const primero = Object.values(data.errors).flat().find((v) => typeof v === 'string');
        if (primero) return primero;
    }
    if (typeof data?.message === 'string' && data.message) return data.message;
    if (err?.response?.status === 429) return 'Hiciste demasiadas solicitudes seguidas. Espera un minuto y vuelve a intentarlo.';
    if (!err?.response) return 'No hay conexión con el servidor. Revisa tu red y vuelve a intentarlo.';
    return porDefecto;
}
