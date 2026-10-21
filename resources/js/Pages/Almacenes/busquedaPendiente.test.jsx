// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Costos from './Costos/Index';
import Inventarios from './Inventarios/Index';

const router = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }) => <a href={href}>{children}</a>,
    router,
    useForm: () => ({
        data: {},
        setData: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        processing: false,
        reset: vi.fn(),
        errors: {},
    }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));

const sucursales = [
    { id: '1', nombre: 'Sucursal A' },
    { id: '2', nombre: 'Sucursal B' },
];
const almacenes = [
    { id: '10', sucursal_id: '1', codigo: 'A1', nombre: 'Almacen A' },
    { id: '11', sucursal_id: '1', codigo: 'A2', nombre: 'Almacen A2' },
    { id: '20', sucursal_id: '2', codigo: 'B1', nombre: 'Almacen B' },
];

let root;
let container;

function escribir(valor) {
    const input = container.querySelector('input[placeholder="Buscar por nombre o código..."]');
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, valor);
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function cambiarSelect(indice, valor) {
    const select = container.querySelectorAll('select')[indice];
    Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set.call(select, valor);
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

function parametrosEnviados() {
    return router.get.mock.calls.map((llamada) => llamada[1]);
}

function renderPantalla(Pantalla) {
    return act(() => root.render(
        <Pantalla
            auth={{ user: { permissions: [], roles: [] } }}
            costos={{ data: [] }}
            inventarios={{ data: [] }}
            sucursales={sucursales}
            almacenes={almacenes}
            filtros={{ sucursal_id: '1', almacen_id: '10', q: '' }}
        />,
    ));
}

describe.each([
    ['costos', Costos, 'almacenes.costos.index'],
    ['inventarios', Inventarios, 'almacenes.inventarios.index'],
])('%s', (nombre, Pantalla, ruta) => {
    beforeEach(() => {
        globalThis.route = (name) => name;
        globalThis.IS_REACT_ACT_ENVIRONMENT = true;
        vi.useFakeTimers();
        router.get.mockReset();
        container = document.createElement('div');
        document.body.append(container);
        root = createRoot(container);
    });

    afterEach(async () => {
        if (root) await act(() => root.unmount());
        container.remove();
        root = null;
        vi.useRealTimers();
    });

    it('no consulta el contexto anterior si la sucursal cambia antes de la espera', async () => {
        await renderPantalla(Pantalla);
        await act(() => escribir('sku'));
        await act(() => cambiarSelect(0, '2'));

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get).toHaveBeenCalledWith(ruta, expect.objectContaining({
            sucursal_id: '2',
            q: 'sku',
            page: 1,
        }), expect.objectContaining({ preserveState: true, replace: true }));
        expect(parametrosEnviados()[0].almacen_id).toBeUndefined();

        await act(() => vi.advanceTimersByTime(400));

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(JSON.stringify(parametrosEnviados())).not.toContain('"sucursal_id":"1"');
        expect(JSON.stringify(parametrosEnviados())).not.toContain('"almacen_id":"10"');
    });

    it('no consulta el almacén anterior si cambia antes de la espera', async () => {
        await renderPantalla(Pantalla);
        await act(() => escribir('sku'));
        await act(() => cambiarSelect(1, '11'));

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get).toHaveBeenCalledWith(ruta, expect.objectContaining({
            sucursal_id: '1',
            almacen_id: '11',
            q: 'sku',
            page: 1,
        }), expect.any(Object));

        await act(() => vi.advanceTimersByTime(400));

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(JSON.stringify(parametrosEnviados())).not.toContain('"almacen_id":"10"');
    });

    it('envía la búsqueda al cumplirse la espera si el contexto no cambia', async () => {
        await renderPantalla(Pantalla);
        await act(() => escribir('sku'));
        await act(() => vi.advanceTimersByTime(399));
        expect(router.get).not.toHaveBeenCalled();

        await act(() => vi.advanceTimersByTime(1));

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get).toHaveBeenCalledWith(ruta, expect.objectContaining({
            sucursal_id: '1',
            almacen_id: '10',
            q: 'sku',
            page: 1,
        }), expect.objectContaining({ preserveState: true, replace: true }));
    });

    it('no envía una búsqueda pendiente después de desmontar', async () => {
        await renderPantalla(Pantalla);
        await act(() => escribir('sku'));
        await act(() => root.unmount());
        root = null;

        await act(() => vi.advanceTimersByTime(400));

        expect(router.get).not.toHaveBeenCalled();
    });
});
