import { expect, it } from 'vitest';
import { completarRevisionesPesajeNacional, excepcionesDesdeRevisiones } from './completarRevisionesPesajeNacional';

const pieza = (id, sku, cantidad = 1) => ({ id, sku, descripcion: `Pieza ${sku}`, cantidad });

it('Todo OK confirma cada UUID vigente como Bueno', () => {
    const resultado = completarRevisionesPesajeNacional([
        pieza('u-a', 'SKU-A', 2),
        pieza('u-b', 'SKU-B'),
    ], [], true);
    expect(resultado).toHaveLength(2);
    expect(resultado.map(r => r.client_uuid)).toEqual(['u-a', 'u-b']);
    expect(resultado.every(r => r.estado_fisico === 'bueno')).toBe(true);
    expect(resultado[0].cantidad_encontrada).toBe(2);
});

it('un faltante deja el resto en Bueno y el UUID en sin existencias', () => {
    const resultado = completarRevisionesPesajeNacional(
        [pieza('u-a', 'SKU-A'), pieza('u-b', 'SKU-B')],
        [{ sku: 'SKU-A', condicion: 'faltante', descripcion: 'Pieza SKU-A', cantidad: 1 }],
        false,
    );
    const porId = Object.fromEntries(resultado.map(r => [r.client_uuid, r]));
    expect(porId['u-a'].estado_fisico).toBe('sin_existencia');
    expect(porId['u-a'].cantidad_encontrada).toBe(0);
    expect(porId['u-b'].estado_fisico).toBe('bueno');
    expect(porId['u-b'].cantidad_encontrada).toBe(1);
});

it('un detalle marca regular y un SKU no solicitado queda como revisión extra', () => {
    const resultado = completarRevisionesPesajeNacional(
        [pieza('u-a', 'SKU-A')],
        [
            { sku: 'SKU-A', condicion: 'detalle', descripcion: 'Pieza SKU-A', detalle: 'Tapa rota', archivos: ['f'] },
            { sku: 'SKU-X', condicion: 'faltante', descripcion: 'No pedida' },
        ],
        false,
    );
    expect(resultado[0].estado_fisico).toBe('regular');
    expect(resultado[0].comentario).toBe('Tapa rota');
    expect(resultado[1].sku).toBe('SKU-X');
    expect(resultado[1].estado_fisico).toBe('sin_existencia');
    expect(resultado[1].piezaSolicitada).toBe(false);
});

it('excepcionesDesdeRevisiones solo expone las no Bueno', () => {
    const extra = excepcionesDesdeRevisiones([
        { estado_fisico: 'bueno', descripcion_producto: 'A' },
        { estado_fisico: 'sin_existencia', descripcion_producto: 'B', sku: 'B', client_uuid: 'u-b' },
    ]);
    expect(extra).toHaveLength(1);
    expect(extra[0].condicion).toBe('faltante');
    expect(extra[0].client_uuid).toBe('u-b');
});
