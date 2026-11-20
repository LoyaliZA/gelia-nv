// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import Formulario from '../Partials/FormularioRegistrarTienda';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1, departamento_id: 1, departamento_ids: [1], permissions: [] } } } }) }));
vi.mock('../Partials/useActualizacionesPedidos', () => ({ default: () => {} }));
vi.mock('./ExpedienteConsignatario', () => ({ default: ({ onSeleccionar }) => <button type="button" onClick={() => onSeleccionar(12)}>Elegir persona ficticia</button> }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));
vi.mock('../Partials/VisorPdfPaginas', () => ({ default: () => null }));

let root, host;
const terminal = { id: 2, nombre: 'Terminal ficticia', categoria: 'local_regional', activo: true, permite_costo_diferido: true, permite_por_cobrar: true };
const catalogos = { departamentos: [{ id: 1, nombre: 'Departamento' }], paqueterias: [terminal], almacenes: [] };
const urls = [];
beforeEach(() => {
    vi.useFakeTimers();
    sessionStorage.clear();
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (nombre, id) => `${nombre}/${id ?? ''}`);
    window.axios = axios;
    urls.length = 0;
    axios.get.mockImplementation(async (url) => {
        if (String(url).includes('/api/clientes')) {
            return { data: [{ id: 3, numero_cliente: '900077', nombre: 'Cliente ficticio' }] };
        }
        return { data: { id: 77, solicitud_preparacion: { modalidad_entrega: 'municipal' } } };
    });
    axios.post.mockImplementation(async (url, body) => {
        urls.push(typeof url === 'string' ? url : String(url));
        if (String(url).includes('autoguardar')) {
            return { data: { id: 77, folio: 'PBMA-77', solicitud_preparacion: body?.solicitud_preparacion || { modalidad_entrega: 'municipal' } } };
        }
        if (String(url).includes('pdf_pedido')) {
            return { data: { id: 77, documento: { id: 3, tipo: 'pdf_pedido', url: '/wizerp.pdf', nombre_original: 'wizerp.pdf' } } };
        }
        if (String(url).includes('municipal.solicitud')) {
            return { data: { id: 77, catalogo_paqueteria_id: 2, paqueteria: terminal, solicitud_preparacion: { modalidad_entrega: 'municipal', expediente: { consignatario_id: 12 }, finanzas_municipales: { costo_transporte: 'diferido' } } } };
        }
        if (String(url).includes('municipal.consulta')) {
            return { data: { id: 77, solicitud_preparacion: { modalidad_entrega: 'municipal', consulta_municipal: { enviada_at: '2026-11-17T12:00:00-06:00', estado: 'pendiente', area: 'cedis' } } } };
        }
        return { data: { id: 77 } };
    });
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.useRealTimers(); vi.unstubAllGlobals(); });
const button = text => [...document.querySelectorAll('button')].find(el => el.textContent.includes(text));
const cambiar = (id, value) => act(async () => {
    const el = document.getElementById(id);
    Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value').set.call(el, value);
    el.dispatchEvent(new Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
});
const selectLabel = async (label, value) => act(() => {
    const el = [...document.querySelectorAll('label')].find(n => n.textContent.startsWith(label))?.querySelector('select');
    el.value = value; el.dispatchEvent(new Event('change', { bubbles: true }));
});

it('abrir un alta y elegir solo Municipal no crea un pedido', async () => {
    await act(async () => root.render(<Formulario abierto catalogos={catalogos} />));
    await act(async () => vi.advanceTimersByTimeAsync(1000));
    expect(urls).toEqual([]);
    await cambiar('modalidad-tienda', 'municipal');
    await act(async () => vi.advanceTimersByTimeAsync(1000));
    expect(urls).toEqual([]);
    expect(document.getElementById('modalidad-tienda').value).toBe('municipal');
});

it('elegir solo Tienda y el área tampoco crea un pedido', async () => {
    await act(async () => root.render(<Formulario abierto catalogos={catalogos} />));
    await cambiar('modalidad-tienda', 'tienda');
    await act(async () => vi.advanceTimersByTimeAsync(1000));
    expect(urls).toEqual([]);
});

it('continúa Nacional vacío sin persistir por la sola elección de modalidad', async () => {
    const continuar = vi.fn();
    await act(async () => root.render(<Formulario abierto catalogos={catalogos} onContinuarEnvio={continuar} />));
    await cambiar('modalidad-tienda', 'nacional');
    await act(async () => vi.advanceTimersByTimeAsync(1000));
    expect(urls).toEqual([]);
    expect(continuar).not.toHaveBeenCalled();
    expect(document.getElementById('modalidad-tienda').value).toBe('nacional');
    expect(document.body.textContent).toContain('Salida: CEDIS');
    expect(document.body.textContent).not.toContain('Terminal destino');
});

it('un pedido nuevo con PDF elegido envía autoguardar, pdf-pedido y consulta sin guardar a mano', async () => {
    await act(async () => root.render(<Formulario abierto catalogos={catalogos} />));
    await cambiar('cliente-tienda', '900077');
    await act(() => [...document.querySelectorAll('button')].find(el => el.textContent.includes('Cliente ficticio'))?.click());
    await cambiar('folio-tienda', 'WIZ-77');
    await cambiar('modalidad-tienda', 'municipal');
    const input = document.querySelector('input[type=file]');
    await act(() => {
        Object.defineProperty(input, 'files', { configurable: true, value: [new File(['wizerp'], 'wizerp.pdf', { type: 'application/pdf' })] });
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => vi.runAllTimers());
    await selectLabel('Terminal destino', '2');
    await act(() => button('Elegir persona ficticia').click());
    await selectLabel('Costo de transporte', 'diferido');
    expect(button('Enviar consulta').disabled).toBe(false);
    await act(async () => button('Enviar consulta').click());
    await act(async () => vi.runAllTimers());
    const cadena = urls.filter(u => u.includes('autoguardar') || u.includes('pdf_pedido') || u.includes('municipal.'));
    expect(cadena.some(u => u.includes('autoguardar'))).toBe(true);
    expect(cadena.some(u => u.includes('pdf_pedido'))).toBe(true);
    expect(cadena.indexOf(cadena.find(u => u.includes('pdf_pedido')))).toBeLessThan(cadena.indexOf(cadena.find(u => u.includes('municipal.consulta'))));
    expect(cadena.some(u => u.includes('municipal.solicitud'))).toBe(true);
    expect(cadena.some(u => u.includes('municipal.consulta'))).toBe(true);
});
