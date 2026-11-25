// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest';
import {
    esEcoDeEstaSesion,
    etapaAlReabrir,
    guardarEtapaUi,
    importeNetoRestante,
    deltaPendienteExhibicion,
    costoEnvioParaResumenVivo,
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

    it('al editar una exhibición suma el monto ya contado para no omitir el envío', () => {
        // Mercancía 2156 ya exhibida; faltan 50 de transporte.
        expect(importeNetoRestante({ pendiente: 50 }, 2156)).toBe(2206);
        expect(importeNetoRestante({ pendiente: 2206 })).toBe(2206);
        expect(importeNetoRestante({ pendiente: 50 }, 0)).toBe(50);
        expect(deltaPendienteExhibicion({ pendiente: 50 }, { montoPropuesto: 2206, montoYaContado: 2156 })).toBe(0);
        expect(deltaPendienteExhibicion({ pendiente: 50 }, { montoPropuesto: 50, montoYaContado: 0 })).toBe(0);
        expect(deltaPendienteExhibicion({ pendiente: 50 }, { montoPropuesto: 2156, montoYaContado: 2156 })).toBe(50);
    });

    it('recupera el envío del resumen si el formulario no lo trae', () => {
        const resumen = { total_a_cubrir: 2206, totales_envio: { costo_para_cobertura: '50.00' } };
        expect(costoEnvioParaResumenVivo({ costoEnvio: null, totalMercancia: 2156, resumen })).toBe(50);
        expect(costoEnvioParaResumenVivo({ costoEnvio: 0, totalMercancia: 2156, resumen })).toBe(50);
        expect(costoEnvioParaResumenVivo({ costoEnvio: '', totalMercancia: 2156, resumen: { total_a_cubrir: 2206 } })).toBe(50);
        expect(costoEnvioParaResumenVivo({ costoEnvio: 50, totalMercancia: 2156, resumen })).toBe(50);
        expect(costoEnvioParaResumenVivo({ omiteEnvio: true, costoEnvio: 50, resumen })).toBe(0);
        expect(costoEnvioParaResumenVivo({ costoEnvio: 0, totalMercancia: 2156, resumen: { total_a_cubrir: 2156 } })).toBe(0);
    });
});
