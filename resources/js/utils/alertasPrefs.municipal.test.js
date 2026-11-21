import { describe, expect, it } from 'vitest';
import { ALERTAS_TIPOS, DEFAULT_ALERTAS_PREFS } from './alertasPrefs';

describe('alertas del recorrido municipal', () => {
    it('registra la salida de CEDIS y la salida a terminal, activas por defecto', () => {
        expect(ALERTAS_TIPOS.pedido_salida_cedis_pdv).toBe('Salida de CEDIS hacia PDV');
        expect(ALERTAS_TIPOS.pedido_salida_terminal).toBe('Salida a terminal');
        expect(DEFAULT_ALERTAS_PREFS.tipos.pedido_salida_cedis_pdv).toBe(true);
        expect(DEFAULT_ALERTAS_PREFS.tipos.pedido_salida_terminal).toBe(true);
    });
});
