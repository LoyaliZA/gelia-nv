import { describe, expect, it } from 'vitest';
import { calcCostoSeguro, distribuirSeguroEnLineas, paqueteriaTieneCobertura } from './pedidosBmaStyles';

describe('seguro de paquetería', () => {
    it('calcula FedEx y DHL y omite transporte sin cobertura', () => {
        expect(paqueteriaTieneCobertura('FEDEX')).toBe(true);
        expect(calcCostoSeguro('FEDEX', 200, 1000)).toBe(30);
        expect(calcCostoSeguro('DHL', 100, 400)).toBe(61);
        expect(calcCostoSeguro('TAXI FRONTERA', 100, 400)).toBe(0);
    });

    it('no escribe seguro si falta el costo de algún envío', () => {
        const lineas = distribuirSeguroEnLineas([
            { uuid_operativo: '11111111-1111-1111-1111-111111111111', costo_envio: 80, costo_seguro: 10 },
            { uuid_operativo: '22222222-2222-2222-2222-222222222222', costo_envio: '', costo_seguro: 5 },
        ], 30);
        expect(lineas.every((row) => !Object.prototype.hasOwnProperty.call(row, 'costo_seguro'))).toBe(true);
    });

    it('pone el total calculado en la primera línea cuando el desglose está completo', () => {
        const lineas = distribuirSeguroEnLineas([
            { uuid_operativo: '11111111-1111-1111-1111-111111111111', costo_envio: 80 },
            { uuid_operativo: '22222222-2222-2222-2222-222222222222', costo_envio: 20 },
        ], 30);
        expect(lineas.map((row) => row.costo_seguro)).toEqual([30, 0]);
    });
});
