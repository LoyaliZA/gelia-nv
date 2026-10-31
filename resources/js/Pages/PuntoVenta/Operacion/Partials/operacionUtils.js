import { GELIA_ESTADO_VIVO_TONO } from '../../../../utils/geliaTheme';

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
    en_espera: 'Pre-disponible',
    atendiendo: 'Atendiendo',
    en_retencion: 'En pausa',
    cierre_pendiente: 'Cierre pendiente',
    jornada_cerrada: 'Jornada finalizada',
};

const TONO_ESTADO_VENDEDOR = {
    no_activado: 'neutro',
    no_llego: 'error',
    disponible: 'exito',
    en_espera: 'aviso',
    atendiendo: 'atendiendo',
    en_retencion: 'pausa',
    cierre_pendiente: 'aviso',
    jornada_cerrada: 'neutro',
};

const CLASE_TONO_EQUIPO = {
    atendiendo: 'gelia-estado-equipo--atendiendo',
    prorroga: 'gelia-estado-equipo--prorroga',
    pausa: 'gelia-estado-equipo--pausa',
};

function claseEstadoVivo(tono) {
    const clase = CLASE_TONO_EQUIPO[tono] || GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;

    return `${clase} gelia-estado-vivo gelia-estado-vivo--compacto`;
}

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

export function tonoEstadoVendedor(estado, prorrogaActiva = false) {
    if (estado === 'atendiendo' && prorrogaActiva) return 'prorroga';

    return TONO_ESTADO_VENDEDOR[estado] || 'neutro';
}

export function claseBadgeEstadoVendedor(estado, prorrogaActiva = false) {
    return claseEstadoVivo(tonoEstadoVendedor(estado, prorrogaActiva));
}

export function esAvisoDisponibilidad(estado) {
    return estado === 'en_retencion' || estado === 'en_espera';
}

export function mensajeAvisoDisponibilidad(estado, accion) {
    if (estado === 'en_retencion') {
        const cierre = accion === 'reatencion'
            ? 'La reatención manual sí se puede confirmar.'
            : 'La transferencia sí se puede confirmar.';

        return `Está en pausa. La fila automática no asigna clientes hasta que termine. ${cierre}`;
    }

    if (estado === 'en_espera') {
        const cierre = accion === 'reatencion'
            ? 'La reatención manual sí se puede confirmar.'
            : 'La transferencia sí se puede confirmar.';

        return `Pre-disponible. Volverá a la cola al terminar la cuenta. ${cierre}`;
    }

    return '';
}

