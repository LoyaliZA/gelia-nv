// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import ModalMovimientosTienda from './ModalMovimientosTienda';
import { accionesPedidoLista } from './pedidosListadoUi';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let container, root;
const pedido = { id: 81, folio: 'EJEMPLO-81', cliente: { nombre: 'Cliente de ejemplo' } };
const datos = { aplica: true, version: 0, sucursal_actual: 'Principal', puede_solicitar: true, sucursales: [{ id: 2, nombre: 'City Center' }], almacenes: [], evidencias: [] };
const boton = nombre => [...document.querySelectorAll('button')].find(b => b.textContent === nombre);
async function cambiar(el, valor) { await act(() => { const proto = el.tagName === 'SELECT' ? HTMLSelectElement.prototype : HTMLTextAreaElement.prototype; Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, valor); el.dispatchEvent(new Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true })); }); }
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; axios.get.mockResolvedValue({ data: datos }); axios.post.mockResolvedValue({ data: { ...datos, puede_solicitar: false } }); container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('exige petición del cliente y permite sustituirla por la anotación con confirmación explícita', async () => {
    await act(() => root.render(<ModalMovimientosTienda abierto pedido={pedido} />));
    await cambiar(document.querySelector('select'), '2');
    await cambiar(document.querySelector('textarea'), 'El cliente solicita otra sucursal');
    await act(() => boton('Solicitar traslado').click());
    expect(axios.post).not.toHaveBeenCalled(); expect(document.body.textContent).toContain('Adjunte la petición');
    await act(() => document.querySelector('input[type=checkbox]').click());
    expect(document.body.textContent).toContain('Se notificó un error de captura');
    await act(() => boton('Solicitar traslado').click());
    expect(axios.post).not.toHaveBeenCalled(); expect(document.body.textContent).toContain('Se solicitará autorización');
    await act(() => boton('Sí, confirmar').click());
    const [url, form] = axios.post.mock.calls[0];
    expect(url).toContain('/solicitar'); expect(form.get('error_captura')).toBe('1'); expect(form.get('sucursal_destino_id')).toBe('2');
    expect(document.body.textContent).toContain('el pedido permanece en la sucursal actual');
});
it('cancelar una captura pide descartar y no envía solicitudes', async () => {
    const cerrar = vi.fn();
    await act(() => root.render(<ModalMovimientosTienda abierto pedido={pedido} onClose={cerrar} />));
    await cambiar(document.querySelector('textarea'), 'Datos sin enviar');
    await act(() => document.querySelector('[aria-label="Cerrar movimiento"]').click());
    expect(cerrar).not.toHaveBeenCalled();
    await act(() => boton('Descartar y cerrar').click());
    expect(cerrar).toHaveBeenCalledOnce(); expect(axios.post).not.toHaveBeenCalled();
});
it('prepara municipal como solicitud pendiente sin afirmar conversión', async () => {
    await act(() => root.render(<ModalMovimientosTienda abierto pedido={pedido} modo="modalidad" />));
    await cambiar(document.querySelector('select'), 'municipal'); await cambiar(document.querySelector('textarea'), 'El cliente pide entrega en casa');
    await act(() => boton('Registrar solicitud de cambio').click());
    expect(document.body.textContent).toContain('La modalidad actual se conserva');
    await act(() => boton('Sí, confirmar').click());
    expect(axios.post.mock.calls[0][1].get('modalidad')).toBe('municipal');
    expect(document.body.textContent).toContain('Pendiente de conectar el envío');
});
it('gerencia debe definir recorrido antes de autorizar y destino debe verificar ubicación', async () => {
    axios.get.mockResolvedValue({ data: { ...datos, puede_solicitar: false, puede_resolver: true, traslado: { estado: 'pendiente', motivo: 'Cambio solicitado' } } });
    await act(() => root.render(<ModalMovimientosTienda abierto pedido={pedido} />));
    await act(() => boton('Autorizar traslado').click()); expect(axios.post).not.toHaveBeenCalled(); expect(document.body.textContent).toContain('Defina el recorrido');
});
it('solo ofrece las solicitudes del menú en custodia y mantiene el seguimiento durante el traslado', () => {
    const props = { can: () => true, onMovimientoTienda: vi.fn(), pedido: { ...pedido, puede_mutar: true, estatus_sucursal: 'en_custodia', solicitud_preparacion: { modalidad_entrega: 'tienda' } } };
    expect(accionesPedidoLista(props).map(a => a.label)).toEqual(expect.arrayContaining(['Solicitar traslado', 'Cambiar modalidad']));
    expect(accionesPedidoLista({ ...props, pedido: { ...props.pedido, estatus_sucursal: 'en_transito' } })).toHaveLength(0);
    expect(accionesPedidoLista({ ...props, pedido: { ...props.pedido, estatus_sucursal: 'en_transito', solicitud_preparacion: { modalidad_entrega: 'tienda', segundo_traslado: { estado: 'autorizada' } } } }).map(a => a.label)).toContain('Seguimiento de traslado y modalidad');
});
