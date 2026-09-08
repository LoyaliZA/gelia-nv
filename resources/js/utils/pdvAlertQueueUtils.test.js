// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    conexionDegradadaPdv,
    encolarAlertaPdv,
    etiquetaConexionTiempoRealPdv,
    expirarColaPdv,
    mapearEstadoConexionPdv,
    mensajeAlertaPdv,
    normalizarEnvelopePdv,
    ordenarColaPdv,
    PDV_ESTADO_CONEXION,
} from './pdvAlertQueueUtils';

const envelopeBase = (extra = {}) => ({
    event_id: 'evt-1',
    tipo: 'turno.alta',
    dominio: 'turnos',
    audiencia: 'sucursal',
    sucursal_id: 3,
    version: 2,
    payload_version: 1,
    ocurrido_at: '2026-09-04T18:00:00.000Z',
    datos: { folio: 'A-01' },
    ...extra,
});

describe('pdvAlertQueueUtils', () => {
    it('normaliza y rechaza payloads inválidos', () => {
        expect(normalizarEnvelopePdv(null)).toBeNull();
        expect(normalizarEnvelopePdv({ tipo: 'turno.alta' })).toBeNull();
        expect(normalizarEnvelopePdv(envelopeBase()).event_id).toBe('evt-1');
    });

    it('deduplica por event_id y ordena por ocurrido_at', () => {
        const ids = new Set();
        const primero = encolarAlertaPdv([], ids, envelopeBase({
            event_id: 'evt-2',
            ocurrido_at: '2026-09-04T18:05:00.000Z',
        }), 1_000);
        const segundo = encolarAlertaPdv(primero.cola, primero.idsVistos, envelopeBase({
            event_id: 'evt-1',
            ocurrido_at: '2026-09-04T18:00:00.000Z',
        }), 1_000);
        const duplicado = encolarAlertaPdv(segundo.cola, segundo.idsVistos, envelopeBase({
            event_id: 'evt-1',
        }), 1_100);

        expect(segundo.encolado).toBe(true);
        expect(duplicado.encolado).toBe(false);
        expect(ordenarColaPdv(segundo.cola).map((item) => item.event_id)).toEqual(['evt-1', 'evt-2']);
    });

    it('expira entradas vencidas', () => {
        const cola = [{
            event_id: 'evt-viejo',
            visible_hasta_ms: 500,
        }, {
            event_id: 'evt-vigente',
            visible_hasta_ms: 5_000,
        }];

        expect(expirarColaPdv(cola, 1_000).map((item) => item.event_id)).toEqual(['evt-vigente']);
    });

    it('mapea estados de conexión y mensajes', () => {
        expect(mapearEstadoConexionPdv('connected')).toBe(PDV_ESTADO_CONEXION.conectado);
        expect(mapearEstadoConexionPdv('unavailable')).toBe(PDV_ESTADO_CONEXION.degradado);
        expect(conexionDegradadaPdv(PDV_ESTADO_CONEXION.conectado)).toBe(false);
        expect(conexionDegradadaPdv(PDV_ESTADO_CONEXION.desconectado)).toBe(true);
        expect(etiquetaConexionTiempoRealPdv(PDV_ESTADO_CONEXION.conectado)).toBe('En vivo');
        expect(etiquetaConexionTiempoRealPdv(PDV_ESTADO_CONEXION.conectando)).toBe('Reconectando');
        expect(etiquetaConexionTiempoRealPdv(PDV_ESTADO_CONEXION.desconectado)).toBe('Sin conexión');
        expect(mensajeAlertaPdv(envelopeBase())).toContain('turno');
    });
});
