import { describe, expect, it } from 'vitest';
import {
    debeMostrarModalEspera,
    esConflictoVersionTurno,
    estadoUiTurnoAsignado,
    etiquetasPrioridadDesdeTurno,
    formatearCronometro,
    mensajeErrorOperacionTurno,
    milisegundosRestantes,
    puedeCerrarAtencion,
    puedeIniciarAtencion,
} from './tableroVentasUtils';

describe('tableroVentasUtils', () => {
    const permisos = { cerrar_atencion: true };

    it('detecta etiquetas de prioridad', () => {
        const etiquetas = etiquetasPrioridadDesdeTurno({
            prioridad_diamante: true,
            prioridad_vip: true,
            prioridad_adulto_mayor: false,
            prioridad_discapacidad: true,
        });
        expect(etiquetas).toEqual(['Diamante', 'VIP', 'Discapacidad']);
    });

    it('clasifica estados ui de espera y atención', () => {
        const espera = {
            atencion: { atencion_en_curso: false, espera_inicial_vencida: false },
        };
        const vencida = {
            atencion: { atencion_en_curso: false, espera_inicial_vencida: true },
        };
        const atencion = {
            atencion: { atencion_en_curso: true, espera_inicial_vencida: false },
        };

        expect(estadoUiTurnoAsignado(espera)).toBe('espera');
        expect(estadoUiTurnoAsignado(vencida)).toBe('espera_vencida');
        expect(estadoUiTurnoAsignado(atencion)).toBe('atencion');
        expect(debeMostrarModalEspera(vencida)).toBe(true);
    });

    it('calcula tiempos visuales desde timestamps servidor', () => {
        const servidorAt = '2026-09-04T12:00:00Z';
        const expiraAt = '2026-09-04T12:05:00Z';
        expect(milisegundosRestantes(expiraAt, servidorAt)).toBe(5 * 60 * 1000);
        expect(formatearCronometro(125000)).toBe('02:05');
    });

    it('habilita acciones según permiso y estado', () => {
        const turnoEspera = {
            version: 1,
            atencion: { atencion_en_curso: false, espera_inicial_vencida: false, fin_at: null },
        };
        const turnoAtencion = {
            version: 1,
            atencion: { atencion_en_curso: true, espera_inicial_vencida: false, fin_at: null },
        };

        expect(puedeIniciarAtencion(turnoEspera, permisos)).toBe(true);
        expect(puedeIniciarAtencion(turnoAtencion, permisos)).toBe(false);
        expect(puedeCerrarAtencion(turnoAtencion, permisos)).toBe(true);
    });

    it('interpreta conflicto de versión y errores 403', () => {
        const conflicto = { response: { status: 422, data: { errors: { version: ['Obsoleto'] } } } };
        const prohibido = { response: { status: 403, data: {} } };

        expect(esConflictoVersionTurno(conflicto)).toBe(true);
        expect(mensajeErrorOperacionTurno(conflicto)).toContain('Obsoleto');
        expect(mensajeErrorOperacionTurno(prohibido)).toContain('permiso');
    });
});
