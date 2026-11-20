// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import ContenidoDetalleResguardo from './ContenidoDetalleResguardo';

vi.mock('@inertiajs/react', () => ({ Link: ({ children }) => <a>{children}</a>, usePage: () => ({ props: {} }) }));
vi.mock('../../../ControlPedidos/Partials/SeguimientoTransferenciaTienda', () => ({ default: () => null }));
vi.mock('./PanelIncidenciasResguardo', () => ({ default: () => null }));
vi.mock('./PanelExcepcionesResguardo', () => ({ default: () => null }));
vi.mock('./PanelReponerVencidoResguardo', () => ({ default: () => null }));
vi.mock('./PanelAuditoriaResguardo', () => ({ default: () => null }));
vi.mock('./PanelPedidoRevisionResguardo', () => ({ default: () => null }));
vi.mock('./ModalEntregaResguardo', () => ({ AccionEntregaResguardo: () => null }));
vi.mock('./BotonConfirmarRecepcionResguardo', () => ({ default: () => null, BotonPasarARecepcionResguardo: () => null }));
vi.mock('./AccionEvidenciaRegistroManual', () => ({ default: () => null }));

let root, host;
const vinculo = { origen_id: 7, folio: 'F-7', remision: 'R-7', mecanismo: 'custodia_local', estado: 'pendiente', integrado: false, pago_auditado: true, puede_desvincular: false, etapas: {} };
const resguardo = (puede) => ({
    id: 1, estado: 'en_custodia', snapshot_folio: 'F-7', referencia_cliente: 'C1', clasificaciones: {}, bultos: [],
    integracion_municipal: { pedido_id: 44, puede_confirmar: puede, resguardo_envio: { activo: true, estados: [], vinculos: [vinculo] } },
});
const boton = () => [...document.querySelectorAll('button')].find((b) => b.textContent === 'Confirmar integración en sucursal');

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => {}, removeItem: () => {} });
    window.route = (name, id) => `${name}/${[].concat(id ?? '').join('/')}`;
    window.axios = { post: vi.fn(async () => ({ data: {} })) };
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); });

it('muestra el botón al personal autorizado, confirma contra el envío principal y refresca', async () => {
    const onAccionExito = vi.fn();
    await act(() => root.render(<ContenidoDetalleResguardo resguardo={resguardo(true)} onAccionExito={onAccionExito} />));
    expect(boton()).toBeTruthy();
    await act(async () => boton().click());
    expect(window.axios.post).toHaveBeenCalledWith('control_pedidos.municipal.remisiones.integrar_local/44/7', {}, expect.anything());
    expect(onAccionExito).toHaveBeenCalled();
});

it('no muestra el botón sin facultad ni en modo auditoría', async () => {
    await act(() => root.render(<ContenidoDetalleResguardo resguardo={resguardo(false)} />));
    expect(boton()).toBeUndefined();
    await act(() => root.render(<ContenidoDetalleResguardo resguardo={resguardo(true)} modoAuditoria />));
    expect(boton()).toBeUndefined();
});
