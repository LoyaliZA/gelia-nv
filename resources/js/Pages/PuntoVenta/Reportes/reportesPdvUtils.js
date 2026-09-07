export const TIPO_REPORTE_RESGUARDOS = 'resguardos';
export const TIPO_REPORTE_TURNOS_OPERACION = 'turnos_operacion';
export const TIPO_REPORTE_CONJUNTO = 'conjunto';

export const RUTAS_REPORTE_PDV = {
    [TIPO_REPORTE_RESGUARDOS]: 'punto_venta.reportes.resguardos',
    [TIPO_REPORTE_TURNOS_OPERACION]: 'punto_venta.reportes.turnos_operacion',
    [TIPO_REPORTE_CONJUNTO]: 'punto_venta.reportes.conjunto',
};

export const ETIQUETAS_TIPO_REPORTE = {
    [TIPO_REPORTE_RESGUARDOS]: 'Resguardos',
    [TIPO_REPORTE_TURNOS_OPERACION]: 'Turnos y operación',
    [TIPO_REPORTE_CONJUNTO]: 'Conjunto',
};

/** Omite valores vacíos para query string de Inertia. */
export function paramsFiltrosReportePdv(filtros = {}, extra = {}) {
    const merged = { ...filtros, ...extra };
    const params = {};

    Object.entries(merged).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') return;
        params[key] = value;
    });

    return params;
}

