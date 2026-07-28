import { describe, it, expect } from 'vitest';
import { receptorFiscalDeFactura } from './facturasStyles';

describe('receptorFiscalDeFactura', () => {
    it('lee receptor_fiscal (snake_case, como lo serializa Eloquent)', () => {
        const factura = { receptor_fiscal: { id: 1, codigo_interno: 'TF-000001' } };
        expect(receptorFiscalDeFactura(factura)).toEqual({ id: 1, codigo_interno: 'TF-000001' });
    });

    it('acepta receptorFiscal (camelCase) como respaldo', () => {
        const factura = { receptorFiscal: { id: 2, codigo_interno: 'TF-000002' } };
        expect(receptorFiscalDeFactura(factura)).toEqual({ id: 2, codigo_interno: 'TF-000002' });
    });

    it('regresa null si no hay receptor', () => {
        expect(receptorFiscalDeFactura({})).toBeNull();
        expect(receptorFiscalDeFactura(null)).toBeNull();
    });
});
