export const REFRESCO_OPERACION_MS = 30000;

const ETIQUETAS_JORNADA = {
    CERRADA: 'Cerrada',
    ABIERTA: 'Abierta',
    CERRADA_CON_ATENCION: 'Cerrada con atención',
};

const ETIQUETAS_ACTIVIDAD = {
    disponible: 'Disponible',
    en_pausa: 'En pausa',
    en_atencion: 'En atención',
};

export function etiquetaJornada(estado) {
    return ETIQUETAS_JORNADA[estado] || 'Sin jornada';
}

export function etiquetaActividad(actividad) {
    if (!actividad) return '—';
    return ETIQUETAS_ACTIVIDAD[actividad] || actividad;
}

export function claseBadgeJornada(estado) {
    if (estado === 'ABIERTA') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (estado === 'CERRADA_CON_ATENCION') return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    return 'bg-slate-500/15 theme-text-muted';
}

export function claseBadgeActividad(actividad) {
    if (actividad === 'disponible') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (actividad === 'en_pausa') return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    if (actividad === 'en_atencion') return 'bg-sky-500/15 text-sky-700 dark:text-sky-300';
    return 'bg-slate-500/15 theme-text-muted';
}

export function referenciaCronometro(estado) {
    const jornada = estado?.jornada;
    const intervalo = estado?.intervalo;

    if (intervalo?.inicio_at && estado?.actividad === 'en_pausa') {
        return { etiqueta: 'Tiempo en pausa', referenciaAt: intervalo.inicio_at, modo: 'transcurrido' };
    }

    if (jornada?.estado === 'ABIERTA' && intervalo?.inicio_at && estado?.actividad === 'disponible') {
        return { etiqueta: 'Tiempo disponible', referenciaAt: intervalo.inicio_at, modo: 'transcurrido' };
    }

    if (jornada?.apertura_at && jornada?.estado === 'ABIERTA') {
        return { etiqueta: 'Jornada abierta', referenciaAt: jornada.apertura_at, modo: 'transcurrido' };
    }

    if (jornada?.cierre_at && jornada?.estado === 'CERRADA_CON_ATENCION') {
        return { etiqueta: 'Jornada cerrada', referenciaAt: jornada.cierre_at, modo: 'transcurrido' };
    }

    return null;
}

export function puedeAbrirJornada(estado, permisos) {
    return Boolean(permisos?.jornada_abrir && !estado?.jornada);
}

export function puedeCerrarJornada(estado, permisos) {
    return Boolean(permisos?.jornada_cerrar && estado?.jornada?.estado === 'ABIERTA');
}

export function puedeIniciarPausa(estado, permisos) {
    return Boolean(
        permisos?.pausa
        && estado?.jornada?.estado === 'ABIERTA'
        && estado?.actividad === 'disponible',
    );
}

export function puedeFinalizarPausa(estado, permisos) {
    return Boolean(
        permisos?.pausa
        && estado?.jornada?.estado === 'ABIERTA'
        && estado?.actividad === 'en_pausa',
    );
}

export function puedeCerrarSucursal(estado, permisos) {
    return Boolean(
        permisos?.cerrar_sucursal
        && estado?.sucursal_dia?.acepta_altas !== false,
    );
}

export function puedeAmpliarHorario(estado, permisos) {
    return Boolean(permisos?.ampliar);
}

export function puedeConfigurarHorarioCierre(permisos) {
    return Boolean(permisos?.ampliar);
}

export function mensajeAvisoSucursal(estado) {
    const dia = estado?.sucursal_dia;
    if (!dia) return null;

    if (dia.acepta_altas === false) {
        if (dia.cierre_manual_at) {
            return 'La sucursal ya no acepta altas nuevas (cierre manual de gerencia).';
        }
        if (estado?.cierre_programado?.vencido) {
            return 'La sucursal ya no acepta altas nuevas (horario de cierre alcanzado).';
        }
        return 'La sucursal ya no acepta altas nuevas.';
    }

    if (dia.ampliacion_hasta_at) {
        return 'Horario ampliado por gerencia; el cierre automático de hoy queda desplazado.';
    }

    if (dia.cierre_automatico_invalidado) {
        return 'Gerencia intervino hoy; el cierre automático queda invalidado hasta nueva ampliación o cierre manual.';
    }

    return null;
}

export function mensajeErrorOperacion(err, accion = 'operación') {
    const status = err?.response?.status;
    const data = err?.response?.data;

    if (status === 422) {
        const errores = data?.errors;
        if (errores?.version) {
            return errores.version[0] || 'Otro terminal modificó el estado. Actualiza e intenta de nuevo.';
        }
        if (errores && typeof errores === 'object') {
            const primero = Object.values(errores).flat()[0];
            if (primero) return primero;
        }
        return data?.message || `Revisa los datos de la ${accion}.`;
    }

    if (status === 403) {
        return 'No tienes permiso para esta acción.';
    }

    if (!err?.response) {
        return 'No se pudo conectar. Verifica la red e intenta de nuevo.';
    }

    return data?.message || `No se pudo completar la ${accion}.`;
}

export function esConflictoVersion(err) {
    return Boolean(err?.response?.data?.errors?.version);
}

export function valorDatetimeLocalDesdeIso(iso, zonaFallback = null) {
    if (!iso) return '';
    const fecha = new Date(iso);
    if (!Number.isFinite(fecha.getTime())) return '';

    const offsetMs = fecha.getTimezoneOffset() * 60000;
    return new Date(fecha.getTime() - offsetMs).toISOString().slice(0, 16);
}

export function isoDesdeDatetimeLocal(valor) {
    if (!valor) return null;
    const fecha = new Date(valor);
    if (!Number.isFinite(fecha.getTime())) return null;
    return fecha.toISOString();
}
