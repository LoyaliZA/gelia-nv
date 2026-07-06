import { diaEspanolDesdeFecha, normalizarMatrizHorario } from './matrizHorarioTurno';

export function formatoMoneda(valor, opciones = {}) {
    const monto = Number(valor);
    if (Number.isNaN(monto)) {
        return '—';
    }

    let customOptions = opciones;
    if (typeof opciones === 'number') {
        customOptions = { minimumFractionDigits: opciones, maximumFractionDigits: opciones };
    }

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        ...customOptions,
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
    // Desacoplamiento: el divisor financiero se mantiene estandarizado en 8 horas diarias
    const horas = 8;
    const decimalesMinuto = Number(configuracion.decimales_salario_minuto) || 8;

    const salarioDiario = Number(datos.salario_base) || 0;
    const bonoProdDiario = Number(datos.bono_productividad) || 0;
    const bonoPuntDiario = Number(datos.bono_puntualidad) || 0;

    const salarioPeriodo = Math.round((salarioDiario * dias) * 100) / 100;
    const bonoProdPeriodo = Math.round((bonoProdDiario * dias) * 100) / 100;
    const bonoPuntPeriodo = Math.round((bonoPuntDiario * dias) * 100) / 100;

    const salarioPorHora = Math.round((salarioDiario / horas) * 10000) / 10000;
    const salarioPorMinuto = Number((salarioDiario / (horas * 60)).toFixed(decimalesMinuto));

    return {
        salario_periodo: salarioPeriodo,
        salario_diario: salarioDiario,
        bono_productividad_periodo: bonoProdPeriodo,
        bono_puntualidad_periodo: bonoPuntPeriodo,
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
    const graciaMinutos = Math.max(0, Number(configuracion.he_gracia_minutos_despues_salida) || 30);
    const multiplicador = Number(configuracion.he_multiplicador_pago) || 2;
    const usarTarifaFija = configuracion.he_usar_tarifa_fija !== false;
    const tarifaFija = Number(configuracion.he_tarifa_hora_fija) || 39;

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
    const tarifaHora = usarTarifaFija ? tarifaFija : salarioPorHora;

    let salidaOficialMin = entradaMin + Math.round(horasNormales * 60);
    const turno = colaborador?.turno;
    const matrizHorario = turno?.matriz_horario ? normalizarMatrizHorario(turno.matriz_horario) : null;

    if (matrizHorario) {
        const diaEspanol = diaEspanolDesdeFecha(datos.fecha_turno || new Date().toISOString().slice(0, 10));
        const configDia = matrizHorario[diaEspanol];

        if (configDia && !configDia.descanso) {
            salidaOficialMin = parseTimeToMinutes(configDia.salida);
        }
    } else if (colaborador?.hora_salida_oficial) {
        salidaOficialMin = parseTimeToMinutes(colaborador.hora_salida_oficial);
    } else if (colaborador?.hora_entrada_oficial) {
        salidaOficialMin = parseTimeToMinutes(colaborador.hora_entrada_oficial) + Math.round(horasNormales * 60);
    }

    const inicioExtraMin = salidaOficialMin + graciaMinutos;
    const tiempoExtraMinutos = salidaMin > inicioExtraMin && !salidaDiaSiguiente
        ? salidaMin - inicioExtraMin
        : salidaDiaSiguiente
            ? Math.max(0, (24 * 60 - inicioExtraMin) + salidaMin)
            : 0;

    const tiempoExtraCrudo = Math.round((tiempoExtraMinutos / 60) * 100) / 100;
    const horasExtraAPagar = calcularHorasAPagar(tiempoExtraMinutos, minutosMinimos);
    const montoHorasExtra = Math.round(horasExtraAPagar * multiplicador * tarifaHora * 100) / 100;

    return {
        salida_dia_siguiente: salidaDiaSiguiente,
        total_horas_laboradas: totalHoras,
        horas_normales_snapshot: horasNormales,
        tiempo_extra_crudo: tiempoExtraCrudo,
        tiempo_extra_minutos: tiempoExtraMinutos,
        horas_extra_a_pagar: horasExtraAPagar,
        salario_por_hora_snapshot: salarioPorHora,
        tarifa_hora_snapshot: tarifaHora,
        multiplicador_snapshot: multiplicador,
        total_economico: montoHorasExtra,
        monto_horas_extra: montoHorasExtra,
        estado_pago: datos.fecha_programada_pago ? 'programado' : 'pendiente',
        area_snapshot: colaborador?.area?.nombre ?? datos.area_snapshot ?? null,
    };
}

export function formatearHora(time) {
    if (!time) return '—';
    return String(time).slice(0, 5);
}

export function calcularDeduccionPreview(datos, colaborador = null, regla = null, producto = null) {
    if (!colaborador || !regla) {
        return {
            monto_deduccion_base: 0,
            factor_multiplicador: 1,
            total_parcial: 0,
            monto_total_final: 0,
            total_deduccion: 0,
            deduccion_salario_base: 0,
            deduccion_bono_puntualidad: 0,
            deduccion_bono_productividad: 0,
            estado_deduccion: 'pendiente_nomina',
        };
    }

    const factor = Math.max(0.01, Number(datos.factor_multiplicador) || 1);
    const esNomina = regla.tipo_comportamiento === 'deduccion_nomina'
        || regla.categoria === 'falta'
        || regla.categoria === 'retardo';

    if (esNomina) {
        const dedSalario = regla.aplica_deduccion_salario_base
            ? Math.round(Number(colaborador.salario_diario) * factor * 100) / 100
            : 0;
        const factorPunt = Number(regla.factor_penalizacion_puntualidad) || 0;
        const factorProd = Number(regla.factor_penalizacion_productividad) || 0;
        const dedPunt = Math.round(Number(colaborador.bono_puntualidad_diario) * factorPunt * factor * 100) / 100;
        const dedProd = Math.round(Number(colaborador.bono_productividad_diario) * factorProd * factor * 100) / 100;
        const totalFinal = Math.round((dedSalario + dedPunt + dedProd) * 100) / 100;

        return {
            monto_deduccion_base: totalFinal,
            factor_multiplicador: factor,
            total_parcial: totalFinal,
            monto_total_final: totalFinal,
            deduccion_salario_base: dedSalario,
            deduccion_bono_puntualidad: dedPunt,
            deduccion_bono_productividad: dedProd,
            total_deduccion: Math.round(totalFinal * 100) / 100,
            estado_deduccion: datos.origen_deduccion === 'comisiones' ? 'pendiente_comision' : 'pendiente_nomina',
        };
    }

    let base = 0;
    switch (regla.tipo_comportamiento) {
        case 'cobro_fijo':
            base = Number(regla.monto_fijo) || 0;
            break;
        case 'cobro_costo_producto':
            base = Number(producto?.costo) || 0;
            break;
        case 'cobro_precio_venta_producto':
            base = Number(producto?.precio_venta) || 0;
            break;
        case 'cancelacion_bono_especifico':
            base = 0;
            break;
        default:
            base = 0;
    }

    const parcial = Math.round(base * factor * 100) / 100;
    const totalFinal = factor === 1 ? Math.round(base * 100) / 100 : parcial;

    return {
        monto_deduccion_base: Math.round(base * 100) / 100,
        factor_multiplicador: factor,
        total_parcial: parcial,
        monto_total_final: totalFinal,
        deduccion_salario_base: 0,
        deduccion_bono_puntualidad: 0,
        deduccion_bono_productividad: 0,
        total_deduccion: Math.round(totalFinal),
        estado_deduccion: datos.origen_deduccion === 'comisiones' ? 'pendiente_comision' : 'pendiente_nomina',
    };
}

/** @deprecated Use calcularDeduccionPreview */
export function calcularIncidenciaPreview(datos, colaborador = null, tipoFalta = null) {
    return calcularDeduccionPreview(datos, colaborador, tipoFalta);
}

export function formatoDeduccionEntera(valor) {
    return formatoDeduccionDecimal(valor);
}

export function formatoDeduccionDecimal(valor) {
    const monto = Number(valor);
    if (Number.isNaN(monto)) return '—';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(monto);
}

