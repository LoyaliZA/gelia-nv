// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import DrawerImpresionGuias from './DrawerImpresionGuias';
import useImpresionGuiasCedis from './useImpresionGuiasCedis';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../../Partials/ModalVistaPreviaDocumento', () => ({
    default: ({ abierto }) => abierto ? 'Vista previa del PDF' : null,
}));
import axios from 'axios';

const primera = { id: 4, nombre_original: 'guia.pdf', caja: 'Caja térmica', vigente: true, url: '/archivo/4' };
const datos = {
    pedidos: [{ id: 8, folio: '26598', guias: [primera] }],
    pendientes: { pedidos: 2, guias: 2, ids: [4, 5] },
};
const pdf = new Blob(['%PDF-1.4'], { type: 'application/pdf' });
const registro = '/impresion-guias/apertura?signature=prueba';
const preparado = { data: pdf, headers: { 'x-gelia-apertura': registro } };
let root;
let host;
let visor;
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
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:guia');
    visor = { closed: false, close: vi.fn(), location: { replace: vi.fn() } };
    vi.spyOn(window, 'open').mockReturnValue(visor);
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

it('abre el PDF combinado y registra automáticamente los IDs del lote después de enviarlo al visor', async () => {
    axios.post.mockResolvedValueOnce(preparado).mockImplementationOnce(async () => {
        expect(visor.location.replace).toHaveBeenCalledExactlyOnceWith('blob:guia');
        axios.get.mockResolvedValueOnce({ data: { pedidos: [], pendientes: { pedidos: 0, guias: 0 } } });
        return { data: { ok: true } };
    });
    await act(async () => root.render(<Pantalla />));
    expect(document.querySelector('summary').textContent).toContain('26598');
    expect(document.body.textContent).toContain('Caja térmica');
    axios.get.mockResolvedValueOnce({ data: { pendientes: { ids: [4, 5, 6] } } });
    await click(boton('Imprimir todas (2)'));
    expect(axios.get).toHaveBeenCalledWith(
        '/control_pedidos.cedis.impresion_guias.index', expect.objectContaining({ params: { manifiesto: 1 } }),
    );
    expect(axios.post).toHaveBeenNthCalledWith(1,
        '/control_pedidos.cedis.impresion_guias.lote', { ids: [4, 5, 6] }, expect.any(Object),
    );
    expect(axios.post).toHaveBeenNthCalledWith(2,
        registro, { ids: [4, 5, 6] }, expect.any(Object),
    );
    expect(boton('Imprimir todas (0)').disabled).toBe(true);
    expect(boton('Confirmar impresión')).toBeUndefined();
});

it('registra solo el PDF individual y permite reimprimir una versión anterior desde Historial', async () => {
    axios.post.mockResolvedValue({ data: { ok: true } });
    axios.get.mockImplementation(async (url, config) => {
        if (url === primera.url) return config.params?.preparar ? preparado : { data: pdf };
        if (config.params.tab === 'historial') {
            return { data: { ...datos, pedidos: [{ ...datos.pedidos[0], guias: [{ ...primera, vigente: false }] }] } };
        }
        return { data: datos };
    });
    await act(async () => root.render(<Pantalla />));
    await click(boton('Imprimir'));
    expect(axios.get).toHaveBeenCalledWith(primera.url, expect.objectContaining({ params: { preparar: 1 } }));
    expect(visor.location.replace).toHaveBeenCalledExactlyOnceWith('blob:guia');
    expect(axios.post).toHaveBeenCalledExactlyOnceWith(registro, { ids: [4] }, expect.any(Object));
    await click(boton('Historial'));
    expect(document.body.textContent).toContain('Versión anterior');
    expect(boton('Imprimir todas (2)')).toBeUndefined();
    await click(boton('Imprimir'));
    expect(visor.location.replace).toHaveBeenCalledTimes(2);
    expect(axios.post).toHaveBeenCalledTimes(1);
});

it('Ver y Descargar conservan la guía en Pendientes', async () => {
    await act(async () => root.render(<Pantalla />));
    await click(document.querySelector('button[title="Ver PDF"]'));
    expect(document.body.textContent).toContain('Vista previa del PDF');
    const enlace = document.querySelector('a[download]');
    expect(enlace.getAttribute('href')).toBe(primera.url);
    expect(enlace.getAttribute('download')).toBe(primera.nombre_original);
    expect(window.open).not.toHaveBeenCalled();
    expect(axios.post).not.toHaveBeenCalled();
    expect(boton('Imprimir todas (2)').disabled).toBe(false);
});

it('conserva los pendientes cuando falla la combinación', async () => {
    axios.post.mockRejectedValueOnce({ response: { status: 503, data: { message: 'No se pudo combinar el lote.' } } });
    await act(async () => root.render(<Pantalla />));
    await click(boton('Imprimir todas (2)'));

    expect(axios.post).toHaveBeenCalledTimes(1);
    expect(visor.close).toHaveBeenCalledOnce();
    expect(visor.location.replace).not.toHaveBeenCalled();
    expect(document.querySelector('[role="alert"]').textContent).toBe('No se pudo combinar el lote.');
    expect(boton('Imprimir todas (2)').disabled).toBe(false);
});

it('conserva los pendientes cuando el navegador bloquea el visor', async () => {
    window.open.mockReturnValueOnce(null);
    await act(async () => root.render(<Pantalla />));
    await click(boton('Imprimir'));

    expect(axios.post).not.toHaveBeenCalled();
    expect(axios.get).not.toHaveBeenCalledWith(primera.url, expect.any(Object));
    expect(document.querySelector('[role="alert"]').textContent).toContain('Permite abrir pestañas nuevas');
    expect(boton('Imprimir todas (2)').disabled).toBe(false);
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
