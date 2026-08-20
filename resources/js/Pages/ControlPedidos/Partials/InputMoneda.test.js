import { describe, expect, it } from 'vitest';
import { parseMonedaInput } from './InputMoneda';

describe('parseMonedaInput', () => {
    it('pega montos formateados del pedido sin alterar el valor', () => {
        expect(parseMonedaInput('$4,850.00')).toBe(4850);
        expect(parseMonedaInput('$1,234.56')).toBe(1234.56);
        expect(parseMonedaInput('MXN $12,500.00')).toBe(12500);
        expect(parseMonedaInput('1,234.56')).toBe(1234.56);
    });

    it('acepta entrada manual y formato europeo', () => {
        expect(parseMonedaInput('4850')).toBe(4850);
        expect(parseMonedaInput('4850.50')).toBe(4850.5);
        expect(parseMonedaInput('1.234,56')).toBe(1234.56);
        expect(parseMonedaInput('2.350,50')).toBe(2350.5);
        expect(parseMonedaInput('')).toBe('');
    });
});
