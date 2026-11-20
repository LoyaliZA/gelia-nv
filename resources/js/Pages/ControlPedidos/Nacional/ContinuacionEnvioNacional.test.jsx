// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import ContinuacionEnvioNacional from './ContinuacionEnvioNacional';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
vi.mock('../Partials/SeccionPagosExhibicion', () => ({
    default: ({ omiteEnvio, costoEnvio }) => <p>{omiteEnvio ? 'Solo mercancía' : `Flete guardado ${costoEnvio}`}</p>,
}));

const pedido = {
    id: 9, cliente_id: 4, total_mercancia: '100.00', catalogo_paqueteria_id: 2,
    estatus: { fase_ciclo: 'PESAJE_RESPONDIDO' },
    revision_registro: 3, base_captura: 'a'.repeat(64),
    cajas: [{ id: 5, uuid_operativo: 'caja-1', estado_operativo: 'activa' }],
};
const catalogos = {
    tipos_guia: [{ id: 3, nombre: 'Guía nacional' }],
    zonas: [{ id: 6, nombre: 'Entrega directa' }],
};

let host;
let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    vi.resetAllMocks();
    axios.get.mockResolvedValue({ data: { direcciones: [{ id: 8, codigo_postal: '86000', etiqueta: 'Casa' }] } });
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

async function render(extra = {}, pendiente = false, onPedido = vi.fn()) {
    await act(async () => root.render(
        <ContinuacionEnvioNacional pedido={{ ...pedido, ...extra }} catalogos={catalogos} pendiente={pendiente} onPedido={onPedido} />,
    ));
    return onPedido;
}

it('permite enviar la cobertura de mercancía al auxiliar sin abrir cotización', async () => {
    axios.put.mockResolvedValue({ data: { estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } } });
    const onPedido = await render({}, true);
    expect(host.textContent).toContain('Solo mercancía');
    expect(host.textContent).not.toContain('Capturar cotización');
    expect(axios.get).not.toHaveBeenCalled();
    await act(async () => host.querySelector('button').click());
    expect(axios.put).toHaveBeenCalledWith('/control-pedidos/9/enviar', {}, expect.anything());
    expect(onPedido).toHaveBeenCalledWith({ estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } });
});

it('registra cada flete y la dirección antes de abrir el pago del envío', async () => {
    const respuesta = { costo_envio: '60.00', solicitud_preparacion: { cotizacion_nacional: { paqueteria_id: 2 } } };
    axios.post.mockResolvedValue({ data: respuesta });
    const onPedido = await render({ mercancia_pagada: true, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } });
    expect(host.textContent).toContain('Mercancía pagada');
    expect(host.textContent).not.toContain('Flete guardado');

    await act(async () => {
        const selects = host.querySelectorAll('select');
        ['8', '3', '6'].forEach((id, i) => {
            selects[i].value = id;
            selects[i].dispatchEvent(new Event('change', { bubbles: true }));
        });
        const monto = host.querySelector('input[type="number"]');
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(monto, '60');
        monto.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(async () => host.querySelector('button').click());
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/paqueteria-nacional', expect.objectContaining({
        catalogo_paqueteria_id: 2,
        revision_esperada: 3,
        base_captura: 'a'.repeat(64),
        cotizacion: { cliente_direccion_id: 8, catalogo_tipo_guia_id: 3, catalogo_zona_id: 6 },
        lineas: [{ uuid_operativo: 'caja-1', costo_envio: 60 }],
    }), expect.anything());
    expect(onPedido).toHaveBeenCalledWith(respuesta);
});
