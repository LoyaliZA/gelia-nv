// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Index from './Index';

const router = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }) => <a href={href}>{children}</a>,
    router,
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));

let root;
let container;

const filas = {
    data: [{
        id: 1,
        descripcion: 'Producto de prueba',
        sku: 'SKU-1',
        asignado: false,
        costo: null,
        tiene_inventario: false,
        tiene_cantidad: false,
        existencia: null,
    }],
    current_page: 1,
    last_page: 3,
    per_page: 50,
    from: 1,
    to: 50,
    total: 120,
};

beforeEach(() => {
    globalThis.route = (name) => name;
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    router.get.mockReset();
    router.post.mockReset();
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

it('consulta la página siguiente y conserva el contexto de productos por almacén', async () => {
    await act(async () => root.render(
        <Index
            auth={{ user: { permissions: [], roles: [] } }}
            filas={filas}
            sucursales={[{ id: 1, nombre: 'Principal' }]}
            almacenes={[{ id: 4, sucursal_id: 1, codigo: 'VTA', nombre: 'Ventas' }]}
            filtros={{ sucursal_id: 1, almacen_id: 4, q: 'rosa', asignacion: 'sin', costo: 'sin', activo: '1' }}
        />,
    ));

    const siguiente = container.querySelector('[aria-label="Página siguiente"]');
    expect(siguiente).not.toBeNull();
    expect(siguiente.disabled).toBe(false);
    expect(container.textContent).toContain('120');

    await act(async () => siguiente.click());

    expect(router.post).not.toHaveBeenCalled();
    expect(router.get).toHaveBeenCalledTimes(1);
    expect(router.get).toHaveBeenCalledWith('almacenes.cobertura.index', {
        sucursal_id: 1,
        almacen_id: 4,
        q: 'rosa',
        asignacion: 'sin',
        costo: 'sin',
        activo: '1',
        page: 2,
    }, { preserveState: true });
});
