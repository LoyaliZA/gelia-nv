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

const base = {
    sucursales: [{ id: 1, nombre: 'Principal' }],
    almacenes: [{ id: 4, sucursal_id: 1, codigo: 'VTA', nombre: 'Ventas' }],
    filtros: {},
    inventarios: {
        data: [{
            id: 3,
            producto_id: 9,
            almacen_id: 4,
            producto: { descripcion: 'Rosa', folio: 100, sku: 'SKU-1' },
            almacen: { nombre: 'Ventas' },
            ubicacion: 'B-2',
            existencia: 1,
            apartado: 0,
            disponible: 1,
            costo_valor: null,
            precio_venta_valor: null,
            transito_oc: null,
            transito_ot: null,
        }],
        current_page: 1,
        last_page: 1,
        from: 1,
        to: 1,
        total: 1,
    },
};

beforeEach(() => {
    globalThis.route = (name, params) => `${name}${params ? `:${JSON.stringify(params)}` : ''}`;
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

it('rotula Carga completa y la ubicación de inventario cuando el caso está permitido', async () => {
    await act(async () => root.render(
        <Index
            auth={{ user: { permissions: ['almacenes.inventarios.gestionar'], roles: [] } }}
            puede_carga_completa
            {...base}
        />,
    ));

    expect(container.textContent).toContain('Carga completa');
    expect(container.textContent).toContain('puede incluir ficha, asignación, costos, precios y existencias');
    expect(container.textContent).toContain('Ubicación de inventario');
    expect(container.querySelector('a')?.getAttribute('href')).toContain('preset":"inventario"');
    expect(container.querySelector('[aria-label="Editar inventario"]')).not.toBeNull();
    expect(container.querySelector('[aria-label="Eliminar inventario"]')).not.toBeNull();
});

it('oculta Carga completa y la edición cuando solo se puede borrar por catálogos', async () => {
    await act(async () => root.render(
        <Index
            auth={{ user: { permissions: ['catalogos.gestionar'], roles: [] } }}
            puede_carga_completa={false}
            {...base}
        />,
    ));

    expect(container.textContent).not.toContain('Carga completa');
    expect(container.querySelector('[aria-label="Editar inventario"]')).toBeNull();
    expect(container.querySelector('[aria-label="Eliminar inventario"]')).not.toBeNull();
});
