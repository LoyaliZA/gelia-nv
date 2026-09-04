import { describe, expect, it } from 'vitest';
import {
    esConflictoVersion,
    etiquetaActividad,
    etiquetaJornada,
    mensajeAvisoSucursal,
    mensajeErrorOperacion,
    puedeAbrirJornada,
    puedeCerrarJornada,
    puedeFinalizarPausa,
    puedeIniciarPausa,
    referenciaCronometro,
} from './operacionUtils';

describe('operacionUtils', () => {
    it('etiqueta jornada y actividad conocidas', () => {
        expect(etiquetaJornada('ABIERTA')).toBe('Abierta');
        expect(etiquetaActividad('en_pausa')).toBe('En pausa');
    });

    it('permisos de acciones según estado', () => {
        const permisos = {
            jornada_abrir: true,
            jornada_cerrar: true,
            pausa: true,
        };

        expect(puedeAbrirJornada({ jornada: null }, permisos)).toBe(true);
        expect(puedeCerrarJornada({ jornada: { estado: 'ABIERTA' } }, permisos)).toBe(true);
        expect(puedeIniciarPausa({ jornada: { estado: 'ABIERTA' }, actividad: 'disponible' }, permisos)).toBe(true);
        expect(puedeFinalizarPausa({ jornada: { estado: 'ABIERTA' }, actividad: 'en_pausa' }, permisos)).toBe(true);
        expect(puedeAbrirJornada({ jornada: { estado: 'ABIERTA' } }, permisos)).toBe(false);
    });

    it('deriva cronómetro desde timestamps de servidor', () => {
        const estado = {
            jornada: { estado: 'ABIERTA', apertura_at: '2026-09-04T10:00:00Z' },
            actividad: 'disponible',
            intervalo: { inicio_at: '2026-09-04T10:05:00Z' },
        };

        expect(referenciaCronometro(estado)).toEqual({
            etiqueta: 'Tiempo disponible',
            referenciaAt: '2026-09-04T10:05:00Z',
            modo: 'transcurrido',
        });
    });

    it('mensaje de aviso cuando sucursal no acepta altas', () => {
        const aviso = mensajeAvisoSucursal({
            sucursal_dia: { acepta_altas: false, cierre_manual_at: '2026-09-04T19:00:00Z' },
        });

        expect(aviso).toContain('cierre manual');
    });

    it('detecta conflicto de versión y error de red', () => {
        const conflicto = { response: { status: 422, data: { errors: { version: ['Obsoleto'] } } } };
        expect(esConflictoVersion(conflicto)).toBe(true);
        expect(mensajeErrorOperacion(conflicto)).toContain('Obsoleto');

        const red = {};
        expect(mensajeErrorOperacion(red)).toContain('conectar');
    });
});
