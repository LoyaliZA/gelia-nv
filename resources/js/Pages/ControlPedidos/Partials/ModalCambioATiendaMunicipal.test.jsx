// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import { accionesPedidoLista } from './pedidosListadoUi';
import ModalCambioATiendaMunicipal from './ModalCambioATiendaMunicipal';
import CambioATiendaArea from './CambioATiendaArea';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }));

let container;
let root;
const boton = (texto) => [...document.body.querySelectorAll('button')].find((b) => b.textContent.trim() === texto);
const cambio = (extra = {}) => ({
    estado: null, etiqueta: null, motivo: null, puede_solicitar: true, puede_aplicar: false, ubicacion: 'pdv',
    sucursal_destino_id: 1, sucursales: [{ id: 1, nombre: 'Norte' }, { id: 2, nombre: 'Sur' }], ...extra,
});
const pedido = (c) => ({ id: 9, folio: 'PED-9', cliente: { nombre: 'Cliente ficticio' }, cambio_a_tienda: c });

beforeEach(() => {
    vi.clearAllMocks();
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (nombre, params) => `${nombre}:${JSON.stringify(params ?? null)}`;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

it('el menú de Ventas sigue lo que informa el servidor', () => {
    const onCambioATienda = vi.fn();
    const props = { can: () => true, onVer: vi.fn(), onBitacora: vi.fn(), puedeEditar: () => false, onCambioATienda };
    const con = (c) => accionesPedidoLista({ ...props, pedido: { solicitud_preparacion: { modalidad_entrega: 'municipal' }, estatus: { fase_ciclo: 'EN_CEDIS' }, cambio_a_tienda: c } });

    expect(con(cambio()).find((a) => a.key === 'cambio-a-tienda').label).toBe('Cambiar a recolección en sucursal');
    expect(con(cambio({ puede_solicitar: false, estado: 'solicitado' })).find((a) => a.key === 'cambio-a-tienda').label).toBe('Recolección en sucursal');
    expect(con(cambio({ puede_solicitar: false })).some((a) => a.key === 'cambio-a-tienda')).toBe(false);
    expect(con(null).some((a) => a.key === 'cambio-a-tienda')).toBe(false);
    expect(con(cambio()).find((a) => a.key === 'cambio-a-tienda').grupo).toBe('Operaciones');
});

it('solicita con motivo, error de captura y la clave de la solicitud', async () => {
    axios.post.mockResolvedValue({ data: { ok: true } });
    const onActualizado = vi.fn();
    const onClose = vi.fn();
    await act(() => root.render(<ModalCambioATiendaMunicipal abierto pedido={pedido(cambio())} onClose={onClose} onActualizado={onActualizado} />));

    expect(document.body.textContent).toContain('Recolección en sucursal');
    expect(document.body.textContent).not.toMatch(/\b[MT]-0\d\b/);
    const casilla = document.body.querySelector('input[type="checkbox"]');
    await act(() => casilla.click());
    await act(() => boton('Solicitar cambio').click());

    expect(axios.post).toHaveBeenCalledTimes(1);
    const datos = axios.post.mock.calls[0][1];
    expect(datos.get('solicitud_id')).toBeTruthy();
    expect(datos.get('error_captura')).toBe('1');
    expect(datos.get('sucursal_destino_id')).toBeNull();
    expect(onActualizado).toHaveBeenCalledTimes(1);
    expect(onClose).toHaveBeenCalledTimes(1);
});

it('avisa que otra sucursal con el traslado hecho exige gerencia y muestra el error del servidor', async () => {
    axios.post.mockRejectedValue({ response: { data: { errors: { pedido: ['El paquete ya salió a terminal. El cambio de modalidad ya no está disponible.'] } } } });
    await act(() => root.render(<ModalCambioATiendaMunicipal abierto pedido={pedido(cambio())} onClose={() => {}} onActualizado={() => {}} />));

    const selector = document.body.querySelector('select');
    await act(async () => {
        const setter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set;
        setter.call(selector, '2');
        selector.dispatchEvent(new Event('change', { bubbles: true }));
    });
    expect(document.body.textContent).toContain('autorización de gerencia');
    await act(() => boton('Solicitar cambio').click());
    expect(axios.post.mock.calls[0][1].get('sucursal_destino_id')).toBe('2');
    expect(document.body.querySelector('[role="alert"]').textContent).toContain('ya salió a terminal');
});

it('con la solicitud registrada muestra su estado sin formulario', async () => {
    await act(() => root.render(<ModalCambioATiendaMunicipal abierto pedido={pedido(cambio({ puede_solicitar: false, estado: 'solicitado', etiqueta: 'Solicitado, pendiente de verificar', motivo: 'El cliente recoge' }))} onClose={() => {}} onActualizado={() => {}} />));

    expect(document.body.textContent).toContain('Solicitado, pendiente de verificar');
    expect(boton('Solicitar cambio')).toBeUndefined();
});

it('el área solo verifica cuando el servidor lo permite', async () => {
    axios.post.mockResolvedValue({ data: { ok: true } });
    const onActualizado = vi.fn();
    await act(() => root.render(<CambioATiendaArea pedidoId={9} onActualizado={onActualizado} cambio={cambio({ estado: 'solicitado', etiqueta: 'Solicitado, pendiente de verificar', puede_aplicar: true })} />));

    await act(() => boton('Verificar cambio').click());
    expect(axios.post.mock.calls[0][0]).toContain('control_pedidos.municipal.cambio_a_tienda.aplicar');
    expect(onActualizado).toHaveBeenCalledTimes(1);

    await act(() => root.render(<CambioATiendaArea pedidoId={9} cambio={cambio({ estado: 'solicitado', etiqueta: 'Solicitado', puede_aplicar: false })} />));
    expect(boton('Verificar cambio')).toBeUndefined();
    await act(() => root.render(<CambioATiendaArea pedidoId={9} cambio={cambio()} />));
    expect(document.body.textContent).toBe('');
});
