const ETIQUETAS_POR_CLAVE = {
    'session.lifetime': 'Tiempo de inactividad (minutos)',
    'session.expire_on_close': 'Expirar al cerrar navegador',
    'sesiones.jornada_cierre_activo': 'Cierre automático al fin de jornada',
    'sesiones.jornada_hora_fin': 'Hora de fin de jornada',
    'sesiones.jornada_zona_horaria': 'Zona horaria de jornada',
    'webauthn.enabled': 'Passkeys habilitadas',
    'webauthn.relying_party.name': 'Nombre del sitio (RP Name)',
    'webauthn.relying_party.id': 'Dominio del sitio (RP ID)',
    'webauthn.origins': 'Orígenes permitidos',
    'control_pedidos.auxiliar.ui_simplificada': 'Interfaz simplificada del auxiliar',
    'control_pedidos.pagos.tolerancia_mxn': 'Tolerancia de pagos (MXN)',
    'control_pedidos.envios.detalle_cajas': 'Detalle de costos por caja',
    'control_pedidos.envios.cedis_captura_costo': 'Captura de costo en CEDIS',
    'control_pedidos.envios.moneda': 'Moneda de envíos',
    'control_pedidos.envios.reexpedicion_alcance': 'Alcance de reexpedición',
    'control_pedidos.envios.editar_tras_recoleccion': 'Editar caja tras recolección',
    'control_pedidos.envios.precision_decimales': 'Decimales en costos de envío',
    'control_pedidos.envios.requisitos_paqueteria': 'Requisitos por paquetería',
    'control_pedidos.cancelacion_operativa': 'Cancelación operativa',
    'control_pedidos.preparacion_tienda': 'Preparación en tienda',
    'control_pedidos.preparacion.dias_resguardo': 'Días de resguardo en tienda',
    'control_pedidos.preparacion.recordatorio_hora_local': 'Hora del recordatorio de resguardo',
    'control_pedidos.preparacion.zona_horaria': 'Zona horaria de preparación',
    'control_pedidos.ventas.formulario_progresivo': 'Formulario progresivo de ventas',
    'control_pedidos.ventas.autosave_debounce_ms': 'Retardo de autoguardado (ms)',
    'control_pedidos.ventas.max_reintentos_autosave': 'Reintentos de autoguardado',
    'control_pedidos.plazos_retraso': 'Plazos de retraso de pedidos',
    'pdv.resguardos.registro_manual': 'Registro manual de resguardos en sucursal',
};

export const ETIQUETAS_CATEGORIA = {
    envios: 'Envíos',
    pagos: 'Pagos',
    auxiliar: 'Auxiliar',
    preparacion: 'Preparación',
    preparacion_tienda: 'Preparación tienda',
    cancelacion_operativa: 'Cancelaciones',
    ventas: 'Ventas',
    tienda: 'Tienda',
    plazos: 'Plazos',
    plazos_retraso: 'Plazos de retraso',
    direccion: 'Dirección',
    espera_pago: 'Espera de pago',
    mail: 'Correo',
    session: 'Sesión',
    sesiones: 'Sesiones',
    webauthn: 'Passkeys',
    general: 'General',
};

export const esBooleanoVerdadero = (valor) => valor === true || valor === 'true' || valor === '1' || valor === 1;

export function humanizarClave(clave) {
    const segmentos = String(clave || '').split('.');
    const relevantes = segmentos.length >= 2 ? segmentos.slice(-2) : segmentos;

    return relevantes
        .join(' ')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function limpiarDescripcionComoTitulo(descripcion) {
    return String(descripcion || '')
        .trim()
        .replace(/^Feature flag\s+/i, '')
        .replace(/\s*\(Control Pedidos\)\s*$/i, '')
        .trim();
}

export function tituloConfiguracion(config) {
    const clave = config?.clave || '';
    if (ETIQUETAS_POR_CLAVE[clave]) {
        return ETIQUETAS_POR_CLAVE[clave];
    }

    const descripcion = (config?.descripcion || '').trim();
    if (descripcion) {
        return limpiarDescripcionComoTitulo(descripcion);
    }

    return humanizarClave(clave);
}

export function tituloUsaDescripcion(config) {
    const clave = config?.clave || '';
    if (ETIQUETAS_POR_CLAVE[clave]) {
        return false;
    }

    return !!(config?.descripcion || '').trim();
}

export function categoriaDeClave(clave) {
    const partes = String(clave || '').split('.');
    if (partes.length < 2) {
        return 'general';
    }

    if (partes[0] === 'control_pedidos') {
        return partes[1] || 'general';
    }

    return partes[0] || 'general';
}

export function etiquetaCategoria(categoria) {
    return ETIQUETAS_CATEGORIA[categoria] || humanizarClave(categoria);
}

export function agruparPorCategoria(configuraciones) {
    const grupos = {};

    configuraciones.forEach((config) => {
        const categoria = categoriaDeClave(config.clave);
        if (!grupos[categoria]) {
            grupos[categoria] = [];
        }
        grupos[categoria].push(config);
    });

    return Object.fromEntries(
        Object.entries(grupos).sort(([a], [b]) => a.localeCompare(b, 'es')),
    );
}

export function filtrarConfiguraciones(configuraciones, busqueda) {
    const termino = busqueda.trim().toLowerCase();
    if (!termino) {
        return configuraciones;
    }

    return configuraciones.filter((config) => {
        const titulo = tituloConfiguracion(config).toLowerCase();
        const clave = (config.clave || '').toLowerCase();
        const descripcion = (config.descripcion || '').toLowerCase();

        return titulo.includes(termino) || clave.includes(termino) || descripcion.includes(termino);
    });
}

export function esValorSecreto(config) {
    return config.tipo === 'string' && /password|secret|token/.test((config.clave || '').toLowerCase());
}

export function formatearValorJson(valor) {
    try {
        return JSON.stringify(JSON.parse(valor), null, 2);
    } catch {
        return String(valor ?? '');
    }
}

export function resumenValorJson(valor) {
    const texto = String(valor ?? '').trim();
    if (!texto) {
        return 'Vacío';
    }

    try {
        const parsed = JSON.parse(texto);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            const claves = Object.keys(parsed);
            if (claves.length === 0) {
                return 'Objeto vacío';
            }
            const activo = parsed.activo ?? parsed.enabled ?? parsed.habilitado;
            if (typeof activo === 'boolean') {
                return activo ? 'Configuración activa' : 'Configuración inactiva';
            }
            return `${claves.length} campo${claves.length === 1 ? '' : 's'}`;
        }
    } catch {
        // ponytail: resumen legible sin bloquear por JSON inválido
    }

    return texto.length > 48 ? `${texto.slice(0, 45)}…` : texto;
}
