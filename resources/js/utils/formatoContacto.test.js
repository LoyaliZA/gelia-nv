import { describe, expect, it } from 'vitest';
import { formatearTelefono, soloDigitos } from './formatoContacto';

describe('formatearTelefono', () => {
    it('agrupa 10 dígitos como 55 1234 5678', () => {
        expect(formatearTelefono('5512345678')).toBe('55 1234 5678');
    });

    it('normaliza un número pegado con lada, paréntesis y guiones', () => {
        expect(formatearTelefono('+52 (55) 1234-5678')).toBe('55 1234 5678');
    });

    it('formatea de forma parcial mientras se teclea', () => {
        expect(formatearTelefono('5')).toBe('5');
        expect(formatearTelefono('551')).toBe('55 1');
        expect(formatearTelefono('5512345')).toBe('55 1234 5');
    });

    it('ignora letras y recorta a 10 dígitos', () => {
        expect(formatearTelefono('55abc12345678999')).toBe('55 1234 5678');
        expect(formatearTelefono('')).toBe('');
    });
});

describe('soloDigitos', () => {
    it('conserva solo dígitos y respeta el máximo', () => {
        expect(soloDigitos('06A00-1', 5)).toBe('06001');
        expect(soloDigitos('0600123', 5)).toBe('06001');
        expect(soloDigitos(null, 5)).toBe('');
    });
});
