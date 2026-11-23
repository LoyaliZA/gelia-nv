// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import ModalConciliarOrigenSaf from './ModalConciliarOrigenSaf';
import { accionesPedidoLista } from '../../ControlPedidos/Partials/pedidosListadoUi';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }));

let container;
let root;
const boton = (texto) => [...document.body.querySelectorAll('button')].find((b) => b.textContent.trim() === texto);

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

it('concilia el origen con uso, motivo y evidencia, y muestra el error del servidor', async () => {
    axios.post.mockRejectedValueOnce({ response: { data: { errors: { permiso: ['Quien generó el saldo no concilia su propio origen.'] } } } });
    const onClose = vi.fn();
    await act(() => root.render(<ModalConciliarOrigenSaf credito={{ id: 3, folio: 'SAF-00003', monto_disponible: 60 }} onClose={onClose} />));

    expect(document.body.textContent).toContain('Conciliar origen');
    expect(document.body.textContent).toContain('no mueve montos, reservas ni aplicaciones');
    await act(() => boton('Confirmar origen').click());
    expect(axios.post.mock.calls[0][0]).toContain('saldos_favor.conciliar_origen');
    expect(axios.post.mock.calls[0][1].get('uso')).toBe('solo_transporte');
    expect(document.body.querySelector('[role="alert"]').textContent).toContain('no concilia su propio origen');
    expect(onClose).not.toHaveBeenCalled();

    axios.post.mockResolvedValueOnce({ data: { ok: true } });
    await act(() => boton('Confirmar origen').click());
    expect(onClose).toHaveBeenCalledTimes(1);
});

it('la acción de saldo de envío solo aparece cuando el servidor lo autoriza', () => {
    const onSaldoEnvio = vi.fn();
    const props = { can: () => true, onVer: vi.fn(), onBitacora: vi.fn(), puedeEditar: () => false, onSaldoEnvio };
    const con = (c) => accionesPedidoLista({ ...props, pedido: { solicitud_preparacion: { modalidad_entrega: 'municipal' }, estatus: { fase_ciclo: 'EN_CEDIS' }, cambio_a_tienda: c } });

    expect(con({ estado: 'aplicado', puede_autorizar_saldo: true }).find((a) => a.key === 'saldo-envio').label).toBe('Autorizar saldo de envío');
    expect(con({ estado: 'aplicado', puede_autorizar_saldo: false }).some((a) => a.key === 'saldo-envio')).toBe(false);
    expect(con(null).some((a) => a.key === 'saldo-envio')).toBe(false);
});
