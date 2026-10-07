export const MESES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

export function dinero(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    const numero = Number(valor);
    if (Number.isNaN(numero)) return '—';
    return numero.toLocaleString('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2 });
}

export const ETIQUETAS_RESULTADO = {
    sin_cambio: 'Se mantiene',
    ascenso: 'Ascenso',
    descenso: 'Descenso',
    no_participa: 'No participa',
    bloqueado: 'Bloqueado',
    revision_pendiente: 'Revisión pendiente',
    inactividad: 'Inactividad',
};

export const TONO_RESULTADO = {
    sin_cambio: 'info',
    ascenso: 'exito',
    descenso: 'aviso',
    no_participa: 'neutro',
    bloqueado: 'error',
    revision_pendiente: 'aviso',
    inactividad: 'aviso',
};

export const OPCIONES_FILTRO_RESULTADO = [
    { id: 'ascenso', label: 'Ascenso' },
    { id: 'descenso', label: 'Descenso' },
    { id: 'sin_cambio', label: 'Se mantiene' },
    { id: 'inactividad', label: 'Inactividad' },
    { id: 'bloqueado', label: 'Bloqueado' },
    { id: 'no_participa', label: 'No participa' },
    { id: 'revision_pendiente', label: 'Revisión pendiente' },
];

export const TONO_GRAVEDAD_INCIDENCIA = {
    bloquea: 'error',
    aviso: 'aviso',
};

export const TONO_ESTADO_INCIDENCIA = {
    abierta: 'aviso',
    resuelta: 'exito',
};

export const TONO_ESTADO_DOCUMENTO = {
    activo: 'exito',
    cancelado: 'neutro',
    pendiente_de_vinculacion: 'aviso',
    pendiente_revision: 'aviso',
};

/** Tono semántico para badges `gelia-estado-vivo` en la previsualización de importación. */
export const TONO_RESULTADO_IMPORTACION = {
    alta: 'exito',
    identico: 'neutro',
    revision: 'aviso',
    pendiente: 'aviso',
    incidencia: 'error',
    excluido: 'neutro',
    historial_alta: 'info',
    historial_identico: 'info',
    historial_revision: 'info',
    historial_pendiente: 'info',
    historial_excluido: 'neutro',
    futuro_alta: 'aviso',
    futuro_identico: 'aviso',
    futuro_revision: 'aviso',
    futuro_pendiente: 'aviso',
    futuro_excluido: 'neutro',
};

export function tonoResultadoImportacion(codigo) {
    return TONO_RESULTADO_IMPORTACION[codigo] || 'neutro';
}

export const RESULTADOS_IMPORTACION = {
    alta: 'Nuevo documento',
    identico: 'Sin cambios',
    revision: 'Revisión',
    pendiente: 'Pendiente',
    incidencia: 'Error',
    excluido: 'Excluido',
    historial_alta: 'Historial · nuevo',
    historial_identico: 'Historial · sin cambio',
    historial_revision: 'Historial · revisión',
    historial_pendiente: 'Historial · pendiente',
    historial_excluido: 'Historial · excluido',
    futuro_alta: 'Futuro · nuevo',
    futuro_identico: 'Futuro · sin cambio',
    futuro_revision: 'Futuro · revisión',
    futuro_pendiente: 'Futuro · pendiente',
    futuro_excluido: 'Futuro · excluido',
};

export function etiquetaTipoDocumento(tipo) {
    if (tipo === 'remision') return 'Remisión';
    if (tipo === 'devolucion') return 'Devolución';
    return tipo || '—';
}

export const ETIQUETAS_ESTADO_DOCUMENTO = {
    activo: 'Activo',
    cancelado: 'Cancelado',
    pendiente_de_vinculacion: 'Pendiente de vínculo',
    pendiente_revision: 'Pendiente de revisión',
};
