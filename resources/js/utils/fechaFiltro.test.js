import { describe, it, expect } from 'vitest';
import { esFechaIsoValida, normalizarFechaAlConfirmar } from './fechaFiltro';
import {
    debeUsarVozMensajeria,
    shouldTriggerMensajeriaVoz,
    shouldTriggerChannel,
    MENSAJERIA_TIPO_ALERTA,
    resolveNotificationDestination,
} from './alertasPrefs';

describe('fechaFiltro', () => {
    it('acepta fechas ISO válidas', () => {
        expect(esFechaIsoValida('2026-05-23')).toBe(true);
    });

    it('rechaza cadenas vacías o parciales', () => {
        expect(esFechaIsoValida('')).toBe(false);
        expect(esFechaIsoValida('2026-05')).toBe(false);
        expect(normalizarFechaAlConfirmar('2026-05').ok).toBe(false);
    });

    it('acepta vacío al confirmar', () => {
        expect(normalizarFechaAlConfirmar('')).toEqual({ ok: true, valor: '' });
    });

    it('acepta ISO completo al confirmar', () => {
        expect(normalizarFechaAlConfirmar('2026-03-15')).toEqual({ ok: true, valor: '2026-03-15' });
    });
});

describe('alertasPrefs voz', () => {
    it('respeta canal voz desactivado para mensajería', () => {
        const prefs = {
            canales: { sonido: true, voz: false, escritorio: true, app: true },
            mensajeria_voz: 'leer_mensaje',
            tipos: { [MENSAJERIA_TIPO_ALERTA]: true },
        };
        expect(debeUsarVozMensajeria(prefs)).toBe(false);
        expect(shouldTriggerMensajeriaVoz(prefs)).toBe(false);
    });

    it('permite mensajería con voz activa y modo solo_aviso', () => {
        const prefs = {
            canales: { sonido: true, voz: true, escritorio: true, app: true },
            mensajeria_voz: 'solo_aviso',
            tipos: { [MENSAJERIA_TIPO_ALERTA]: true },
        };
        expect(shouldTriggerMensajeriaVoz(prefs)).toBe(true);
        expect(shouldTriggerChannel(prefs, MENSAJERIA_TIPO_ALERTA, 'sonido')).toBe(true);
    });
});

describe('resolveNotificationDestination', () => {
    it('abre la solicitud por búsqueda y no el listado genérico', () => {
        expect(resolveNotificationDestination({
            data: { solicitud_id: 42, tipo: 'nueva' },
        })).toBe('/solicitudes?q=42');
    });

    it('abre la factura por folio', () => {
        expect(resolveNotificationDestination({
            modulo: 'facturas',
            folio: 'FAC-9',
        })).toBe('/facturas?q=FAC-9');
    });

    it('abre el ticket y no el listado de soporte', () => {
        expect(resolveNotificationDestination({
            ticket_id: 9,
            url: '/soporte/mis-tickets',
        })).toBe('/soporte/mis-tickets/9');
    });

    it('abre el pedido cuando la url guardada es el módulo', () => {
        expect(resolveNotificationDestination({
            modulo: 'control_pedidos',
            folio: 'PED-1',
            url: '/control-pedidos',
        })).toBe('/control-pedidos?q=PED-1');
    });

    it('conserva el enlace concreto de punto de venta', () => {
        expect(resolveNotificationDestination({
            modulo: 'punto_venta',
            resguardo_id: 15,
            url: '/punto-venta/resguardos/15',
        })).toBe('/punto-venta/resguardos/15');
    });

    it('cae al dashboard si no hay destino', () => {
        expect(resolveNotificationDestination({})).toBe('/dashboard');
    });
});
