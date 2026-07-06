export const DIAS_TURNO = [
    { clave: 'lunes', nombre: 'Lunes' },
    { clave: 'martes', nombre: 'Martes' },
    { clave: 'miercoles', nombre: 'Miércoles' },
    { clave: 'jueves', nombre: 'Jueves' },
    { clave: 'viernes', nombre: 'Viernes' },
    { clave: 'sabado', nombre: 'Sábado' },
    { clave: 'domingo', nombre: 'Domingo' },
];

const MAPA_CLAVES_LEGACY = {
    0: 'domingo',
    1: 'lunes',
    2: 'martes',
    3: 'miercoles',
    4: 'jueves',
    5: 'viernes',
    6: 'sabado',
    '0': 'domingo',
    '1': 'lunes',
    '2': 'martes',
    '3': 'miercoles',
    '4': 'jueves',
    '5': 'viernes',
    '6': 'sabado',
};

export function matrizHorarioDefecto() {
    return {
        lunes: { entrada: '09:00', salida: '18:00', horas: 9, descanso: false },
        martes: { entrada: '09:00', salida: '18:00', horas: 9, descanso: false },
        miercoles: { entrada: '09:00', salida: '18:00', horas: 9, descanso: false },
        jueves: { entrada: '09:00', salida: '18:00', horas: 9, descanso: false },
        viernes: { entrada: '09:00', salida: '18:00', horas: 9, descanso: false },
        sabado: { entrada: '09:00', salida: '14:00', horas: 5, descanso: false },
        domingo: { entrada: '00:00', salida: '00:00', horas: 0, descanso: true },
    };
}

function normalizarHora(hora) {
    if (!hora) return '00:00';
    const valor = String(hora).trim();
    return valor.length >= 8 ? valor.slice(0, 5) : valor;
}

function resolverClaveDia(clave) {
    const texto = String(clave).toLowerCase();
    if (DIAS_TURNO.some((dia) => dia.clave === texto)) {
        return texto;
    }
    return MAPA_CLAVES_LEGACY[clave] ?? MAPA_CLAVES_LEGACY[texto] ?? null;
}

export function normalizarMatrizHorario(matriz) {
    const defecto = matrizHorarioDefecto();

    if (!matriz || typeof matriz !== 'object') {
        return defecto;
    }

    const normalizada = {};

    Object.entries(matriz).forEach(([clave, config]) => {
        const dia = resolverClaveDia(clave);
        if (!dia || !config || typeof config !== 'object') {
            return;
        }

        const descanso = !!config.descanso;
        const entrada = normalizarHora(config.entrada ?? defecto[dia].entrada);
        const salida = normalizarHora(config.salida ?? defecto[dia].salida);

        normalizada[dia] = {
            entrada,
            salida,
            horas: descanso ? 0 : Number(config.horas ?? defecto[dia].horas),
            descanso,
        };
    });

    DIAS_TURNO.forEach(({ clave }) => {
        if (!normalizada[clave]) {
            normalizada[clave] = { ...defecto[clave] };
        }
    });

    return normalizada;
}

export function diaEspanolDesdeFecha(fechaIso) {
    const mapa = {
        0: 'domingo',
        1: 'lunes',
        2: 'martes',
        3: 'miercoles',
        4: 'jueves',
        5: 'viernes',
        6: 'sabado',
    };
    const fecha = new Date(`${fechaIso}T12:00:00`);
    return mapa[fecha.getDay()];
}

export function nombreDiaDesdeFecha(fechaIso) {
    const dia = diaEspanolDesdeFecha(fechaIso);
    return DIAS_TURNO.find((d) => d.clave === dia)?.nombre ?? dia;
}

/**
 * Horario del catálogo de turno para una fecha (día de la semana).
 */
export function horarioTurnoParaFecha(turno, fechaIso, fallbackHoras = 8) {
    const diaEspanol = diaEspanolDesdeFecha(fechaIso || new Date().toISOString().slice(0, 10));
    const matriz = turno?.matriz_horario ? normalizarMatrizHorario(turno.matriz_horario) : null;

    if (!matriz) {
        return {
            entrada: null,
            salida: null,
            horas: Number(fallbackHoras) || 8,
            descanso: false,
            diaEspanol,
            tieneTurno: false,
            turnoNombre: turno?.nombre ?? null,
        };
    }

    const configDia = matriz[diaEspanol];

    return {
        entrada: configDia.entrada,
        salida: configDia.salida,
        horas: configDia.descanso ? 0 : Number(configDia.horas) || Number(fallbackHoras) || 8,
        descanso: !!configDia.descanso,
        diaEspanol,
        tieneTurno: true,
        turnoNombre: turno?.nombre ?? null,
    };
}
