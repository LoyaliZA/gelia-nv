// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import Seguimiento from './SeguimientoSolicitudTienda';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('./AccionesRapidasDisponibilidad', () => ({ default: () => null }));
vi.mock('./ModalVistaPreviaDocumento', () => ({ default: () => null }));
vi.mock('./RespuestaPreparacionTienda', () => ({ default: () => null }));
vi.mock('./RespuestaDisponibilidadPedido', () => ({ default: () => null }));
vi.mock('./CapturaConjuntoPedido', () => ({ default: () => null }));
vi.mock('./ResumenCambiosDisponibilidad', () => ({ default: () => null }));
vi.mock('./AjustePiezas', () => ({ AjustePiezas: () => null }));
vi.mock('../Municipal/SeguimientoSolicitudMunicipal', () => ({
    default: ({ pedido }) => <div>Seguimiento municipal {pedido?.solicitud_preparacion?.modalidad_entrega}</div>,
}));

let root;
let host;
const pedido = {
    id: 9,
    version_consulta: 12,
    puede_cerrar_consulta: true,
    solicitud_preparacion: {
        modalidad_entrega: 'tienda',
        revision_disponibilidad: { estado: 'sin_excepciones' },
    },
};

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    axios.post.mockResolvedValue({ data: { consulta_cerrada: true } });
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

it('envía modalidad esperada y versión al cerrar', async () => {
    await act(() => root.render(<Seguimiento pedidoId={9} pedido={pedido} />));
    const cerrar = [...document.querySelectorAll('button')].find((el) => el.textContent.includes('Cerrar consulta'));
    await act(() => cerrar.click());
    expect(axios.post.mock.calls[0][0]).toBe('/control-pedidos/9/cerrar-consulta');
    expect(axios.post.mock.calls[0][1]).toEqual({ modalidad_esperada: 'tienda', version_consulta: 12 });
});

it('deshabilita el cierre con el motivo del servidor', async () => {
    await act(() => root.render(<Seguimiento pedidoId={9} pedido={{
        ...pedido,
        puede_cerrar_consulta: false,
        motivo_bloqueo_cierre: 'Hay un cambio de modalidad en curso',
    }} />));
    expect([...document.querySelectorAll('button')].some((el) => el.textContent.includes('Cerrar consulta'))).toBe(false);
    expect(document.body.textContent).toContain('Hay un cambio de modalidad en curso');
});

it('abre el seguimiento municipal si el cierre queda obsoleto', async () => {
    axios.post.mockRejectedValueOnce({
        response: { status: 409, data: { codigo: 'contexto_obsoleto', message: 'La consulta ya no corresponde a esta modalidad. Actualice para continuar en envío municipal.' } },
    });
    axios.get.mockResolvedValueOnce({
        data: { id: 9, solicitud_preparacion: { modalidad_entrega: 'municipal', consulta_municipal: { estado: 'respondida' } } },
    });
    await act(() => root.render(<Seguimiento pedidoId={9} pedido={pedido} />));
    await act(() => [...document.querySelectorAll('button')].find((el) => el.textContent.includes('Cerrar consulta')).click());
    expect(document.body.textContent).toContain('Seguimiento municipal municipal');
    expect(document.body.textContent).toContain('Actualice para continuar en envío municipal');
});
