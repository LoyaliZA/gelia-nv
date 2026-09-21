import { describe, expect, it } from 'vitest';
import {
    esConflictoVersion,
    etiquetaActividad,
    etiquetaEstadoVendedor,
    etiquetaJornada,
    formatearUltimaActualizacion,
    avisoSucursalOperacion,
    firmaAvisoSucursal,
    mensajeAvisoSucursal,
    tipoAvisoSucursal,
    mensajeErrorOperacion,
    mensajeMiAtencion,
    mostrarBandejaSinTurno,
    puedeAbrirJornada,
    puedeAbrirSucursalManualmente,
    puedeCerrarJornada,
    puedeFinalizarPausa,
    puedeIniciarPausa,
    puedeReabrirSucursal,
    referenciaCronometro,
} from './operacionUtils';

describe('operacionUtils', () => {
    it('etiqueta jornada y actividad conocidas', () => {
        expect(etiquetaJornada('ABIERTA')).toBe('Abierta');
        expect(etiquetaActividad('en_pausa')).toBe('En pausa');
        expect(etiquetaEstadoVendedor('en_retencion')).toBe('En pausa');
    });

    it('mensajes de mi atención por estado', () => {
        expect(mensajeMiAtencion('no_activado')).toContain('gerencia active');
        expect(mensajeMiAtencion('disponible')).toContain('alerta');
        expect(mensajeMiAtencion('en_retencion')).toContain('pausa');
        expect(mensajeMiAtencion('jornada_cerrada')).toContain('finalizó');
        expect(mensajeMiAtencion('estado_raro')).toContain('Estado no disponible');
    });

    it('solo disponible muestra bandeja sin turno', () => {
        expect(mostrarBandejaSinTurno('disponible')).toBe(true);
        expect(mostrarBandejaSinTurno('jornada_cerrada')).toBe(false);
        expect(mostrarBandejaSinTurno('no_activado')).toBe(false);
    });

    it('formatea última actualización', () => {
        expect(formatearUltimaActualizacion('2026-09-04T12:05:00Z')).not.toBe('—');
        expect(formatearUltimaActualizacion(null)).toBe('—');
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

    it('deriva cronómetro de pausa desde timestamps de servidor', () => {
        const estado = {
            jornada: { estado: 'ABIERTA', apertura_at: '2026-09-04T10:00:00Z' },
            actividad: 'en_pausa',
            intervalo: { inicio_at: '2026-09-04T10:05:00Z' },
        };

        expect(referenciaCronometro(estado)).toEqual({
            etiqueta: 'Tiempo en pausa',
            referenciaAt: '2026-09-04T10:05:00Z',
            modo: 'transcurrido',
        });
    });

    it('deriva cronómetro disponible desde timestamps de servidor', () => {
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

    it('mensaje de aviso antes de hora de apertura', () => {
        const aviso = mensajeAvisoSucursal({
            sucursal_dia: { acepta_altas: false },
            antes_de_apertura: true,
            horario_apertura: { hora_apertura: '08:00' },
        });

        expect(aviso).toContain('08:00');
    });

    it('permite reabrir sucursal tras cierre manual', () => {
        const permisos = { cerrar_sucursal: true };
        const estado = {
            sucursal_dia: { acepta_altas: false, cierre_manual_at: '2026-09-04T19:00:00Z' },
        };

        expect(puedeReabrirSucursal(estado, permisos)).toBe(true);
        expect(puedeReabrirSucursal({ sucursal_dia: { acepta_altas: true } }, permisos)).toBe(false);
    });

    it('permite iniciar jornada manual solo si aún no abre y no hay cierre manual', () => {
        const permisos = { cerrar_sucursal: true };

        expect(puedeAbrirSucursalManualmente({
            sucursal_dia: { acepta_altas: false, cierre_manual_at: null },
        }, permisos)).toBe(true);

        expect(puedeAbrirSucursalManualmente({
            sucursal_dia: { acepta_altas: true, origen_apertura: 'automatica' },
        }, permisos)).toBe(false);

        expect(puedeAbrirSucursalManualmente({
            sucursal_dia: { acepta_altas: false, cierre_manual_at: '2026-09-04T19:00:00Z' },
        }, permisos)).toBe(false);
    });

    it('aviso de jornada iniciada de forma manual', () => {
        const aviso = mensajeAvisoSucursal({
            sucursal_dia: {
                acepta_altas: true,
                origen_apertura: 'manual',
                apertura_manual_at: '2026-09-21T13:00:00Z',
            },
        });

        expect(aviso).toContain('manual');
    });

    it('clasifica aviso informativo y de advertencia', () => {
        expect(tipoAvisoSucursal({
            sucursal_dia: { acepta_altas: true, origen_apertura: 'automatica' },
        })).toBe('info');

        expect(tipoAvisoSucursal({
            sucursal_dia: { acepta_altas: false },
        })).toBe('warning');
    });

    it('firma de aviso evita repetir el mismo toast', () => {
        const estado = {
            sucursal_dia: { acepta_altas: true, origen_apertura: 'automatica' },
        };

        expect(avisoSucursalOperacion(estado)).toEqual({
            mensaje: 'La jornada se inició de forma automática según el horario configurado.',
            tipo: 'info',
        });
        expect(firmaAvisoSucursal(estado)).toBe(
            'info:La jornada se inició de forma automática según el horario configurado.',
        );
    });

    it('detecta conflicto de versión y error de red', () => {
        const conflicto = { response: { status: 422, data: { errors: { version: ['Obsoleto'] } } } };
        expect(esConflictoVersion(conflicto)).toBe(true);
        expect(mensajeErrorOperacion(conflicto)).toContain('Obsoleto');

        const red = {};
        expect(mensajeErrorOperacion(red)).toContain('conectar');
    });
});
