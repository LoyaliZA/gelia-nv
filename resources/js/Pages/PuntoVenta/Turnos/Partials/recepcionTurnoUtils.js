import { formatearDuracion } from '../../Reportes/reportesPdvUtils';
import { estadoUiTurnoAsignado } from './tableroVentasUtils';
import { etiquetasPrioridadTurno } from './altaTurnoUtils';

const ETIQUETAS_ATENCION_ASIGNADO = {
    asignado: 'Asignado',
    espera: 'En espera de llegada',
    atencion: 'En atención',
    espera_vencida: 'Espera vencida',
};

export function abreviarNombreLlamado(nombre) {
    if (!nombre) return '—';
    const partes = nombre.trim().split(/\s+/).filter(Boolean);
    if (partes.length === 0) return '—';
    if (partes.length === 1) {
        return partes[0].length > 20 ? `${partes[0].slice(0, 18)}…` : partes[0];
    }

    const inicial = partes[partes.length - 1][0]?.toUpperCase() || '';
    const corto = `${partes[0]} ${inicial}.`;
    return corto.length > 22 ? `${partes[0].slice(0, 12)} ${inicial}.` : corto;
}

export function resumenBandejaRecepcion(bandeja) {
    const resumen = bandeja?.resumen;
    if (resumen) {
        return {
            enEspera: Number(resumen.en_espera) || 0,
            asignados: Number(resumen.asignados) || 0,
            mayorEsperaSegundos: Number(resumen.mayor_espera_segundos) || 0,
            vendedoresDisponibles: Number(resumen.vendedores_disponibles) || 0,
        };
    }

    const enCola = bandeja?.en_cola ?? [];
    const asignados = bandeja?.asignados ?? [];
    const mayorEspera = enCola.reduce((maximo, turno) => {
        const espera = Number(turno?.espera_segundos);
        return Number.isFinite(espera) ? Math.max(maximo, espera) : maximo;
    }, 0);

    return {
        enEspera: enCola.length,
        asignados: asignados.length,
        mayorEsperaSegundos: mayorEspera,
        vendedoresDisponibles: 0,
    };
}

export function formatearEsperaTurno(turno) {
    const segundos = Number(turno?.espera_segundos);
    if (!Number.isFinite(segundos)) return '—';
    return formatearDuracion(segundos);
}

export function etiquetaAtencionAsignado(turno) {
    const estadoUi = estadoUiTurnoAsignado(turno);
    return ETIQUETAS_ATENCION_ASIGNADO[estadoUi] || '—';
}

export function resumenPrioridadTurno(turno) {
    const etiquetas = etiquetasPrioridadTurno(turno);
    return etiquetas.length > 0 ? etiquetas.join(', ') : 'Normal';
}
