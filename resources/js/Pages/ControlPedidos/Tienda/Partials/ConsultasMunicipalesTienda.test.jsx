// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { enviarRespuestaMunicipal, PanelConsultaMunicipalTienda } from './ConsultasMunicipalesTienda';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('../../Partials/PanelRespuestaDisponibilidad', () => ({
    default: ({ onEnviar, pedido }) => (
        <div>
            <p>{pedido.folio}</p>
            <button type="button" onClick={() => { const form = new FormData(); form.append('persistir_unidad', '1'); return onEnviar(form); }}>Guardar pieza</button>
            <button type="button" onClick={() => onEnviar(new FormData())}>Enviar respuesta</button>
        </div>
    ),
}));

let root;
let container;
const recarga = vi.fn();

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (name, id) => `${name}/${id}`;
    api.get.mockReset().mockResolvedValue({ data: { id: 9, folio: 'M01-001' } });
    api.post.mockReset().mockResolvedValue({ data: { estado: 'excepciones_pendientes' } });
    window.axios = api;
    recarga.mockReset();
    vi.stubGlobal('location', { reload: recarga });
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.unstubAllGlobals(); });

it('el borrador de pieza no recarga; el envío del conjunto cierra sin recarga dura', async () => {
    const onClose = vi.fn();
    await act(async () => { root.render(<PanelConsultaMunicipalTienda pedidoId={9} onClose={onClose} />); });
    expect(api.get).toHaveBeenCalledWith('/control-pedidos/9/disponibilidad-tienda');
    await act(async () => [...document.querySelectorAll('button')].find(b => b.textContent === 'Guardar pieza').click());
    expect(api.post).toHaveBeenCalledWith('control_pedidos.municipal.disponibilidad/9', expect.any(FormData), expect.objectContaining({ headers: { Accept: 'application/json' } }));
    expect(api.get).toHaveBeenCalledTimes(2);
    expect(recarga).not.toHaveBeenCalled();
    expect(onClose).not.toHaveBeenCalled();
    await act(async () => [...document.querySelectorAll('button')].find(b => b.textContent === 'Enviar respuesta').click());
    expect(onClose).toHaveBeenCalledTimes(1);
    expect(recarga).not.toHaveBeenCalled();
});

it('enviarRespuestaMunicipal refresca el JSON en el borrador y avisa el envío final', async () => {
    const onBorrador = vi.fn();
    const onEnviado = vi.fn();
    const borrador = new FormData();
    borrador.append('persistir_unidad', '1');
    await enviarRespuestaMunicipal(9, borrador, { onBorrador, onEnviado });
    expect(onBorrador).toHaveBeenCalledWith({ id: 9, folio: 'M01-001' });
    expect(onEnviado).not.toHaveBeenCalled();
    await enviarRespuestaMunicipal(9, new FormData(), { onBorrador, onEnviado });
    expect(onEnviado).toHaveBeenCalledTimes(1);
    expect(recarga).not.toHaveBeenCalled();
});
