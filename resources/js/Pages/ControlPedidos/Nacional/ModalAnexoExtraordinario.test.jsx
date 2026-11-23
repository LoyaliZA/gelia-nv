// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import ModalAnexoExtraordinario from './ModalAnexoExtraordinario';

vi.mock('axios', () => ({ default: { post: vi.fn() } }));

const pedido = { id: 9, folio: 'PBMA-58', revision_registro: 4, base_captura: 'b'.repeat(64) };

let host;
let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    vi.resetAllMocks();
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

const render = async (props = {}) => {
    const onCreado = vi.fn();
    const onClose = vi.fn();
    await act(async () => root.render(
        <ModalAnexoExtraordinario abierto pedido={pedido} onCreado={onCreado} onClose={onClose} {...props} />,
    ));
    return { onCreado, onClose };
};
const campo = (id) => document.getElementById(id);
const escribir = async (id, valor) => act(async () => {
    const el = campo(id);
    const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, valor);
    el.dispatchEvent(new Event('input', { bubbles: true }));
});
const adjuntar = async (id, nombre = 'anexo.pdf') => act(async () => {
    const el = campo(id);
    const archivo = new File(['x'], nombre, { type: 'application/pdf' });
    Object.defineProperty(el, 'files', { value: [archivo], configurable: true });
    el.dispatchEvent(new Event('change', { bubbles: true }));
});
const boton = (texto) => [...document.body.querySelectorAll('button')].find((b) => b.textContent.trim() === texto);
const llenarValido = async () => {
    await escribir('anexo-folio-wizerp', 'WIZ-901');
    await escribir('anexo-importe', '250');
    await adjuntar('anexo-documento');
    await escribir('anexo-pieza-0-sku', 'SKU-1');
    await escribir('anexo-pieza-0-descripcion', 'Pieza uno');
};

it('muestra lo que hereda el anexo y no pide cliente, paquetería ni dirección', async () => {
    await render();
    const dialogo = document.body.querySelector('[role="dialog"]');
    expect(dialogo.textContent).toContain('PBMA-58');
    expect(dialogo.textContent).toContain('Hereda');
    expect(dialogo.textContent).not.toMatch(/Paquetería|Dirección de entrega|Número de cliente/);
    expect(campo('anexo-folio-wizerp')).not.toBeNull();
    expect(campo('anexo-pieza-0-sku')).not.toBeNull();
});

it('no envía si falta el folio, el documento o la identificación de una pieza, y dice qué falta', async () => {
    await render();
    await act(async () => boton('Enviar a consulta').click());
    expect(axios.post).not.toHaveBeenCalled();
    const mensaje = document.body.querySelector('[role="alert"]').textContent;
    expect(mensaje).toContain('folio Wizerp');
    expect(mensaje).toContain('documento');

    await escribir('anexo-folio-wizerp', 'WIZ-901');
    await escribir('anexo-importe', '250');
    await adjuntar('anexo-documento');
    await escribir('anexo-pieza-0-descripcion', 'Sin soporte');
    await act(async () => boton('Enviar a consulta').click());
    expect(axios.post).not.toHaveBeenCalled();
    expect(document.body.querySelector('[role="alert"]').textContent).toContain('SKU o adjunte la captura');
});

it('envía el anexo con sus datos y avisa al terminar', async () => {
    axios.post.mockResolvedValue({ data: { ok: true, anexo: { id: 77, folio: 'PBMA-58-C1', folio_principal: 'PBMA-58', estatus_fase: 'PESAJE_PENDIENTE' } } });
    const { onCreado, onClose } = await render();
    await llenarValido();
    await act(async () => boton('Enviar a consulta').click());

    expect(axios.post).toHaveBeenCalledTimes(1);
    const [url, cuerpo] = axios.post.mock.calls[0];
    expect(url).toBe('/control-pedidos/9/anexo-extraordinario');
    expect(cuerpo).toBeInstanceOf(FormData);
    expect(cuerpo.get('folio_wizerp')).toBe('WIZ-901');
    expect(cuerpo.get('total_mercancia_nueva')).toBe('250');
    expect(cuerpo.get('revision_esperada')).toBe('4');
    expect(cuerpo.get('base_captura')).toBe('b'.repeat(64));
    expect(cuerpo.get('piezas[0][sku]')).toBe('SKU-1');
    expect(cuerpo.get('piezas[0][descripcion]')).toBe('Pieza uno');
    expect(cuerpo.get('piezas[0][cantidad]')).toBe('1');
    expect(cuerpo.get('documento')).toBeInstanceOf(File);
    expect(cuerpo.get('solicitud_id')).toMatch(/^[0-9a-f-]{36}$/);
    expect(onCreado).toHaveBeenCalledWith(expect.objectContaining({ id: 77, folio: 'PBMA-58-C1' }));
    expect(onClose).toHaveBeenCalled();
});

it('dos clics seguidos envían una sola petición', async () => {
    let resolver;
    axios.post.mockReturnValue(new Promise((resolve) => { resolver = resolve; }));
    await render();
    await llenarValido();
    await act(async () => { boton('Enviar a consulta').click(); boton('Enviar a consulta')?.click(); });
    expect(axios.post).toHaveBeenCalledTimes(1);
    await act(async () => resolver({ data: { ok: true, anexo: { id: 1, folio: 'x' } } }));
});

it('un 422 muestra el mensaje del servidor y no cierra el modal', async () => {
    axios.post.mockRejectedValue({ response: { status: 422, data: { message: 'Sin remisión ni pago, el ajuste sigue en el mismo pedido.' } } });
    const { onCreado, onClose } = await render();
    await llenarValido();
    await act(async () => boton('Enviar a consulta').click());
    expect(document.body.querySelector('[role="alert"]').textContent).toContain('Sin remisión ni pago');
    expect(onCreado).not.toHaveBeenCalled();
    expect(onClose).not.toHaveBeenCalled();
});

it('valida al salir del campo, marca el error en el campo y lo limpia al editar', async () => {
    await render();
    await act(async () => campo('anexo-folio-wizerp').dispatchEvent(new FocusEvent('focusout', { bubbles: true })));
    expect(campo('anexo-folio-wizerp').getAttribute('aria-invalid')).toBe('true');
    const mensaje = document.getElementById(campo('anexo-folio-wizerp').getAttribute('aria-describedby'));
    expect(mensaje.textContent).toContain('folio Wizerp');
    await escribir('anexo-folio-wizerp', 'WIZ-1');
    expect(campo('anexo-folio-wizerp').getAttribute('aria-invalid')).toBeNull();

    await act(async () => campo('anexo-importe').dispatchEvent(new FocusEvent('focusout', { bubbles: true })));
    expect(document.getElementById(campo('anexo-importe').getAttribute('aria-describedby')).textContent).toContain('mayor a cero');
});

it('se cierra con Escape y devuelve el foco a quien lo abrió', async () => {
    const abridor = document.createElement('button');
    document.body.append(abridor);
    abridor.focus();
    const { onClose } = await render();
    await act(async () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
    expect(onClose).toHaveBeenCalledTimes(1);
    await act(async () => root.render(<ModalAnexoExtraordinario abierto={false} pedido={pedido} onClose={onClose} />));
    expect(document.activeElement).toBe(abridor);
    abridor.remove();
});
