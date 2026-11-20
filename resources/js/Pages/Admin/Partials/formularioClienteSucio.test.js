import { describe, expect, it } from 'vitest';
import { formularioClienteSucio } from './formularioClienteSucio';

describe('formularioClienteSucio', () => {
    const inicial = { monto_venta_actual: 1000, lista_actual_id: 4, nombre: 'Ana' };

    it('ignora el mismo valor con otro tipo', () => {
        expect(formularioClienteSucio({ ...inicial, monto_venta_actual: '1000', lista_actual_id: '4' }, inicial)).toBe(false);
    });

    it('detecta un monto distinto', () => {
        expect(formularioClienteSucio({ ...inicial, monto_venta_actual: '2500' }, inicial)).toBe(true);
    });
});
