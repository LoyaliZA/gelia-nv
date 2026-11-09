// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import useActualizacionesPedidos from './useActualizacionesPedidos';

let root;
let container;
let actualizar;
let pendiente;
const Harness = (props) => { useActualizacionesPedidos(props); return null; };
const cambio = (id) => window.dispatchEvent(new CustomEvent('pedido-bma.actualizado', { detail: { pedido_id: id } }));
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.useFakeTimers();
    actualizar = vi.fn(); pendiente = vi.fn();
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.useRealTimers(); vi.unstubAllGlobals(); vi.restoreAllMocks(); });
const render = (props = {}) => act(() => root.render(<Harness onActualizar={actualizar} onPendiente={pendiente} pedidoId={32} {...props} />));

it('no consulta periódicamente y agrupa cambios del mismo pedido', async () => {
    await render();
    await act(() => vi.advanceTimersByTime(60000));
    expect(actualizar).not.toHaveBeenCalled();
    await act(() => { cambio(99); vi.advanceTimersByTime(200); });
    expect(actualizar).not.toHaveBeenCalled();
    await act(() => { cambio(32); cambio(32); cambio(32); vi.advanceTimersByTime(200); });
    expect(actualizar).toHaveBeenCalledOnce();
});

it('conserva la captura y recupera una sola actualización al terminar la edición', async () => {
    await render({ pausado: true });
    await act(() => { cambio(32); cambio(32); vi.advanceTimersByTime(1000); });
    expect(actualizar).not.toHaveBeenCalled();
    expect(pendiente).toHaveBeenCalled();
    await render({ pausado: false });
    expect(actualizar).toHaveBeenCalledOnce();
});

it('comprueba de nuevo al recuperar la conexión y retira listeners al cerrar', async () => {
    await render();
    await act(() => { window.dispatchEvent(new Event('online')); vi.advanceTimersByTime(200); });
    expect(actualizar).toHaveBeenCalledOnce();
    await render({ activo: false });
    await act(() => { cambio(32); window.dispatchEvent(new Event('online')); vi.advanceTimersByTime(1000); });
    expect(actualizar).toHaveBeenCalledOnce();
});

it('usa el respaldo de 15 segundos solo en una pestaña visible sin suscripción operativa', async () => {
    const subscription = { subscribed: true };
    const connection = { state: 'connected', bind: vi.fn(), unbind: vi.fn() };
    const canal = { subscription, listen: vi.fn(), stopListening: vi.fn() };
    vi.stubGlobal('Echo', { private: () => canal, connector: { pusher: { connection } }, leave: vi.fn() });
    const visibilidad = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    await render({ intervaloRespaldo: 15000 });
    await act(() => vi.advanceTimersByTime(15000));
    expect(actualizar).not.toHaveBeenCalled();

    subscription.subscribed = false;
    visibilidad.mockReturnValue('hidden');
    await act(() => vi.advanceTimersByTime(15000));
    expect(actualizar).not.toHaveBeenCalled();

    visibilidad.mockReturnValue('visible');
    await act(() => vi.advanceTimersByTime(15200));
    expect(actualizar).toHaveBeenCalledOnce();

    await render({ activo: false, intervaloRespaldo: 15000 });
    await act(() => vi.advanceTimersByTime(30000));
    expect(actualizar).toHaveBeenCalledOnce();
});
