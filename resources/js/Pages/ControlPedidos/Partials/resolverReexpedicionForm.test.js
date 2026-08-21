/**
 * @vitest-environment node
 */
import { describe, expect, it } from 'vitest';
import {
    resolverReexpedicionForm,
    separarCostoEnvioDeReexpedicion,
    costoEnvioParaPersistir,
    costoReexpedicionDeZona,
} from './resolverReexpedicionForm';

describe('resolverReexpedicionForm — cargo por zona', () => {
    const zonas = [
        { id: 1, nombre: 'Sin reexpedición', costo_adicional: 0 },
        { id: 2, nombre: 'Con reexpedición', costo_adicional: 150 },
    ];
    const reexpediciones = [
        { codigo_postal: '64000', paqueteria_id: 9, costo_adicional: 85 },
    ];

    it('elige Con reexpedición a mano → 150 aunque CP no esté en catálogo', () => {
        const r = resolverReexpedicionForm({
            codigoPostal: '99999',
            paqueteriaId: 9,
            reexpediciones,
            zonas,
            zonaIdSeleccionada: 2,
        });
        expect(r.costoAplicado).toBe(150);
        expect(r.matchKey).toBeNull();
        expect(r.zonaIdSugerida).toBe(1);
    });

    it('match CP sugiere Con; monto sigue siendo el de la zona (150), no el del catálogo CP', () => {
        const r = resolverReexpedicionForm({
            codigoPostal: '64000',
            paqueteriaId: 9,
            reexpediciones,
            zonas,
            zonaIdSeleccionada: '',
        });
        expect(r.matchKey).toBe('64000|9');
        expect(r.zonaIdSugerida).toBe(2);
        expect(r.costoAplicado).toBe(150);
    });

    it('Sin reexpedición → 0', () => {
        const r = resolverReexpedicionForm({
            codigoPostal: '64000',
            paqueteriaId: 9,
            reexpediciones,
            zonas,
            zonaIdSeleccionada: 1,
        });
        expect(r.costoAplicado).toBe(0);
    });
});

describe('helpers', () => {
    it('costoReexpedicionDeZona', () => {
        expect(costoReexpedicionDeZona([{ id: 2, costo_adicional: 150 }], 2)).toBe(150);
        expect(costoReexpedicionDeZona([{ id: 1, costo_adicional: 0 }], 1)).toBe(0);
    });

    it('separa / persiste', () => {
        expect(separarCostoEnvioDeReexpedicion(350, 150)).toEqual({ base: 200, reexpedicion: 150 });
        expect(costoEnvioParaPersistir(200, 150)).toBe(350);
    });
});
