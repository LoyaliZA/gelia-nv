export function formatoMoneda(valor, opciones = {}) {
    const monto = Number(valor);
    if (Number.isNaN(monto)) {
        return '—';
    }

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        ...opciones,
    }).format(monto);
}

export function formatoDecimal(valor, decimales = 4) {
    const monto = Number(valor);
    if (Number.isNaN(monto)) {
        return '—';
    }

    return new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales,
    }).format(monto);
}

export function calcularSalariosPreview(datos, configuracion = {}) {
    const dias = Math.max(1, Number(configuracion.dias_periodo_pago) || 30);
    const horas = Math.max(0.01, Number(datos.horas_laboradas_oficiales) || 8);
    const decimalesMinuto = Number(configuracion.decimales_salario_minuto) || 8;

    const salarioBase = Number(datos.salario_base) || 0;
    const bonoProd = Number(datos.bono_productividad) || 0;
    const bonoPunt = Number(datos.bono_puntualidad) || 0;

    const salarioDiario = Math.round((salarioBase / dias) * 100) / 100;
    const salarioPorHora = Math.round((salarioDiario / horas) * 10000) / 10000;
    const salarioPorMinuto = Number((salarioDiario / (horas * 60)).toFixed(decimalesMinuto));

    return {
        salario_diario: salarioDiario,
        bono_productividad_diario: Math.round((bonoProd / dias) * 100) / 100,
        bono_puntualidad_diario: Math.round((bonoPunt / dias) * 100) / 100,
        salario_por_hora: salarioPorHora,
        salario_por_minuto: salarioPorMinuto,
    };
}

export function nombreCompletoColaborador(colaborador) {
    if (!colaborador) return '';
    return [colaborador.nombre, colaborador.apellido_paterno, colaborador.apellido_materno]
        .filter(Boolean)
        .join(' ');
}

function parseTimeToMinutes(time) {
    if (!time) return 0;
    const parts = String(time).slice(0, 5).split(':').map(Number);
    return (parts[0] || 0) * 60 + (parts[1] || 0);
}

function calcularHorasAPagar(minutosExtra, minutosMinimos = 30) {
    if (minutosExtra < minutosMinimos) return 0;
    return Math.floor((minutosExtra - minutosMinimos) / minutosMinimos) + 1;
}

export function calcularHorasExtraPreview(datos, configuracion = {}, colaborador = null) {
    const minutosMinimos = Math.max(1, Number(configuracion.he_minutos_minimos) || 30);
    const multiplicador = Number(configuracion.he_multiplicador_pago) || 2;

    let salidaDiaSiguiente = !!datos.salida_dia_siguiente;
    const entradaMin = parseTimeToMinutes(datos.hora_entrada || '08:00');
    const salidaMin = parseTimeToMinutes(datos.hora_salida || '17:00');

    if (datos.salida_dia_siguiente === undefined || datos.salida_dia_siguiente === null) {
        salidaDiaSiguiente = salidaMin <= entradaMin;
    }

    let totalMinutos = salidaDiaSiguiente
        ? (24 * 60 - entradaMin) + salidaMin
        : Math.max(0, salidaMin - entradaMin);

    const totalHoras = Math.round((totalMinutos / 60) * 100) / 100;
    const horasNormales = Number(colaborador?.horas_laboradas_oficiales ?? datos.horas_normales_snapshot ?? 8);
    const salarioPorHora = Number(colaborador?.salario_por_hora ?? datos.salario_por_hora_snapshot ?? 0);
    const tiempoExtraCrudo = Math.max(0, Math.round((totalHoras - horasNormales) * 100) / 100);
    const tiempoExtraMinutos = Math.round(tiempoExtraCrudo * 60);
    const horasExtraAPagar = calcularHorasAPagar(tiempoExtraMinutos, minutosMinimos);
    const totalEconomico = Math.round(horasExtraAPagar * multiplicador * salarioPorHora * 100) / 100;

    return {
        salida_dia_siguiente: salidaDiaSiguiente,
        total_horas_laboradas: totalHoras,
        horas_normales_snapshot: horasNormales,
        tiempo_extra_crudo: tiempoExtraCrudo,
        tiempo_extra_minutos: tiempoExtraMinutos,
        horas_extra_a_pagar: horasExtraAPagar,
        salario_por_hora_snapshot: salarioPorHora,
        multiplicador_snapshot: multiplicador,
        total_economico: totalEconomico,
        estado_pago: datos.fecha_programada_pago ? 'programado' : 'pendiente',
        area_snapshot: colaborador?.area?.nombre ?? datos.area_snapshot ?? null,
    };
}

export function formatearHora(time) {
    if (!time) return '—';
    return String(time).slice(0, 5);
}

export function calcularIncidenciaPreview(datos, colaborador = null, tipoFalta = null) {
    if (!colaborador || !tipoFalta) {
        return {
            deduccion_salario_base: 0,
            deduccion_bono_puntualidad: 0,
            deduccion_bono_productividad: 0,
            total_deduccion: 0,
            estado_deduccion: datos.fecha_deduccion_nomina ? 'programado' : 'pendiente',
        };
    }

    const dedSalario = tipoFalta.aplica_deduccion_salario_base
        ? Math.round(Number(colaborador.salario_diario) * 100) / 100
        : 0;

    const factorPunt = Number(tipoFalta.factor_penalizacion_puntualidad) || 0;
    const factorProd = Number(tipoFalta.factor_penalizacion_productividad) || 0;

    const dedPunt = Math.round(Number(colaborador.bono_puntualidad_diario) * factorPunt * 100) / 100;
    const dedProd = Math.round(Number(colaborador.bono_productividad_diario) * factorProd * 100) / 100;
    const total = Math.round(dedSalario + dedPunt + dedProd);

    return {
        deduccion_salario_base: dedSalario,
        deduccion_bono_puntualidad: dedPunt,
        deduccion_bono_productividad: dedProd,
        total_deduccion: total,
        estado_deduccion: datos.fecha_deduccion_nomina ? 'programado' : 'pendiente',
    };
}

export function formatoDeduccionEntera(valor) {
    const monto = Number(valor);
    if (Number.isNaN(monto)) return '—';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(monto);
}

