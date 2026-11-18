// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import useActualizacionesPedidos from './useActualizacionesPedidos';

let root;
let container;
let actualizar;
const Harness = (props) => {
    useActualizacionesPedidos(props);
    return null;
};

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.useFakeTimers();
    actualizar = vi.fn();
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.useRealTimers();
});

it('refresca ante conversion_reserva_adquirida, conversion_reserva_liberada y conversion_modalidad', async () => {
    await act(() => root.render(<Harness onActualizar={actualizar} pedidoId={32} />));
    for (const accion of ['conversion_reserva_adquirida', 'conversion_reserva_liberada', 'conversion_modalidad']) {
        actualizar.mockClear();
        await act(() => {
            window.dispatchEvent(new CustomEvent('pedido-bma.actualizado', { detail: { pedido_id: 32, accion } }));
            vi.advanceTimersByTime(200);
        });
        expect(actualizar).toHaveBeenCalled();
        expect(actualizar.mock.calls[0][0]).toMatchObject({ pedido_id: 32, accion });
    }
});
