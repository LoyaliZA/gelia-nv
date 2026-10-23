// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Index from './Index';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }) => <a href={href}>{children}</a>,
    router: { get: vi.fn(), delete: vi.fn() },
    useForm: (initial) => ({
        data: initial,
        setData: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        processing: false,
        reset: vi.fn(),
    }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));

let root;
let container;

beforeEach(() => {
    globalThis.route = (name) => name;
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

it('explica que el archivo de costos se identifica por folio y que simular no escribe', async () => {
    await act(async () => root.render(
        <Index
            auth={{ user: { permissions: [], roles: [] } }}
            costos={{ data: [], current_page: 1, last_page: 1, total: 0 }}
            sucursales={[]}
            almacenes={[]}
            filtros={{}}
        />,
    ));

    expect(container.textContent).toContain('identifica el producto por folio');
    expect(container.textContent).toContain('Simular no aplica cambios al catálogo');
    expect(container.textContent).not.toContain('SKU + costo');
});

it('muestra eliminar sin editar cuando solo hay permiso de catálogos', async () => {
    await act(async () => root.render(
        <Index
            auth={{ user: { permissions: ['catalogos.gestionar'], roles: [] } }}
            costos={{
                data: [{
                    id: 5,
                    producto_id: 1,
                    almacen_id: 2,
                    producto: { descripcion: 'Rosa', sku: 'SKU-1' },
                    almacen: { nombre: 'Ventas' },
                    costo: 10,
                    costo_reposicion: null,
                    precio_venta: 20,
                }],
                current_page: 1,
                last_page: 1,
                from: 1,
                to: 1,
                total: 1,
            }}
            sucursales={[{ id: 1, nombre: 'Principal' }]}
            almacenes={[{ id: 2, sucursal_id: 1, codigo: 'VTA', nombre: 'Ventas' }]}
            filtros={{}}
        />,
    ));

    expect(container.querySelector('[aria-label="Editar costo"]')).toBeNull();
    expect(container.querySelector('[aria-label="Eliminar costo"]')).not.toBeNull();
});
