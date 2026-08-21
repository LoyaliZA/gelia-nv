import { describe, expect, it } from 'vitest';
import { elegirDireccionParaPedido } from './elegirDireccionParaPedido';

describe('elegirDireccionParaPedido', () => {
    const dirs = [
        { id: 2, es_principal: false },
        { id: 1, es_principal: true },
        { id: 3, es_principal: false },
    ];

    it('devuelve null sin lista', () => {
        expect(elegirDireccionParaPedido([])).toBeNull();
        expect(elegirDireccionParaPedido(null)).toBeNull();
    });

    it('respeta direccionId si existe', () => {
        expect(elegirDireccionParaPedido(dirs, { direccionId: 3 })?.id).toBe(3);
    });

    it('prefiere principal sobre la primera', () => {
        expect(elegirDireccionParaPedido(dirs)?.id).toBe(1);
    });

    it('si no hay principal usa la primera', () => {
        const solo = [{ id: 9, es_principal: false }, { id: 8, es_principal: false }];
        expect(elegirDireccionParaPedido(solo)?.id).toBe(9);
    });
});