export function mensajeMiAtencion(estadoVendedor) {
    switch (estadoVendedor) {
        case 'no_activado':
            return 'Debes esperar a que gerencia active tu jornada para comenzar a recibir turnos.';
        case 'no_llego':
            return 'Gerencia registró que no llegaste hoy. Contacta a tu supervisor si hay un error.';
        case 'disponible':
            return 'Estás disponible. Recibirás una alerta cuando se te asigne un cliente.';
        case 'en_espera':
            return 'Estás pre-disponible. Volverás a la cola al terminar la cuenta regresiva.';
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

const ESTADOS_VENDEDOR_INACTIVOS_GERENCIA = new Set(['no_activado', 'no_llego', 'jornada_cerrada']);

const PRIORIDAD_ORDEN_EQUIPO_ACTIVO_GERENCIA = {
    atendiendo: 0,
    disponible: 1,
    en_espera: 2,
    en_retencion: 3,
    cierre_pendiente: 4,
};

const PRIORIDAD_ORDEN_EQUIPO_INACTIVO_GERENCIA = {
    no_activado: 0,
    no_llego: 1,
    jornada_cerrada: 2,
};

export function esVendedorEquipoInactivoGerencia(estadoVendedor) {
    return ESTADOS_VENDEDOR_INACTIVOS_GERENCIA.has(estadoVendedor);
}

function prioridadMiembroEquipoGerencia(miembro, inactivo) {
    const estado = miembro?.estado_vendedor;
    const mapa = inactivo ? PRIORIDAD_ORDEN_EQUIPO_INACTIVO_GERENCIA : PRIORIDAD_ORDEN_EQUIPO_ACTIVO_GERENCIA;
    return mapa[estado] ?? 99;
}

function compararMiembrosEquipoGerencia(a, b, inactivo) {
    const delta = prioridadMiembroEquipoGerencia(a, inactivo) - prioridadMiembroEquipoGerencia(b, inactivo);
    if (delta !== 0) return delta;
    return String(a?.nombre || '').localeCompare(String(b?.nombre || ''), 'es', { sensitivity: 'base' });
}

/**
 * Separa el equipo en activos (jornada operativa) e inactivos, ordenados para la vista de gerencia.
 *
 * @returns {{ activos: array, inactivos: array }}
 */
export function particionarEquipoGerencia(equipo = []) {
    const activos = [];
    const inactivos = [];

    for (const miembro of equipo) {
        if (esVendedorEquipoInactivoGerencia(miembro?.estado_vendedor)) {
            inactivos.push(miembro);
        } else {
            activos.push(miembro);
        }
    }

    activos.sort((a, b) => compararMiembrosEquipoGerencia(a, b, false));
    inactivos.sort((a, b) => compararMiembrosEquipoGerencia(a, b, true));

    return { activos, inactivos };
}

/** Lapso sin activar ni usar la sección antes de contraerla, si ya hay gente activa. */
export const INACTIVIDAD_SECCION_INACTIVOS_MS = 60_000;

/**
 * Sección de inactivos: abierta si nadie está activo; si ya hay activos, se contrae
 * tras un minuto sin activar o usar el apartado.
 *
 * @returns {{ abierta: boolean, reiniciarTemporizador: boolean, cancelarTemporizador: boolean }}
 */
export function resolverSeccionInactivos({
    abierta,
    activosAntes,
    activosAhora,
    evento,
}) {
    if (evento === 'vencio') {
        if (activosAhora === 0) {
            return { abierta: true, reiniciarTemporizador: false, cancelarTemporizador: true };
        }

        return { abierta: false, reiniciarTemporizador: false, cancelarTemporizador: true };
    }

    if (evento === 'alternar') {
        const siguiente = !abierta;
        const corre = siguiente && activosAhora > 0;

        return {
            abierta: siguiente,
            reiniciarTemporizador: corre,
            cancelarTemporizador: !corre,
        };
    }

    if (evento === 'actividad') {
        if (!abierta || activosAhora === 0) {
            return { abierta, reiniciarTemporizador: false, cancelarTemporizador: false };
        }

        return { abierta: true, reiniciarTemporizador: true, cancelarTemporizador: false };
    }

    if (activosAhora === 0) {
        return { abierta: true, reiniciarTemporizador: false, cancelarTemporizador: true };
    }

    if (activosAntes === 0 || activosAhora > activosAntes) {
        return { abierta: true, reiniciarTemporizador: true, cancelarTemporizador: false };
    }

    return { abierta, reiniciarTemporizador: false, cancelarTemporizador: false };
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
    if (estado === 'ABIERTA') return claseEstadoVivo('exito');
    if (estado === 'CERRADA_CON_ATENCION') return claseEstadoVivo('aviso');
    return claseEstadoVivo('neutro');
}

export function claseBadgeActividad(actividad) {
    if (actividad === 'disponible') return claseEstadoVivo('exito');
    if (actividad === 'en_pausa') return claseEstadoVivo('aviso');
    if (actividad === 'en_atencion') return claseEstadoVivo('info');
    return claseEstadoVivo('neutro');
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

export function puedeAbrirSucursalManualmente(estado, permisos) {
    return Boolean(
        permisos?.cerrar_sucursal
        && estado?.sucursal_dia?.acepta_altas === false
        && !estado?.sucursal_dia?.cierre_manual_at,
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
            return `La sucursal abre a las ${estado.horario_apertura.hora_apertura}; puedes abrir la sucursal manualmente si gerencia debe prepararse antes.`;
        }
        if (estado?.cierre_programado?.vencido) {
            return 'La sucursal ya no acepta altas nuevas (horario de cierre alcanzado).';
        }
        return 'La sucursal ya no acepta altas nuevas.';
    }

    if (dia.origen_apertura === 'manual' && dia.apertura_manual_at) {
        return 'La sucursal está abierta de forma manual. El inicio automático de hoy ya no se ejecuta.';
    }

    if (dia.origen_apertura === 'automatica') {
        return 'La sucursal está abierta de forma automática según el horario configurado.';
    }

    if (dia.ampliacion_hasta_at) {
        return 'Horario ampliado por gerencia; el cierre automático de hoy queda desplazado.';
    }

    if (dia.cierre_automatico_invalidado) {
        return 'Gerencia intervino hoy; el cierre automático queda invalidado hasta nueva ampliación o cierre manual.';
    }

    return null;
}

export function tipoAvisoSucursal(estado) {
    if (estado?.sucursal_dia?.acepta_altas === false) {
        return 'warning';
    }

    return 'info';
}

/**
 * @returns {{ mensaje: string, tipo: 'info' | 'warning' } | null}
 */
export function avisoSucursalOperacion(estado) {
    const mensaje = mensajeAvisoSucursal(estado);
    if (!mensaje) return null;

    return {
        mensaje,
        tipo: tipoAvisoSucursal(estado),
    };
}

export function firmaAvisoSucursal(estado) {
    const aviso = avisoSucursalOperacion(estado);
    if (!aviso) return null;

    return `${aviso.tipo}:${aviso.mensaje}`;
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

export function resumenHorarioOperativo(estado) {
    const horario = estado?.horario_cierre || {};
    const apertura = horario.hora_apertura || 'Sin restricción';
    const cierre = horario.hora_cierre || '—';
    const partes = [`${apertura}–${cierre}`];
    const ampliacion = estado?.sucursal_dia?.ampliacion_hasta_at;
    if (ampliacion) {
        const fecha = new Date(ampliacion);
        const hora = Number.isFinite(fecha.getTime())
            ? fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
            : String(ampliacion);
        partes.push(`Ampliación hasta ${hora}`);
    }
    return partes.join(' · ');
}

export const PERMISO_PDV_PLAZOS_TURNOS = 'pdv.operacion.plazos_turnos';

export function puedeGestionarPlazosTurnosPdv({ capacidades = null, auth = null } = {}) {
    if (capacidades?.plazos_turnos) return true;
    return auth?.user?.permissions?.includes(PERMISO_PDV_PLAZOS_TURNOS) ?? false;
}
