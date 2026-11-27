// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarTienda from './FormularioRegistrarTienda';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1, departamento_id: 1, permissions: [] } } } }) }));

let root, container;
beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (nombre) => `/${nombre}`);
    axios.get.mockResolvedValue({ data: {} });
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

it.each(['tienda', 'municipal', 'nacional'])('abre %s preseleccionada sin guardar un borrador vacío', async (modalidad) => {
    await act(() => root.render(<FormularioRegistrarTienda abierto modalidadInicial={modalidad} onClose={() => {}} />));
    expect(document.querySelector('#modalidad-tienda').value).toBe(modalidad);
    await act(() => vi.advanceTimersByTimeAsync(1500));
    expect(axios.post).not.toHaveBeenCalled();
    expect(axios.put).not.toHaveBeenCalled();
});

it('conserva la modalidad guardada al editar aunque se reciba una modalidad inicial distinta', async () => {
    const pedido = { id: 12, solicitud_preparacion: { modalidad_entrega: 'tienda' } };
    axios.get.mockResolvedValue({ data: pedido });
    await act(() => root.render(<FormularioRegistrarTienda abierto pedido={pedido} modalidadInicial="nacional" onClose={() => {}} />));
    expect(document.querySelector('#modalidad-tienda').value).toBe('tienda');
});

it('vincula el aviso de folio repetido al campo y lo retira al corregir el número', async () => {
    const pedido = { id: 12, folio_remision: '12', solicitud_preparacion: { modalidad_entrega: 'tienda' } };
    const mensaje = 'El número de pedido 11 ya está registrado en el pedido GELIA-11.';
    axios.get.mockResolvedValue({ data: pedido });
    axios.post.mockRejectedValueOnce({ response: { status: 422, data: { errors: { folio_remision: [mensaje] } } } });
    axios.post.mockResolvedValueOnce({ data: { ...pedido, folio_remision: '13' } });
    await act(async () => root.render(<FormularioRegistrarTienda abierto pedido={pedido} onClose={() => {}} />));

    const campo = document.getElementById('folio-tienda');
    const escribir = (valor) => act(() => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(campo, valor);
        campo.dispatchEvent(new Event('input', { bubbles: true }));
    });

    await escribir('11');
    expect(campo.getAttribute('aria-invalid')).toBe('false');
    await act(() => vi.advanceTimersByTimeAsync(700));
    expect(campo.getAttribute('aria-invalid')).toBe('true');
    const aviso = document.getElementById(campo.getAttribute('aria-describedby'));
    expect(aviso.getAttribute('role')).toBe('alert');
    expect(aviso.textContent).toBe(mensaje);

    await escribir('13');
    expect(campo.getAttribute('aria-invalid')).toBe('false');
    expect(campo.hasAttribute('aria-describedby')).toBe(false);
    expect(document.body.textContent).not.toContain(mensaje);
    await act(() => vi.advanceTimersByTimeAsync(700));
    expect(axios.post.mock.calls.at(-1)[1].folio_remision).toBe('13');
    expect(campo.getAttribute('aria-invalid')).toBe('false');
});
