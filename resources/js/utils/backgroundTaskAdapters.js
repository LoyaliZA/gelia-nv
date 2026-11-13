import {
    ESTADOS_ACTIVOS,
    ESTADOS_TERMINALES,
} from './tiendanubeImageImportTracker';

export const FASES_CARGA = {
    preparando: 'Preparando archivo',
    subiendo: 'Subiendo archivo',
    archivo_recibido: 'Archivo recibido',
    analizando: 'Analizando',
    esperando_confirmacion: 'Esperando confirmación',
    procesando: 'Procesando',
    completado: 'Completado',
    completado_con_incidencias: 'Completado con incidencias',
    error: 'Error',
};

const FASES_ACTIVAS_CLIENTES = [
    'preparando',
    'subiendo',
    'archivo_recibido',
    'analizando',
    'esperando_confirmacion',
    'procesando',
];

const FASES_TERMINALES_CLIENTES = ['completado', 'completado_con_incidencias', 'error'];

function presentarTiendanube(data) {
    const activo = ESTADOS_ACTIVOS.includes(data.estado);
    const porcentaje = Number.isFinite(Number(data.porcentaje)) ? Number(data.porcentaje) : null;
    return {
        titulo: 'Carga de imágenes',
        etiqueta: data.estado || 'En proceso',
        porcentaje,
        indeterminado: porcentaje == null && activo,
        descripcion: `${data.procesados ?? 0}/${data.total_archivos ?? '—'} · OK ${data.exitosos ?? 0}${(data.fallidos ?? 0) > 0 ? ` · Fallidos ${data.fallidos}` : ''}`,
        error: data.mensaje_error || null,
        activo,
        terminal: ESTADOS_TERMINALES.includes(data.estado),
        href: route('tiendanube.imagenes.index'),
        hrefLabel: 'Imágenes',
    };
}

function presentarClientes(data) {
    const fase = data.fase || 'analizando';
    const transfiriendo = fase === 'subiendo' || fase === 'preparando';
    const porcentaje = data.indeterminado ? null : (Number.isFinite(Number(data.porcentaje)) ? Number(data.porcentaje) : null);
    return {
        titulo: 'Carga de clientes',
        etiqueta: FASES_CARGA[fase] || fase,
        porcentaje,
        indeterminado: porcentaje == null,
        descripcion: data.mensaje
            ? data.mensaje
            : (transfiriendo
                ? 'El archivo todavía se está transfiriendo. Esta página debe seguir abierta.'
                : 'El servidor ya recibió el archivo. El proceso continúa sin dejar esta página abierta.'),
        detalle: data.filas_leidas
            ? `${data.filas_procesadas ?? 0}/${data.filas_leidas} · pendientes ${data.pendientes ?? 0} · errores ${data.errores ?? 0}`
            : null,
        error: data.mensaje || null,
        activo: FASES_ACTIVAS_CLIENTES.includes(fase),
        terminal: FASES_TERMINALES_CLIENTES.includes(fase),
        href: `${route('admin.clientes')}?importacion=${data.id}`,
        hrefLabel: fase === 'esperando_confirmacion' ? 'Revisar' : 'Resultados',
    };
}

export const TASK_ADAPTERS = {
    'tiendanube-imagenes': {
        titulo: 'Carga de imágenes',
        url: (id) => route('tiendanube.imagenes.importar.progreso', id),
        presentar: presentarTiendanube,
        toast(data) {
            if (data.estado === 'completado') {
                return { tipo: 'success', mensaje: `Carga de imágenes #${data.id}: ${data.exitosos}/${data.total_archivos} correctas.` };
            }
            if (data.estado === 'completado_con_incidencias') {
                return { tipo: 'error', mensaje: `Carga de imágenes #${data.id} terminó con incidencias (${data.exitosos ?? 0} correctas, ${data.fallidos ?? 0} fallidas).` };
            }
            return { tipo: 'error', mensaje: data.mensaje_error || `Carga de imágenes #${data.id} falló.` };
        },
    },
    'clientes-importacion': {
        titulo: 'Carga de clientes',
        url: (id) => route('admin.clientes.importaciones.progreso', id),
        descubrir: () => route('admin.clientes.importaciones.activas'),
        presentar: presentarClientes,
        toast(data) {
            const fase = data.fase;
            if (fase === 'completado') {
                return { tipo: 'success', mensaje: `Carga de clientes #${data.id} completada.` };
            }
            if (fase === 'completado_con_incidencias') {
                return { tipo: 'error', mensaje: `Carga de clientes #${data.id} terminó con incidencias.` };
            }
            return { tipo: 'error', mensaje: data.mensaje || `Carga de clientes #${data.id} falló.` };
        },
    },
};

export function presentarLocal(local) {
    const fase = local?.fase || 'subiendo';
    return {
        titulo: local?.titulo || 'Carga de clientes',
        etiqueta: FASES_CARGA[fase] || fase,
        porcentaje: local?.porcentaje ?? null,
        indeterminado: local?.indeterminado ?? local?.porcentaje == null,
        descripcion: local?.descripcion || 'El archivo todavía se está transfiriendo. Esta página debe seguir abierta.',
        detalle: null,
        error: local?.error || null,
        activo: true,
        terminal: false,
        href: null,
        hrefLabel: null,
        local: true,
    };
}
