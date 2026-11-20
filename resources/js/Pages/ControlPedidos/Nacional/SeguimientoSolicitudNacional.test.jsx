// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import SeguimientoSolicitudNacional from './SeguimientoSolicitudNacional';

vi.mock('axios', () => ({ default: { post: vi.fn(), get: vi.fn() } }));
vi.mock('../Partials/AjustePiezas', () => ({ AjustePiezas: () => null }));

const pedido = {
    id: 9,
    pesaje_respondido_at: '2026-11-20T12:00:00Z',
    puede_cerrar_consulta: true,
    version_consulta: 15,
    revisiones_producto: [{ id: 1, sku: 'SKU-NAC', descripcion_producto: 'Aroma', cantidad_encontrada: 1 }],
};
let root;
let host;

beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    axios.post.mockReset();
    axios.get.mockReset();
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

function botonCerrar() {
    return [...document.querySelectorAll('button')].find((boton) => boton.textContent.includes('Cerrar consulta'));
}

async function render(onPedidoActualizado = vi.fn(), extra = {}) {
    await act(async () => {
        root.render(<SeguimientoSolicitudNacional pedido={{ ...pedido, ...extra }} onPedidoActualizado={onPedidoActualizado} />);
    });

    return onPedidoActualizado;
}

it('cierra la consulta con el contexto nacional y publica la lectura nueva', async () => {
    const lectura = { id: 9, consulta_cerrada: true, consulta_cerrada_at: '2026-11-20T13:00:00Z', version_consulta: 16 };
    axios.post.mockResolvedValue({ data: { ok: true, consulta_cerrada: true } });
    axios.get.mockResolvedValue({ data: lectura });
    const onPedidoActualizado = await render();
    expect(botonCerrar().disabled).toBe(false);
    await act(async () => { botonCerrar().click(); });
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/cerrar-consulta',
        { modalidad_esperada: 'nacional', version_consulta: 15 },
        expect.anything(),
    );
    expect(axios.get).toHaveBeenCalledWith('/control-pedidos/9/consulta-nacional', expect.anything());
    expect(onPedidoActualizado).toHaveBeenCalledWith(lectura);
});

it('muestra el conflicto y no da la consulta por cerrada', async () => {
    axios.post.mockRejectedValue({ response: { status: 409, data: { message: 'La consulta cambió. Actualice para continuar.', codigo: 'vista_atrasada' } } });
    const onPedidoActualizado = await render();
    await act(async () => { botonCerrar().click(); });
    expect(document.body.textContent).toContain('La consulta cambió. Actualice para continuar.');
    expect(document.body.textContent).not.toContain('Ventas registró el cierre');
    expect(onPedidoActualizado).not.toHaveBeenCalled();
    expect(axios.get).not.toHaveBeenCalled();
});
