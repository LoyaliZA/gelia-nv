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

const ETIQUETAS_ESTADO_VENDEDOR = {
    no_activado: 'No activado',
    no_llego: 'No llegó',
    disponible: 'Disponible',
    atendiendo: 'Atendiendo',
    en_retencion: 'En pausa',
    cierre_pendiente: 'Cierre pendiente',
    jornada_cerrada: 'Jornada finalizada',
};

const CLASES_ESTADO_VENDEDOR = {
    no_activado: 'bg-slate-500/15 theme-text-muted',
    no_llego: 'bg-red-500/15 text-red-700 dark:text-red-300',
    disponible: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    atendiendo: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    en_retencion: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    cierre_pendiente: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    jornada_cerrada: 'bg-slate-500/15 theme-text-muted',
};

export function etiquetaEstadoVendedor(estado) {
    if (!estado) return '—';
    return ETIQUETAS_ESTADO_VENDEDOR[estado] || estado;
}

export function inicialesNombre(nombre) {
    if (!nombre) return '?';
    const partes = nombre.trim().split(/\s+/).filter(Boolean);
    if (partes.length === 0) return '?';
    if (partes.length === 1) return partes[0].slice(0, 2).toUpperCase();
    return `${partes[0][0]}${partes[partes.length - 1][0]}`.toUpperCase();
}

export function claseBadgeEstadoVendedor(estado) {
    return CLASES_ESTADO_VENDEDOR[estado] || 'bg-slate-500/15 theme-text-muted';
}

export function mensajeMiAtencion(estadoVendedor) {
    switch (estadoVendedor) {
        case 'no_activado':
            return 'Debes esperar a que gerencia active tu jornada para comenzar a recibir turnos.';
        case 'no_llego':
            return 'Gerencia registró que no llegaste hoy. Contacta a tu supervisor si hay un error.';
        case 'disponible':
            return 'Estás disponible. Recibirás una alerta cuando se te asigne un cliente.';
        case 'atendiendo':
            return null;
        case 'en_retencion':
            return 'Estás en pausa asignada por gerencia. No recibirás turnos hasta que finalice.';
        case 'cierre_pendiente':
            return 'Tu jornada está en cierre. Termina la atención actual para finalizar.';
        case 'jornada_cerrada':
            return 'Tu jornada de hoy finalizó. Aquí puedes consultar tu estado hasta que gerencia te reactive.';
        default:
            return 'Estado no disponible. Espera a que gerencia actualice tu jornada.';
    }
}

export function esEstadoVendedorConocido(estadoVendedor) {
    return Boolean(estadoVendedor && ETIQUETAS_ESTADO_VENDEDOR[estadoVendedor]);
}

export function mostrarBandejaSinTurno(estadoVendedor) {
    return estadoVendedor === 'disponible';
}

export function formatearUltimaActualizacion(iso) {
    if (!iso) return '—';
    const fecha = new Date(iso);
    if (!Number.isFinite(fecha.getTime())) return '—';

    return fecha.toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

const ETIQUETAS_ACCION_EQUIPO = {
    activar: 'Activar',
    no_llego: 'No llegó',
    desactivar: 'Desactivar',
    retencion_iniciar: 'Pausar',
    retencion_finalizar: 'Finalizar pausa',
    pausa_iniciar: 'Pausar',
    pausa_finalizar: 'Finalizar pausa',
    cerrar_jornada: 'Cerrar jornada',
    reactivar: 'Reactivar',
    cancelar_cierre_pendiente: 'Cancelar cierre pendiente',
};

export function etiquetaAccionEquipo(accion) {
    return ETIQUETAS_ACCION_EQUIPO[accion] || accion;
}

export function esAccionPeligrosaEquipo(accion) {
    return accion === 'desactivar' || accion === 'cerrar_jornada' || accion === 'no_llego';
}

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

export function cronometroDesdeEstado(estado) {
    if (estado?.cronometro) {
        return {
            etiqueta: estado.cronometro.etiqueta,
            referenciaAt: estado.cronometro.referencia_at,
            modo: estado.cronometro.modo,
        };
    }

    return referenciaCronometro(estado);
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

export function puedeReabrirSucursal(estado, permisos) {
    return Boolean(
        permisos?.cerrar_sucursal
        && estado?.sucursal_dia?.acepta_altas === false
        && estado?.sucursal_dia?.cierre_manual_at,
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
            return 'La sucursal ya no acepta altas nuevas (cierre manual de gerencia). Puedes reabrirla desde esta pantalla.';
        }
        if (estado?.antes_de_apertura && estado?.horario_apertura?.hora_apertura) {
            return `La sucursal abre a las ${estado.horario_apertura.hora_apertura}; los turnos se podrán registrar a partir de esa hora.`;
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
