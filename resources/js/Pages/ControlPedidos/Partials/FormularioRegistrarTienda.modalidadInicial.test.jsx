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
