// @vitest-environment happy-dom
import { expect, it } from 'vitest';
import { resolverAlmacenBusqueda } from './ModalResponderPesaje';

const almacenes = [{ id: 1, codigo: 'VTA' }, { id: 2, codigo: 'PDV' }];
it('conserva el almacén asignado al pedido y exige elegir si no está definido', () => {
    expect(resolverAlmacenBusqueda({ almacen_id: 2 }, almacenes)).toEqual(almacenes[1]);
    expect(resolverAlmacenBusqueda({}, almacenes)).toBeNull();
    expect(resolverAlmacenBusqueda({}, [almacenes[1]])).toEqual(almacenes[1]);
});
it('no utiliza un almacén que ya no está habilitado para búsqueda', () => {
    expect(resolverAlmacenBusqueda({ almacen_id: 3, almacen: { id: 3, codigo: 'INACTIVO' } }, almacenes)).toBeNull();
});
