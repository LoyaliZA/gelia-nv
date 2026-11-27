// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import DrawerImpresionGuias from './DrawerImpresionGuias';
import useImpresionGuiasCedis from './useImpresionGuiasCedis';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));
import axios from 'axios';

const primera = { id: 4, nombre_original: 'guia.pdf', caja: 'Caja térmica', vigente: true, url: '/archivo/4' };
const datos = {
    pedidos: [{ id: 8, folio: '26598', guias: [primera] }],
    pendientes: { pedidos: 2, guias: 2, ids: [4, 5] },
};
const pdf = new Blob(['%PDF-1.4'], { type: 'application/pdf' });
let root;
let host;
const boton = texto => [...document.querySelectorAll('button')].find(b => b.textContent.trim() === texto);
const click = element => act(async () => element.click());

function Pantalla({ abierta = true }) {
    const cola = useImpresionGuiasCedis(true);
    React.useEffect(() => cola.setAbierto(abierta), [abierta, cola.setAbierto]);
    return cola.abierto ? <DrawerImpresionGuias cola={cola} onClose={() => cola.setAbierto(false)} /> : null;
}

beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', nombre => `/${nombre}`);
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('about:blank');
    axios.get.mockReset().mockResolvedValue({ data: datos });
    axios.post.mockReset();
    host = document.createElement('div');
    document.body.appendChild(host);
    root = createRoot(host);
});

afterEach(async () => {
    await act(async () => root.unmount());
    host.remove();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

async function simularDialogoNativo() {
    const iframe = document.querySelector('iframe[title="PDF para imprimir"]');
    const imprimir = vi.fn();
    Object.defineProperty(iframe, 'contentWindow', { configurable: true, value: { focus: vi.fn(), print: imprimir } });
    await act(async () => iframe.dispatchEvent(new Event('load')));
    expect(imprimir).toHaveBeenCalledTimes(1);
}

it('prepara un solo PDF para todos los pendientes y solo registra tras confirmación explícita', async () => {
    axios.post.mockResolvedValueOnce({ data: pdf }).mockResolvedValueOnce({ data: { ok: true } });
    await act(async () => root.render(<Pantalla />));
    expect(document.querySelector('summary').textContent).toContain('26598');
    expect(document.body.textContent).toContain('Caja térmica');
    axios.get.mockResolvedValueOnce({ data: { pendientes: { ids: [4, 5, 6] } } });
    await click(boton('Imprimir todas (2)'));
    expect(axios.get).toHaveBeenLastCalledWith(
        '/control_pedidos.cedis.impresion_guias.index', expect.objectContaining({ params: { manifiesto: 1 } }),
    );
    expect(axios.post).toHaveBeenCalledExactlyOnceWith(
        '/control_pedidos.cedis.impresion_guias.lote', { ids: [4, 5, 6] }, expect.any(Object),
    );
    await simularDialogoNativo();
    expect(axios.post).toHaveBeenCalledTimes(1);
    await click(boton('Confirmar impresión'));
    expect(axios.post).toHaveBeenLastCalledWith(
        '/control_pedidos.cedis.impresion_guias.confirmar', { ids: [4, 5, 6] }, expect.any(Object),
    );
});

it('permite imprimir una guía, conservarla pendiente y reimprimir desde el historial', async () => {
    axios.get.mockImplementation(async (url, config) => {
        if (url === primera.url) return { data: pdf };
        if (config.params.tab === 'historial') {
            return { data: { ...datos, pedidos: [{ ...datos.pedidos[0], guias: [{ ...primera, vigente: false }] }] } };
        }
        return { data: datos };
    });
    await act(async () => root.render(<Pantalla />));
    await click(boton('Imprimir'));
    await simularDialogoNativo();
    await click(boton('Mantener pendientes'));
    expect(axios.post).not.toHaveBeenCalled();
    await click(boton('Historial'));
    expect(document.body.textContent).toContain('Versión anterior');
    expect(boton('Imprimir todas (2)')).toBeUndefined();
    await click(boton('Imprimir'));
    await simularDialogoNativo();
    expect(boton('Confirmar impresión')).toBeUndefined();
    expect(axios.post).not.toHaveBeenCalled();
});

it('actualiza un drawer abierto al recibir un aviso y busca por folio en el historial', async () => {
    const callbacks = new Map();
    const canal = { listen: vi.fn((nombre, callback) => callbacks.set(nombre, callback)), stopListening: vi.fn() };
    vi.stubGlobal('Echo', { private: vi.fn(() => canal), leave: vi.fn() });
    await act(async () => root.render(<Pantalla />));

    axios.get.mockResolvedValueOnce({ data: datos }).mockResolvedValue({ data: { pedidos: [], pendientes: { pedidos: 0, guias: 0, ids: [] } } });
    await act(async () => {
        await callbacks.get('.cedis.impresion-guias.actualizada')();
    });
    expect(axios.get).toHaveBeenLastCalledWith(
        '/control_pedidos.cedis.impresion_guias.index', expect.objectContaining({ params: { tab: 'pendientes', q: undefined } }),
    );
    await click(boton('Historial'));
    const buscar = document.querySelector('input[type="search"]');
    await act(async () => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(buscar, '26598');
        buscar.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(async () => new Promise(resolve => setTimeout(resolve, 210)));
    expect(axios.get).toHaveBeenLastCalledWith(
        '/control_pedidos.cedis.impresion_guias.index', expect.objectContaining({ params: { tab: 'historial', q: '26598' } }),
    );
});
