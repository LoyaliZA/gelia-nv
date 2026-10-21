import { expect, it } from 'vitest';
import { paramsFichaProducto } from './fichaProducto';

it('arma el retorno solo con parámetros internos', () => {
    const params = paramsFichaProducto(8, {
        desde: 'cobertura',
        sucursal_id: 2,
        almacen_id: 4,
        q: 'rosa',
        url: 'https://ejemplo.invalid',
        redirect: '/otro',
        page: '',
    });

    expect(params).toEqual({
        producto: 8,
        desde: 'cobertura',
        sucursal_id: 2,
        almacen_id: 4,
        q: 'rosa',
    });
});
