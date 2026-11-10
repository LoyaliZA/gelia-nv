import { expect, it } from 'vitest';
import { importesListosCaptura } from './FormularioRegistrarTienda';

const confirmados = { total_mercancia: 0, saldo_a_favor: 0 };
const base = { saldoCapturado: 0, confirmados, conflicto: '', estadoGuardado: 'guardado' };

it('crédito habilita con importe capturado y bloquea el cero aunque ya esté guardado', () => {
    expect(importesListosCaptura({ ...base, cobertura: 'credito', totalMercancia: 8699, confirmados: { total_mercancia: 0, saldo_a_favor: 0 }, estadoGuardado: 'pendiente' })).toBe(true);
    expect(importesListosCaptura({ ...base, cobertura: 'credito', totalMercancia: 0 })).toBe(false);
    expect(importesListosCaptura({ ...base, cobertura: 'credito', totalMercancia: '' })).toBe(false);
    expect(importesListosCaptura({ ...base, cobertura: 'credito', totalMercancia: 100, estadoGuardado: 'error' })).toBe(true);
    expect(importesListosCaptura({ ...base, cobertura: 'credito', totalMercancia: 100, conflicto: 'Otra persona guardó este pedido.' })).toBe(false);
});

it('transferencia sigue exigiendo el importe ya guardado', () => {
    expect(importesListosCaptura({ ...base, cobertura: 'transferencia', totalMercancia: 8699, confirmados: { total_mercancia: 8699, saldo_a_favor: 0 } })).toBe(true);
    expect(importesListosCaptura({ ...base, cobertura: 'transferencia', totalMercancia: 8699, confirmados: { total_mercancia: 0, saldo_a_favor: 0 }, estadoGuardado: 'pendiente' })).toBe(false);
    expect(importesListosCaptura({ ...base, cobertura: 'apartado', totalMercancia: 0 })).toBe(true);
});