export function isoDesdeFechaLocal(fecha) {
    if (!fecha) return null;
    const parsed = new Date(`${fecha}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return null;
    return parsed.toISOString();
}

export function isoHastaFechaLocal(fecha) {
    if (!fecha) return null;
    const parsed = new Date(`${fecha}T23:59:59`);
    if (Number.isNaN(parsed.getTime())) return null;
    return parsed.toISOString();
}

export function fechaLocalDesdeIso(iso) {
    if (!iso) return '';
    const parsed = new Date(iso);
    if (Number.isNaN(parsed.getTime())) return '';
    const y = parsed.getFullYear();
    const m = String(parsed.getMonth() + 1).padStart(2, '0');
    const d = String(parsed.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function filtrosResguardosParaApi(estadoLocal = {}) {
    return paramsFiltrosReportePdv({
        desde: isoDesdeFechaLocal(estadoLocal.desde),
        hasta: isoHastaFechaLocal(estadoLocal.hasta),
        corte_reporte_at: estadoLocal.corte_reporte_at || null,
        sucursal_id: estadoLocal.sucursal_id || null,
        estado: estadoLocal.estado || null,
        antiguedad: estadoLocal.antiguedad || null,
        tipo_incidencia: estadoLocal.tipo_incidencia || null,
    });
}

export function filtrosTurnosOperacionParaApi(estadoLocal = {}) {
    return paramsFiltrosReportePdv({
        desde: isoDesdeFechaLocal(estadoLocal.desde),
        hasta: isoHastaFechaLocal(estadoLocal.hasta),
        corte_reporte_at: estadoLocal.corte_reporte_at || null,
        sucursal_id: estadoLocal.sucursal_id || null,
        servicio: estadoLocal.servicio || null,
        user_id: estadoLocal.user_id || null,
        fecha_operativa: estadoLocal.fecha_operativa || null,
        franja_desde: estadoLocal.franja_desde || null,
        franja_hasta: estadoLocal.franja_hasta || null,
        alcance: estadoLocal.alcance || null,
    });
}

export function filtrosConjuntoParaApi(estadoLocal = {}) {
    const resguardos = estadoLocal.resguardos || estadoLocal;
    const turnos = estadoLocal.turnos_operacion || estadoLocal;

    const comunes = {
        desde: isoDesdeFechaLocal(resguardos.desde || turnos.desde),
        hasta: isoHastaFechaLocal(resguardos.hasta || turnos.hasta),
        corte_reporte_at: resguardos.corte_reporte_at || turnos.corte_reporte_at || null,
        sucursal_id: resguardos.sucursal_id || turnos.sucursal_id || null,
    };

    return paramsFiltrosReportePdv({
        ...comunes,
        estado: resguardos.estado || null,
        antiguedad: resguardos.antiguedad || null,
        tipo_incidencia: resguardos.tipo_incidencia || null,
        servicio: turnos.servicio || null,
        user_id: turnos.user_id || null,
        fecha_operativa: turnos.fecha_operativa || null,
        franja_desde: turnos.franja_desde || null,
        franja_hasta: turnos.franja_hasta || null,
        alcance: turnos.alcance || null,
    });
}

export function estadoFiltrosDesdePayload(filtros = {}, tipoReporte) {
    if (tipoReporte === TIPO_REPORTE_CONJUNTO) {
        const resguardos = filtros.resguardos || {};
        const turnos = filtros.turnos_operacion || {};
        return {
            resguardos: {
                desde: fechaLocalDesdeIso(resguardos.desde),
                hasta: fechaLocalDesdeIso(resguardos.hasta),
                sucursal_id: resguardos.sucursal_id || '',
                estado: resguardos.estado || '',
                antiguedad: resguardos.antiguedad || '',
                tipo_incidencia: resguardos.tipo_incidencia || '',
            },
            turnos_operacion: {
                desde: fechaLocalDesdeIso(turnos.desde),
                hasta: fechaLocalDesdeIso(turnos.hasta),
                sucursal_id: turnos.sucursal_id || '',
                servicio: turnos.servicio || '',
                alcance: turnos.alcance || '',
                fecha_operativa: turnos.fecha_operativa || '',
                franja_desde: turnos.franja_desde || '',
                franja_hasta: turnos.franja_hasta || '',
            },
        };
    }

    return {
        desde: fechaLocalDesdeIso(filtros.desde),
        hasta: fechaLocalDesdeIso(filtros.hasta),
        sucursal_id: filtros.sucursal_id || '',
        estado: filtros.estado || '',
        antiguedad: filtros.antiguedad || '',
        tipo_incidencia: filtros.tipo_incidencia || '',
        servicio: filtros.servicio || '',
        alcance: filtros.alcance || '',
        fecha_operativa: filtros.fecha_operativa || '',
        franja_desde: filtros.franja_desde || '',
        franja_hasta: filtros.franja_hasta || '',
    };
}

export function bloqueMetricas(payload, seccionId, tipoReporte) {
    if (tipoReporte === TIPO_REPORTE_RESGUARDOS) {
        return payload?.resguardos?.metricas || {};
    }
    if (tipoReporte === TIPO_REPORTE_TURNOS_OPERACION) {
        return payload?.turnos_operacion?.metricas || {};
    }
    if (seccionId === 'resguardos') {
        return payload?.resguardos?.metricas || {};
    }
    return payload?.turnos_operacion?.metricas || {};
}

export function desgloseSucursal(payload, seccionId, tipoReporte) {
    if (tipoReporte === TIPO_REPORTE_RESGUARDOS) {
        return payload?.resguardos?.por_sucursal || {};
    }
    if (tipoReporte === TIPO_REPORTE_TURNOS_OPERACION) {
        return payload?.turnos_operacion?.por_sucursal || {};
    }
    if (seccionId === 'resguardos') {
        return payload?.resguardos?.por_sucursal || {};
    }
    return payload?.turnos_operacion?.por_sucursal || {};
}

export function formatearValorMetrica(metrica) {
    if (!metrica) return '—';
    const unidad = metrica.unidad || '';

    if (unidad === 'porcentaje') {
        const valor = metrica.valor ?? metrica.promedio_segundos;
        return valor != null ? `${Number(valor).toFixed(1)}%` : '—';
    }

    if (unidad === 'segundos') {
        if (metrica.promedio_segundos != null) {
            return formatearDuracion(metrica.promedio_segundos);
        }
        if (metrica.valor != null) {
            return formatearDuracion(metrica.valor);
        }
        return '—';
    }

    if (unidad === 'distribucion' && metrica.distribucion) {
        const partes = Object.entries(metrica.distribucion).map(([k, v]) => `${k}: ${v}%`);
        return partes.join(' · ') || '—';
    }

    if (metrica.valor != null) {
        return Number(metrica.valor).toLocaleString('es-MX');
    }

    return '—';
}

export function formatearDuracion(segundos) {
    const total = Number(segundos);
    if (!Number.isFinite(total) || total < 0) return '—';
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = Math.floor(total % 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
}

export function metricaTieneDetalle(metrica) {
    if (!metrica) return false;
    return Boolean(
        metrica.percentiles
        || metrica.distribucion
        || metrica.en_curso
        || metrica.conteo != null
        || metrica.numerador != null
    );
}

export function puedeVerTipoReporte(vistasDisponibles, tipo) {
    if (!vistasDisponibles) return false;
    if (tipo === TIPO_REPORTE_CONJUNTO) return Boolean(vistasDisponibles.conjunto);
    if (tipo === TIPO_REPORTE_RESGUARDOS) return Boolean(vistasDisponibles.resguardos);
    if (tipo === TIPO_REPORTE_TURNOS_OPERACION) return Boolean(vistasDisponibles.turnos_operacion);
    return false;
}

export function estimarExportacionPesada(tipoReporte, formato, filtros = {}) {
    if (formato === 'pdf') return true;
    if (tipoReporte === TIPO_REPORTE_CONJUNTO) return true;
    const desde = filtros.desde ? new Date(filtros.desde) : null;
    const hasta = filtros.hasta ? new Date(filtros.hasta) : null;
    if (!desde || !hasta) return false;
    const dias = Math.max(1, Math.ceil((hasta - desde) / (1000 * 60 * 60 * 24)));
    return dias > 31;
}
