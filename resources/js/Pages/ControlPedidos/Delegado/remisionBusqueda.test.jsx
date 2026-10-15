// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Index from './Index';
import { OPCIONES_BUSQUEDA_DELEGADO, placeholderBusquedaDelegado } from '../Partials/pedidosBmaStyles';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('axios', () => ({ default: { get } }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, usePage: () => ({ props: { flash: {} } }) }));
vi.mock('../../../Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('../../../Components/GeliaPageShell', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('../../../Components/GeliaTituloCard', () => ({ default: () => null }));
vi.mock('../Partials/useActualizacionesPedidos', () => ({ default: () => {} }));
vi.mock('./Partials/TablaDelegado', () => ({ default: ({ onIrAPagina }) => <button onClick={() => onIrAPagina(2)}>Página 2</button> }));
vi.mock('./Partials/MetricasBandejaDelegado', () => ({ default: () => null }));
vi.mock('./Partials/PanelImportExport', () => ({ default: () => null }));
vi.mock('../Partials/ModalAlertaPedido', () => ({ default: () => null }));

let root;
let container;
const pedidos = { data: [], total: 0, current_page: 3 };
const metricas = {};
const montar = async (filtros = {}) => {
    await act(() => root.render(<Index auth={{}} pedidos={pedidos} metricas={metricas} filtros={filtros} catalogos={{ paqueterias: [{ id: 3, nombre: 'Comercial Q7' }] }} />));
    get.mockClear();
};
const seleccionar = async (campo) => {
    await act(() => {
        const select = container.querySelector('#delegado-busqueda-campo');
        select.value = campo;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
};

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    sessionStorage.clear();
    vi.stubGlobal('route', (name) => `/${name}`);
    get.mockResolvedValue({ data: { pedidos, metricas: {} } });
    get.mockClear();
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe('Delegado: remisión e identidades documentales', () => {
    it('ofrece número de remisión y conserva folio del pedido como criterio separado', async () => {
        await montar();
        const opciones = Array.from(container.querySelectorAll('#delegado-busqueda-campo option'));
        expect(opciones.map((o) => o.value)).toEqual(['general', 'numero_remision', 'folio_remision', 'folio', 'guia', 'cliente']);
        expect(opciones.find((o) => o.value === 'numero_remision').textContent).toBe('Número de remisión');
        expect(opciones.find((o) => o.value === 'folio_remision').textContent).toBe('Folio del pedido');
        expect(OPCIONES_BUSQUEDA_DELEGADO.map((o) => o.id)).toEqual(opciones.map((o) => o.value));
    });

    it('envía q_campo al listado real de la página, conserva filtros y lo mantiene al paginar', async () => {
        await montar({ q: 'REM-00935', paqueteria_ids: [3], situacion: 'sin_guia', ordenar: 'fecha_asc' });
        await seleccionar('numero_remision');
        expect(get).toHaveBeenLastCalledWith('/control_pedidos.delegado.listado', expect.objectContaining({ params: { tab: 'PENDIENTES_GUIA', q: 'REM-00935', q_campo: 'numero_remision', paqueteria_ids: [3], situacion: 'sin_guia', ordenar: 'fecha_asc', page: 1 } }));
        expect(new URLSearchParams(window.location.search).get('q_campo')).toBe('numero_remision');
        expect(container.querySelector('#delegado-busqueda').placeholder).toBe('Número de remisión registrado por Auxiliar…');
        await act(() => Array.from(container.querySelectorAll('button')).find((b) => b.textContent === 'Página 2').click());
        expect(get.mock.lastCall[1].params).toMatchObject({ q_campo: 'numero_remision', q: 'REM-00935', page: 2, paqueteria_ids: [3] });
        await seleccionar('folio_remision');
        expect(get.mock.lastCall[1].params).toMatchObject({ q_campo: 'folio_remision', q: 'REM-00935', page: 1 });
    });

    it('recupera el criterio desde filtros del servidor y conserva su valor al actualizar', async () => {
        await montar({ q: '00935', q_campo: 'numero_remision' });
        expect(container.querySelector('#delegado-busqueda-campo').value).toBe('numero_remision');
        expect(container.querySelector('#delegado-busqueda').value).toBe('00935');
        await act(() => Array.from(container.querySelectorAll('button')).find((b) => b.textContent.includes('Actualizar')).click());
        expect(get.mock.lastCall[1].params).toMatchObject({ q_campo: 'numero_remision', q: '00935' });
        expect(placeholderBusquedaDelegado('folio_remision')).toBe('Folio documental del pedido…');
    });
});
