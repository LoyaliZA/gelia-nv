import { describe, it, expect } from 'vitest';
import { receptorFiscalDeFactura, enlaceFiscalVigente } from './facturasStyles';

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

import { urlArchivoFactura } from './facturasStyles';
import { vi } from 'vitest';

it('conserva el índice para revisar y descargar cada PDF de una solicitud', () => {
    vi.stubGlobal('route', () => '/facturas/1/archivo/pdf');
    expect(urlArchivoFactura(1, 'pdf', 2)).toBe('/facturas/1/archivo/pdf?indice=2');
    expect(urlArchivoFactura(1, 'pdf', 2, { descargar: true })).toBe('/facturas/1/archivo/pdf?indice=2&descargar=1');
    vi.unstubAllGlobals();
});


it('muestra únicamente el enlace vigente de la solicitud abierta', () => {
    const ahora = new Date('2026-10-12T12:00:00Z').getTime();
    const vigente = { url: '/formulario/actual', expira_en: '2026-10-13T12:00:00Z' };
    const factura = { enlaces_fiscales: [
        { url: '/vencido', expira_en: '2026-10-11T12:00:00Z' },
        { url: '/usado', usado_en: '2026-10-12T10:00:00Z' },
        { url: '/revocado', revocado_en: '2026-10-12T10:00:00Z' },
        vigente,
    ] };
    expect(enlaceFiscalVigente(factura, ahora)).toBe(vigente);
    expect(enlaceFiscalVigente(null, ahora)).toBeNull();
    expect(enlaceFiscalVigente({ enlaces_fiscales: factura.enlaces_fiscales.slice(0, 3) }, ahora)).toBeNull();
});
