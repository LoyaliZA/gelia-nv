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

async function renderSinDirecciones(extra = {}) {
    axios.get.mockResolvedValue({ data: { direcciones: [] } });
    await act(async () => root.render(
        <ContinuacionEnvioNacional pedido={pedido} catalogos={catalogos} pendiente={false} onPedido={vi.fn()} {...extra} />,
    ));
}

const boton = (texto) => [...document.body.querySelectorAll('button')].find((b) => b.textContent.trim() === texto);
const escribir = async (campo, valor) => act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(campo, valor);
    campo.dispatchEvent(new Event('input', { bubbles: true }));
});
const abrirCotizacion = async () => act(async () => { host.querySelector('summary').click(); });

it('sin direcciones permite agregar una dentro del pedido y la deja elegida', async () => {
    await renderSinDirecciones({ puedeAgregarDireccion: true });
    await abrirCotizacion();
    expect(host.textContent).toContain('no tiene direcciones verificadas');
    await act(async () => boton('Agregar dirección').click());

    const campos = [...document.body.querySelectorAll('[role="dialog"] input[type="text"]')];
    // Orden del formulario: destinatario, teléfono, calle, ext., int., colonia, C.P., municipio, ciudad, estado, país.
    await escribir(campos[0], 'Ana Prueba');
    await escribir(campos[2], 'Calle 1');
    await escribir(campos[5], 'Centro');
    await escribir(campos[6], '86000');
    await escribir(campos[7], 'Centro');
    await escribir(campos[9], 'Tabasco');

    axios.post.mockResolvedValue({ data: { ok: true, direccion: { id: 12, nombre_destinatario: 'Ana Prueba', codigo_postal: '86000' } } });
    await act(async () => boton('Guardar dirección').click());

    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/registrar-direccion-catalogo', expect.objectContaining({
        cliente_id: 4, pedido_id: 9, es_principal: true,
        nombre_destinatario: 'Ana Prueba', calle: 'Calle 1', colonia: 'Centro', codigo_postal: '86000', municipio: 'Centro', estado: 'Tabasco',
    }), expect.anything());
    expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    expect(host.querySelector('select').value).toBe('12');
});

it('no guarda una dirección incompleta y dice qué falta', async () => {
    await renderSinDirecciones({ puedeAgregarDireccion: true });
    await abrirCotizacion();
    await act(async () => boton('Agregar dirección').click());
    await act(async () => boton('Guardar dirección').click());
    expect(axios.post).not.toHaveBeenCalled();
    expect(document.body.querySelector('[role="dialog"]').textContent).toContain('código postal');
});

it('sin permiso de direcciones no ofrece el alta y pide ayuda a un auxiliar', async () => {
    await renderSinDirecciones();
    await abrirCotizacion();
    expect(boton('Agregar dirección')).toBeUndefined();
    expect(host.textContent).toContain('auxiliar');
});
