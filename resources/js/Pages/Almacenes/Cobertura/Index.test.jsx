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

it('muestra la ubicación de asignación y oculta Asignar a quien solo consulta', async () => {
    await act(async () => root.render(
        <Index
            auth={{ user: { permissions: ['almacenes.inventarios.ver'], roles: [] } }}
            filas={{
                data: [{
                    id: 7,
                    descripcion: 'Asignado',
                    sku: 'SKU-ASIG',
                    asignado: true,
                    ubicacion: 'A-1',
                    costo: null,
                    tiene_inventario: false,
                    tiene_cantidad: false,
                    existencia: null,
                }],
                current_page: 1,
                last_page: 1,
                total: 1,
            }}
            sucursales={[{ id: 1, nombre: 'Principal' }]}
            almacenes={[{ id: 4, sucursal_id: 1, codigo: 'VTA', nombre: 'Ventas' }]}
            filtros={{ sucursal_id: 1, almacen_id: 4 }}
        />,
    ));

    expect(container.textContent).toContain('Ubicación de asignación A-1');
    expect(container.textContent).toContain('independiente de la ubicación de inventario');
    expect(container.textContent).not.toContain('Asignar');
});

it('muestra Asignar cuando la persona puede gestionar inventarios', async () => {
    await act(async () => root.render(
        <Index
            auth={{ user: { permissions: ['almacenes.inventarios.gestionar'], roles: [] } }}
            filas={filas}
            sucursales={[{ id: 1, nombre: 'Principal' }]}
            almacenes={[{ id: 4, sucursal_id: 1, codigo: 'VTA', nombre: 'Ventas' }]}
            filtros={{ sucursal_id: 1, almacen_id: 4 }}
        />,
    ));

    const casilla = container.querySelector('input[type="checkbox"]');
    expect(casilla).not.toBeNull();
    await act(async () => casilla.click());

    expect(container.textContent).toContain('Asignar 1 al almacén');
});
