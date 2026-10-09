// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it } from 'vitest';
import ListaProductosOk from './ListaProductosOk';

let root;
let container;
const productos = (cantidad) => Array.from({ length: cantidad }, (_, i) => ({ id: i + 1, descripcion_producto: `Perfume ${i + 1} 100 ml`, estado_fisico: 'bueno' }));
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});
it('shows up to three OK pieces as separate list items', async () => {
    await act(() => root.render(React.createElement(ListaProductosOk, { productos: productos(3) })));
    expect(container.querySelectorAll('ul[aria-label="Productos OK"] > li')).toHaveLength(3);
    expect(container.querySelector('details')).toBeNull();
});
it('starts collapsed at four pieces, without concatenating descriptions in the summary', async () => {
    await act(() => root.render(React.createElement(ListaProductosOk, { productos: productos(4) })));
    expect(container.querySelector('details').open).toBe(false);
    expect(container.querySelector('summary').textContent).toContain('4 productos OK');
    expect(container.querySelector('summary').textContent).not.toContain('Perfume');
    expect(container.querySelectorAll('ul > li')).toHaveLength(4);
});
it('preserves repeated pieces and their distinguishing instance labels', async () => {
    const piezas = productos(4).map((p) => ({ ...p, descripcion_producto: 'Perfume repetido' }));
    await act(() => root.render(React.createElement(ListaProductosOk, { productos: piezas, etiquetaInstancia: (p) => `${p.id}/4` })));
    expect([...container.querySelectorAll('li')].map((li) => li.textContent)).toEqual([
        'Perfume repetidoPieza 1/4Bueno', 'Perfume repetidoPieza 2/4Bueno',
        'Perfume repetidoPieza 3/4Bueno', 'Perfume repetidoPieza 4/4Bueno',
    ]);
});
