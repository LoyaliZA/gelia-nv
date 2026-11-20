// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarNacional from './FormularioRegistrarNacional';
import { adaptadorConsultaNacional } from '../Partials/adaptadoresConsulta';

vi.mock('axios', () => ({ default: { post: vi.fn(), get: vi.fn() } }));

const pedido = {
    id: 9,
    folio_remision: 'WIZ-1',
    documentos: [{ id: 1, url: '/a.pdf' }],
    revision_registro: 1,
    base_captura: 'a'.repeat(64),
};
const catalogos = {
    paqueterias: [
        { id: 8, nombre: 'DHL', categoria: 'comercial', requiere_peso: true, activo: true },
        { id: 9, nombre: 'PAQ. PENDIENTE', categoria: 'comercial', requiere_peso: true, activo: true },
        { id: 2, nombre: 'Local', categoria: 'local_regional', requiere_peso: false, activo: true },
    ],
};
let root;
let host;

beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    axios.post.mockReset();
    axios.post.mockResolvedValue({ data: { ok: true, id: 9, pesaje_solicitado_at: '2026-11-20T12:00:00Z' } });
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

async function render() {
    const guardar = vi.fn(async () => ({ ...pedido, revision_registro: 2, base_captura: 'b'.repeat(64) }));
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={pedido} catalogos={catalogos} guardar={guardar} onPedido={vi.fn()}>
                {({ contenido, pie }) => <div>{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });

    return guardar;
}

function botonEnviar() {
    return [...document.querySelectorAll('button')].find((boton) => boton.textContent.includes('Enviar consulta a CEDIS'));
}

it('mantiene el envío disponible y no consulta sin paquetería', async () => {
    const guardar = await render();
    expect(botonEnviar().disabled).toBe(false);
    expect(document.body.textContent).not.toContain('PAQ. PENDIENTE');
    expect(document.body.textContent).not.toContain('Local');
    await act(async () => {
        botonEnviar().click();
        await Promise.resolve();
    });
    expect(document.body.textContent).toContain('Elija la paquetería comercial.');
    expect(guardar).not.toHaveBeenCalled();
    expect(axios.post).not.toHaveBeenCalled();
});

it('envía la consulta de pesaje con la paquetería comercial', async () => {
    const guardar = await render();
    const select = document.getElementById('paqueteria-nacional');
    await act(async () => {
        select.value = '8';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => botonEnviar().click());
    expect(guardar).toHaveBeenCalledWith({ catalogo_paqueteria_id: 8 });
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/solicitar-pesaje',
        expect.objectContaining({ revision_esperada: 2, base_captura: 'b'.repeat(64) }),
        expect.anything(),
    );
    expect(document.body.textContent).toContain('Salida: CEDIS');
});

it('actualiza la paquetería después del cierre, incluida la pendiente', async () => {
    const cerrado = {
        ...pedido,
        consulta_cerrada_at: '2026-11-20T18:00:00Z',
        pesaje_solicitado_at: '2026-11-20T12:00:00Z',
        catalogo_paqueteria_id: 8,
        revision_registro: 4,
        base_captura: 'd'.repeat(64),
    };
    axios.post.mockResolvedValue({ data: { ok: true, catalogo_paqueteria_id: 9 } });
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={cerrado} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()}>
                {({ contenido, pie }) => <div>{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });
    const select = document.getElementById('paqueteria-posterior');
    expect(select).not.toBeNull();
    expect(select.textContent).toContain('PAQ. PENDIENTE');
    expect(document.getElementById('paqueteria-nacional')).toBeNull();
    await act(async () => {
        select.value = '9';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    const boton = [...document.querySelectorAll('button')].find((item) => item.textContent.includes('Actualizar paquetería'));
    expect(boton.disabled).toBe(false);
    await act(async () => boton.click());
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/paqueteria-nacional',
        expect.objectContaining({
            catalogo_paqueteria_id: 9,
            revision_esperada: 4,
            base_captura: 'd'.repeat(64),
        }),
        expect.anything(),
    );
});

it('anexa sobre el mismo pedido nacional', async () => {
    const form = new FormData();
    const adaptador = adaptadorConsultaNacional({
        pedidoId: 7,
        pedido: { pesaje_respondido_at: '2026-11-20', revision_registro: 3, base_captura: 'c'.repeat(64) },
    });
    await adaptador.comandos.anexar({ archivos: [{ size: 10 }], armar: () => form });
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/7/ajuste-nacional', form, expect.anything());
    expect(form.get('tipo')).toBe('anexo');
    expect(String(axios.post.mock.calls[0][0])).not.toContain('anexo-tienda');
});
