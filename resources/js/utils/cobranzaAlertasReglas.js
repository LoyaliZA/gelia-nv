const DEFAULT_CONFIG = {
    intervalo_dias: 3,
    umbral_diario: 30,
    dias_gracia: 3,
    dias_habiles: [1, 2, 3, 4, 5],
};

export const COBRANZA_DIAS_SEMANA = [
    { iso: 1, label: 'Lun' },
    { iso: 2, label: 'Mar' },
    { iso: 3, label: 'Mié' },
    { iso: 4, label: 'Jue' },
    { iso: 5, label: 'Vie' },
    { iso: 6, label: 'Sáb' },
    { iso: 7, label: 'Dom' },
];

export function normalizarConfigCobranzaAlertas(configuracion = {}) {
    const diasHabilesRaw = Array.isArray(configuracion.dias_habiles)
        ? configuracion.dias_habiles
        : DEFAULT_CONFIG.dias_habiles;

    let diasHabiles = [...new Set(
        diasHabilesRaw
            .map((dia) => Number(dia))
            .filter((dia) => Number.isInteger(dia) && dia >= 1 && dia <= 7)
    )].sort((a, b) => a - b);

    if (diasHabiles.length === 0) {
        diasHabiles = [...DEFAULT_CONFIG.dias_habiles];
    }

    return {
        intervalo_dias: Math.max(1, Number(configuracion.intervalo_dias) || DEFAULT_CONFIG.intervalo_dias),
        umbral_diario: Math.max(1, Number(configuracion.umbral_diario) || DEFAULT_CONFIG.umbral_diario),
        dias_gracia: Math.max(0, Number(configuracion.dias_gracia) ?? DEFAULT_CONFIG.dias_gracia),
        dias_habiles: diasHabiles,
    };
}

export function esDiaHabilCobranza(fecha = new Date(), configuracion = {}) {
    const config = normalizarConfigCobranzaAlertas(configuracion);
    const iso = fecha.getDay() === 0 ? 7 : fecha.getDay();
    return config.dias_habiles.includes(iso);
}

export function esDiaDeLlamadaCobranza(diasAtraso, configuracion = {}) {
    if (diasAtraso < 1) return false;

    const config = normalizarConfigCobranzaAlertas(configuracion);

    // ponytail: skip interval/threshold, call daily post grace period (3 days post corte)
    if (diasAtraso <= config.dias_gracia) return false;

    return true;
}

export function diasParaProximaLlamadaCobranza(diasAtraso, configuracion = {}) {
    if (diasAtraso < 1) return 0;

    const config = normalizarConfigCobranzaAlertas(configuracion);

    if (esDiaDeLlamadaCobranza(diasAtraso, config)) return 0;

    // ponytail: daily calling after grace, remaining days is grace + 1 - delay
    return (config.dias_gracia + 1) - diasAtraso;
}
