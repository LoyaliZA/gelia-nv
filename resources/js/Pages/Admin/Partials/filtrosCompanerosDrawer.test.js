import { describe, expect, it } from 'vitest';
import { areaPrincipalDe, catalogoNombrado, filtrarCompaneros } from './filtrosCompanerosDrawer';

const personas = [
    {
        id: 1,
        name: 'Albert',
        departamento_id: 10,
        departamentos: [{ id: 10, nombre: 'Administración' }, { id: 11, nombre: 'Control' }],
        area_id: 101,
        area: { id: 101, nombre: 'Caja' },
        sucursales: [{ id: 3, nombre: 'Centro' }],
    },
    {
        id: 2,
        name: 'Ale',
        departamento_id: 11,
        departamentos: [{ id: 11, nombre: 'Control' }],
        area_id: 102,
        areas: [{ id: 102, nombre: 'Piso' }],
        sucursales: [{ id: 4, nombre: 'Norte' }, { id: 3, nombre: 'Centro' }],
    },
    {
        id: 3,
        name: 'Sin datos',
        departamentos: [],
        sucursales: [],
    },
];

describe('filtros del cambio de colaborador', () => {
    it('arma catálogos únicos y ordenados a partir de la página visible', () => {
        const departamentos = catalogoNombrado(personas, (persona) => persona.departamentos || []);
        expect(departamentos.map((item) => item.label)).toEqual(['Administración', 'Control']);
    });

    it('usa el área principal aunque solo venga el id dentro de las áreas asignadas', () => {
        expect(areaPrincipalDe(personas[1])?.nombre).toBe('Piso');
        expect(areaPrincipalDe(personas[2])).toBeNull();
    });

    it('cruza departamento, sucursal y área principal', () => {
        expect(filtrarCompaneros(personas, { departamento: '11' }).map((persona) => persona.id)).toEqual([1, 2]);
        expect(filtrarCompaneros(personas, { sucursal: '4', area: '102' }).map((persona) => persona.id)).toEqual([2]);
        expect(filtrarCompaneros(personas, { departamento: '10', sucursal: '4' })).toEqual([]);
    });
});
