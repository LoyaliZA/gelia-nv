import { describe, expect, it } from 'vitest';
import { formatearFechaNegocio } from './pedidosBmaStyles';

// Ejecutar con TZ=America/Mexico_City para cubrir el desfase que vio la operadora.
describe('fecha de negocio en pedidos', () => {
    it.each(['2026-10-09', '2026-01-01', '2024-02-29'])('conserva el día del calendario %s', (fecha) => {
        expect(formatearFechaNegocio(fecha)).toBe(fecha);
    });
    it('maneja fechas ausentes o inválidas sin mostrar datos engañosos', () => {
        expect(formatearFechaNegocio(null)).toBe('—');
        expect(formatearFechaNegocio('no es fecha')).toBe('—');
        expect(formatearFechaNegocio('2026-02-30')).toBe('—');
    });
});
