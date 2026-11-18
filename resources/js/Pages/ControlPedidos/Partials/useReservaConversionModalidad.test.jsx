// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import useReservaConversionModalidad, { LATIDO_CONVERSION_MS } from './useReservaConversionModalidad';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));
vi.mock('../../../utils/uuidSolicitud', () => ({ uuidSolicitud: () => '11111111-1111-4111-8111-111111111111' }));

function Harness({ pedidoId, flagsIniciales = null }) {
    useReservaConversionModalidad({ activo: true, pedidoId, flagsIniciales });
    return <p>reserva activa</p>;
}

let root;
let host;

beforeEach(() => {
    vi.useFakeTimers();
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    axios.get.mockResolvedValue({ data: { conversion_modalidad: { puede_iniciar: true } } });
    axios.post.mockResolvedValue({ data: { ok: true, reserva_activa: true, ttl_segundos: 45 } });
    axios.delete.mockResolvedValue({ data: { ok: true } });
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
    vi.useRealTimers();
    vi.restoreAllMocks();
});

it('reserva con instancia_id al abrir, latido del mismo id y suelta al cerrar', async () => {
    await act(() => root.render(<Harness pedidoId={81} />));
    await act(() => vi.advanceTimersByTimeAsync(0));
    expect(axios.post.mock.calls[0][0]).toContain('/conversion-modalidad/reserva');
    expect(axios.post.mock.calls[0][1]).toEqual({ instancia_id: '11111111-1111-4111-8111-111111111111' });
    expect(axios.post.mock.calls[0][0]).not.toContain('/latido');
    await act(() => vi.advanceTimersByTimeAsync(LATIDO_CONVERSION_MS));
    const latido = axios.post.mock.calls.find(([url]) => String(url).includes('/reserva/latido'));
    expect(latido[1]).toEqual({ instancia_id: '11111111-1111-4111-8111-111111111111' });
    await act(() => root.unmount());
    root = createRoot(host);
    expect(axios.delete).toHaveBeenCalled();
    expect(axios.delete.mock.calls[0][1].data).toEqual({ instancia_id: '11111111-1111-4111-8111-111111111111' });
});
