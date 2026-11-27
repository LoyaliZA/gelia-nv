// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import Index from './Index';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('axios', () => ({ default: { get } }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { reload: vi.fn() },
    usePage: () => ({ props: { flash: {} } }),
}));
vi.mock('../../../Layouts/AppLayout', () => ({ default: ({ children }) => children }));
vi.mock('../../../Components/GeliaPageShell', () => ({ default: ({ children }) => children }));
vi.mock('../../../Components/GeliaPaginacion', () => ({ default: () => null }));
vi.mock('./Partials/FiltrosCedis', () => ({ default: () => null }));
vi.mock('./Partials/TarjetasCedis', () => ({
    default: ({ pedidos, onVerDetalle }) => <button onClick={() => onVerDetalle(pedidos.data[0])}>Ver detalle</button>,
}));
vi.mock('./Partials/ModalDetalleCedis', () => ({
    default: ({ abierto, pedido }) => abierto && <output>{pedido.documentos.map((doc) => doc.nombre_original).join(', ')}</output>,
}));
vi.mock('./Partials/ModalResponderPesaje', () => ({ default: () => null }));
vi.mock('../Partials/ModalReportarErrorDatos', () => ({ default: () => null }));
vi.mock('./Partials/ModalMarcarApartadoResguardo', () => ({ default: () => null }));
vi.mock('../Partials/ModalAlertaPedido', () => ({ default: () => null }));
vi.mock('../Partials/ModalBitacoraPedido', () => ({ default: () => null }));
vi.mock('../Partials/ModalLiberarMercancia', () => ({ default: () => null }));
vi.mock('../Partials/PanelRespuestaDisponibilidad', () => ({ default: () => null }));

it('el evento de confirmación actualiza los documentos del detalle abierto', async () => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.useFakeTimers();
    vi.stubGlobal('route', (name) => `/${name}`);
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    const antes = { id: 42, folio: 'NAC-42', guias_pdf_por_caja: true, documentos: [] };
    const metricas = {};
    try {
        await act(() => root.render(<Index auth={{}} metricas={metricas} pedidos={{ data: [antes], current_page: 1, total: 1 }} filtros={{ tab: 'TODOS' }} />));
        await act(() => [...host.querySelectorAll('button')].find((b) => b.textContent === 'Ver detalle').click());
        expect(host.querySelector('output').textContent).toBe('');

        get.mockResolvedValueOnce({
            data: { pedidos: { data: [{ ...antes, documentos: [{ nombre_original: 'Guía caja 1.pdf' }] }] } },
        });
        await act(async () => {
            window.dispatchEvent(new CustomEvent('pedido-bma.actualizado', { detail: { pedido_id: 42 } }));
            await vi.advanceTimersByTimeAsync(250);
        });
        expect(get).toHaveBeenLastCalledWith('/control_pedidos.cedis.listado', expect.objectContaining({
            params: { tab: 'TODOS', q: 'NAC-42' },
        }));
        expect(host.querySelector('output').textContent).toBe('Guía caja 1.pdf');
    } finally {
        await act(() => root.unmount());
        host.remove();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    }
});
