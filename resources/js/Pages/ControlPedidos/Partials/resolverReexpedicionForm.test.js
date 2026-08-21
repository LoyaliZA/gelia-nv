/**
 * @vitest-environment node
 */
import { describe, expect, it } from 'vitest';
import {
    resolverReexpedicionForm,
    separarCostoEnvioDeReexpedicion,
    costoEnvioParaPersistir,
} from './resolverReexpedicionForm';

describe('resolverReexpedicionForm', () => {
    const zonas = [
        { id: 1, nombre: 'Sin reexpedición' },
        { id: 2, nombre: 'Con reexpedición' },
    ];
    const reexpediciones = [
        { codigo_postal: '64000', paqueteria_id: 9, costo_adicional: 150 },
    ];

    it('devuelve cargo aparte sin tocar un costo_envio', () => {
        const r = resolverReexpedicionForm({
            codigoPostal: '64000',
            paqueteriaId: 9,
            reexpediciones,
            zonas,
        });
        expect(r.costoAplicado).toBe(150);
        expect(r.zonaId).toBe(2);
        expect(r.matchKey).toBe('64000|9');
        expect(r.costoEnvio).toBeUndefined();
    });

    it('sin match: zona sin y cargo 0', () => {
        const r = resolverReexpedicionForm({
            codigoPostal: '99999',
            paqueteriaId: 9,
            reexpediciones,
            zonas,
        });
        expect(r.costoAplicado).toBe(0);
        expect(r.zonaId).toBe(1);
        expect(r.matchKey).toBeNull();
    });
});

describe('separar / persistir costo', () => {
    it('separa flete histórico mezclado', () => {
        expect(separarCostoEnvioDeReexpedicion(350, 150)).toEqual({ base: 200, reexpedicion: 150 });
    });

    it('persiste suma para BD', () => {
        expect(costoEnvioParaPersistir(200, 150)).toBe(350);
        expect(costoEnvioParaPersistir('', 150)).toBe(150);
    });
});
