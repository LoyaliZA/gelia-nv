import { describe, expect, it } from 'vitest';
import {
    elegirDireccionParaPedido,
    manualDireccionCompleta,
    faltantesManualDireccion,
} from './elegirDireccionParaPedido';

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

describe('manualDireccionCompleta', () => {
    it('exige destinatario, estado y domicilio regular', () => {
        expect(manualDireccionCompleta({
            nombre_destinatario: 'Ana',
            estado: 'Jalisco',
            calle: 'Morelos',
            colonia: 'Centro',
            codigo_postal: '44100',
            municipio: 'Guadalajara',
        })).toBe(true);
        expect(manualDireccionCompleta({
            nombre_destinatario: 'Ana',
            estado: 'Jalisco',
            calle: 'Morelos',
            colonia: 'Centro',
            codigo_postal: '441',
            municipio: 'Guadalajara',
        })).toBe(false);
    });

    it('lista faltantes', () => {
        expect(faltantesManualDireccion({})).toEqual(expect.arrayContaining([
            'nombre del destinatario',
            'estado',
            'calle',
        ]));
    });
});
