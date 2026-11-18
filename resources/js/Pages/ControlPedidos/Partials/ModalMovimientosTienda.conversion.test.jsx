// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import ModalMovimientosTienda from './ModalMovimientosTienda';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

let container;
let root;
const pedido = { id: 81, folio: 'EJEMPLO-81', cliente: { nombre: 'Cliente de ejemplo' } };
beforeEach(() => {
    vi.clearAllMocks();
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

it('con consulta abierta y pendiente municipal continúa la misma conversión', async () => {
    const onContinuarConversion = vi.fn();
    axios.get.mockResolvedValue({
        data: {
            aplica: true,
            version: 0,
            modalidad: { estado: 'pendiente_conexion', destino: 'municipal' },
            conversion_modalidad: { puede_continuar: true, accion_ui: 'continuar', consulta_ya_cerrada_alguna_vez: false },
        },
    });
    await act(() => root.render(
        <ModalMovimientosTienda abierto pedido={pedido} modo="modalidad" onContinuarConversion={onContinuarConversion} />
    ));
    const continuar = [...document.querySelectorAll('button')].find((b) => /continuar/i.test(b.textContent));
    expect(continuar).toBeTruthy();
    await act(() => continuar.click());
    expect(onContinuarConversion).toHaveBeenCalled();
    expect(axios.post).not.toHaveBeenCalled();
});

it('con consulta ya cerrada conserva la intención T-03 y no aplica', async () => {
    axios.get.mockResolvedValue({
        data: {
            aplica: true,
            version: 0,
            modalidad: { estado: 'pendiente_conexion', destino: 'municipal' },
            conversion_modalidad: { puede_iniciar: false, puede_continuar: false, consulta_ya_cerrada_alguna_vez: true },
        },
    });
    await act(() => root.render(<ModalMovimientosTienda abierto pedido={pedido} modo="modalidad" />));
    expect(document.body.textContent).toMatch(/pendiente de conexi[oó]n/i);
    expect([...document.querySelectorAll('button')].some((b) => /aplicar|confirmar cambio|convertir/i.test(b.textContent))).toBe(false);
});
