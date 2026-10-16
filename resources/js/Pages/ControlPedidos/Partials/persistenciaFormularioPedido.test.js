// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest';
import {
    esEcoDeEstaSesion,
    etapaAlReabrir,
    guardarEtapaUi,
    importeNetoRestante,
    mismaMarcaTiempo,
} from './persistenciaFormularioPedido';

describe('eco del autoguardado', () => {
    it('trata como propio el listado con la base confirmada aunque la fecha use otro formato', () => {
        expect(mismaMarcaTiempo('2026-10-16T19:00:00+00:00', '2026-10-16T19:00:00.000000Z')).toBe(true);
        expect(esEcoDeEstaSesion({
            pedido: { base_captura: 'abc', revision_registro: 3, updated_at: '2026-10-16T19:00:00.000000Z' },
            baseConfirmada: { base_captura: 'abc', revision_esperada: 3 },
            updatedAtEsperado: '2026-10-16T19:00:00+00:00',
        })).toBe(true);
    });

    it('no trata como propio un guardado ajeno del mismo segundo', () => {
        expect(esEcoDeEstaSesion({
            pedido: { base_captura: 'ajena', revision_registro: 4, updated_at: '2026-10-16T19:00:00+00:00' },
            baseConfirmada: { base_captura: 'propia', revision_esperada: 3 },
            updatedAtEsperado: '2026-10-16T19:00:00+00:00',
        })).toBe(false);
    });
});

describe('etapa de interfaz', () => {
    it('recupera la etapa guardada y, si ya no es válida, la última disponible', () => {
        guardarEtapaUi(7, 41, 'pago');
        expect(etapaAlReabrir(7, 41, ['datos', 'cotizacion', 'pago']).codigo).toBe('pago');
        const caida = etapaAlReabrir(7, 41, ['datos', 'respuesta']);
        expect(caida.codigo).toBe('respuesta');
        expect(caida.aviso).toMatch(/última etapa válida/);
    });
});

describe('importe neto restante', () => {
    it('usa el pendiente ya calculado, en centavos y nunca negativo', () => {
        expect(importeNetoRestante({ pendiente: 1620.48 })).toBe(1620.48);
        expect(importeNetoRestante({ pendiente: 1620.484 })).toBe(1620.48);
        expect(importeNetoRestante({ pendiente: -5 })).toBe(0);
        expect(importeNetoRestante(null)).toBe(0);
    });
});
