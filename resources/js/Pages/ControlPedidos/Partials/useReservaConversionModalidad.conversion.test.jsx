// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import useReservaConversionModalidad from './useReservaConversionModalidad';

function Harness({ pedidoId }) {
    useReservaConversionModalidad({ activo: true, pedidoId });
    return <p>reserva</p>;
}

let host;
let root;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.useFakeTimers();
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    window.route = (name, id) => `/${name}/${id}`;
    crypto.randomUUID = () => '11111111-1111-4111-8111-111111111111';
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

it('al abrir genera instancia_id, adquiere reserva y latido usa el mismo id', async () => {
    window.axios = {
        get: vi.fn(async () => ({ data: { conversion_modalidad: { puede_iniciar: true, destinos: [{ codigo: 'municipal', nombre: 'Envío municipal', habilitado: true }], herencia: { caso: 'sin_consulta' }, finanzas: {}, fisica: {}, version: 0 } } })),
        post: vi.fn(async () => ({ data: { ok: true, reserva_activa: true, ttl_segundos: 45 } })),
        delete: vi.fn(async () => ({ data: { ok: true, reserva_activa: false } })),
    };
    await act(() => root.render(<Harness pedidoId={9} />));
    expect(window.axios.post.mock.calls[0][0]).toContain('reserva');
    expect(window.axios.post.mock.calls[0][1]).toMatchObject({ instancia_id: '11111111-1111-4111-8111-111111111111' });
    await act(() => vi.advanceTimersByTime(20000));
    const latido = window.axios.post.mock.calls.find((c) => String(c[0]).includes('latido'));
    expect(latido).toBeTruthy();
    expect(latido[1]).toMatchObject({ instancia_id: '11111111-1111-4111-8111-111111111111' });
});

it('al cerrar suelta solo esa instancia', async () => {
    window.axios = {
        get: vi.fn(async () => ({ data: { conversion_modalidad: { puede_iniciar: true, destinos: [], herencia: { caso: 'sin_consulta' }, finanzas: {}, fisica: {}, version: 0 } } })),
        post: vi.fn(async () => ({ data: { ok: true, reserva_activa: true, ttl_segundos: 45 } })),
        delete: vi.fn(async () => ({ data: { ok: true, reserva_activa: false } })),
    };
    await act(() => root.render(<Harness pedidoId={9} />));
    window.dispatchEvent(new Event('beforeunload'));
    expect(window.axios.delete).toHaveBeenCalled();
    expect(window.axios.delete.mock.calls[0][1]).toMatchObject({ instancia_id: '11111111-1111-4111-8111-111111111111' });
});
