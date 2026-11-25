// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import SeccionCaratulaMunicipalCedis from './SeccionCaratulaMunicipalCedis';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

let root;
let host;

afterEach(async () => {
    if (root) await act(() => root.unmount());
    host?.remove();
});

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (name, id) => `/${name}/${id}`;
    axios.get.mockReset();
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});

const pedidoMunicipal = {
    id: 26563,
    solicitud_preparacion: {
        modalidad_entrega: 'municipal',
        expediente: { nombre: 'Jesus Garcia Ramirez', telefono: '9933831337' },
        finanzas_municipales: { leyenda_terminal: 'PAGADO' },
    },
    caratula_municipal: {
        nombre: 'Jesus Garcia Ramirez',
        telefono: '9933831337',
        leyenda: 'PAGADO',
        tiene_consignatario: true,
        tiene_identificacion: false,
    },
};

async function montar(pedido = pedidoMunicipal, onVer = vi.fn()) {
    await act(() => root.render(<SeccionCaratulaMunicipalCedis pedido={pedido} onVer={onVer} />));
    return onVer;
}

const boton = (texto) => [...document.querySelectorAll('button')].find((el) => el.textContent.includes(texto));

it('muestra consignatario, teléfono y las dos carátulas', async () => {
    await montar();
    expect(document.body.textContent).toContain('Jesus Garcia Ramirez');
    expect(document.body.textContent).toContain('9933831337');
    expect(document.body.textContent).toContain('Pagado');
    expect(boton('Ver carátula con identificación')).toBeTruthy();
    expect(boton('Ver carátula sin identificación')).toBeTruthy();
});

it('sin archivo de identificación avisa y no pide el PDF', async () => {
    await montar();
    await act(() => boton('Ver carátula con identificación').click());
    expect(axios.get).not.toHaveBeenCalled();
    expect(document.body.textContent).toContain('Este consignatario no tiene archivo de identificación.');
});

it('abre la carátula sin identificación en el visor', async () => {
    const pdf = new Blob(['%PDF'], { type: 'application/pdf' });
    axios.get.mockResolvedValue({ data: pdf, headers: { 'content-type': 'application/pdf' } });
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:caratula');
    const onVer = await montar();
    await act(async () => {
        boton('Ver carátula sin identificación').click();
    });
    expect(axios.get).toHaveBeenCalledWith(
        '/control_pedidos.cedis.caratula_municipal/26563?variante=sin_identificacion',
        expect.objectContaining({ responseType: 'blob' }),
    );
    expect(onVer).toHaveBeenCalledWith(expect.objectContaining({
        url: 'blob:caratula',
        tipo: 'caratula_municipal',
        mime_type: 'application/pdf',
    }));
});

it('sin consignatario vigente no genera PDF', async () => {
    await montar({
        id: 1,
        solicitud_preparacion: { modalidad_entrega: 'municipal', expediente: {} },
        caratula_municipal: { nombre: null, telefono: null, leyenda: 'PAGADO', tiene_consignatario: false, tiene_identificacion: false },
    });
    await act(() => boton('Ver carátula sin identificación').click());
    expect(axios.get).not.toHaveBeenCalled();
    expect(document.body.textContent).toContain('El pedido no tiene un consignatario vigente.');
});
