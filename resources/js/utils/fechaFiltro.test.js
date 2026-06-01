import { describe, it, expect } from 'vitest';
import { esFechaIsoValida, normalizarFechaAlConfirmar } from './fechaFiltro';
import {
    debeUsarVozMensajeria,
    shouldTriggerMensajeriaVoz,
    shouldTriggerChannel,
    MENSAJERIA_TIPO_ALERTA,
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
