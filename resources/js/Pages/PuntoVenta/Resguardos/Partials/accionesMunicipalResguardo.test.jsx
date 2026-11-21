// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import { router } from '@inertiajs/react';
import ContenidoDetalleResguardo from './ContenidoDetalleResguardo';

vi.mock('axios', () => ({ default: { put: vi.fn(), post: vi.fn(), get: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({
    Link: ({ children }) => <a>{children}</a>,
    usePage: () => ({ props: {} }),
    router: { post: vi.fn(), reload: vi.fn() },
}));
vi.mock('../../../ControlPedidos/Partials/SeguimientoTransferenciaTienda', () => ({ default: () => null }));
vi.mock('./PanelIncidenciasResguardo', () => ({ default: () => null }));
vi.mock('./PanelExcepcionesResguardo', () => ({ default: () => null }));
vi.mock('./PanelReponerVencidoResguardo', () => ({ default: () => null }));
vi.mock('./PanelAuditoriaResguardo', () => ({ default: () => null }));
vi.mock('./PanelPedidoRevisionResguardo', () => ({ default: () => null }));
vi.mock('./ModalEntregaResguardo', () => ({ AccionEntregaResguardo: () => null }));
vi.mock('./BotonConfirmarRecepcionResguardo', () => ({ default: () => null, BotonPasarARecepcionResguardo: () => null }));
vi.mock('./AccionEvidenciaRegistroManual', () => ({ default: () => null }));
vi.mock('../../../ControlPedidos/Municipal/IntegracionFisicaConjunto', () => ({ default: () => null }));

let root;
let host;

const base = {
    id: 5, version: 3, estado: 'en_custodia', estado_etiqueta: 'En custodia', snapshot_folio: 'F-5',
    referencia_cliente: 'C1', clasificaciones: {}, bultos: [{ id: 1, folio: 'B-1', tipo: 'caja', estado: 'en_custodia' }],
    pedido_bma_id: 44,
};
const municipal = (extra = {}) => ({
    ...base, es_municipal: true, puede_salida_terminal: true, salida_terminal: null,
    caratula: { pedido_id: 44, id: null, puede_generar: false, puede_regenerar: false, puede_descargar: false },
    ...extra,
});
const conCaratula = (extra = {}) => municipal({
    caratula: { pedido_id: 44, id: 9, version: 2, estado: 'GENERADA', generada_at: '2026-11-21T10:00:00Z', puede_generar: false, puede_regenerar: true, puede_descargar: true, ...extra },
});

const pintar = async (resguardo, props = {}) => act(() => root.render(<ContenidoDetalleResguardo resguardo={resguardo} {...props} />));
const boton = (texto) => [...document.querySelectorAll('button, a')].find((b) => b.textContent.trim() === texto);
const campo = (etiqueta) => {
    const label = [...document.querySelectorAll('label')].find((l) => l.textContent.includes(etiqueta));
    return label ? (label.querySelector('input, textarea') || document.getElementById(label.getAttribute('for'))) : null;
};
const escribir = async (el, valor) => {
    const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    await act(async () => {
        Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, valor);
        el.dispatchEvent(new Event('input', { bubbles: true }));
    });
};

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => {}, removeItem: () => {} });
    window.route = (name, params) => `/${name}/${[].concat(params ?? '').join('/')}`;
    axios.put.mockReset();
    router.post.mockReset();
    router.reload.mockReset();
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); });

it('muestra la salida a terminal solo en resguardo municipal en custodia', async () => {
    await pintar(municipal());
    expect(campo('Quién recibe en la terminal')).toBeTruthy();
    expect(campo('Quién recibe en la terminal').maxLength).toBe(120);
    expect(campo('Quién recibe en la terminal').required).toBe(true);
    expect(boton('Registrar salida a terminal')).toBeTruthy();

    await pintar(municipal({ puede_salida_terminal: false }));
    expect(boton('Registrar salida a terminal')).toBeUndefined();
    expect(campo('Quién recibe en la terminal')).toBeNull();
});

it('deshabilita el botón sin receptor y tras enviar', async () => {
    let resolver;
    axios.put.mockImplementation(() => new Promise((res) => { resolver = res; }));
    await pintar(municipal());
    expect(boton('Registrar salida a terminal').disabled).toBe(true);

    await escribir(campo('Quién recibe en la terminal'), '   ');
    expect(boton('Registrar salida a terminal').disabled).toBe(true);

    await escribir(campo('Quién recibe en la terminal'), '  Juan Pérez ');
    expect(boton('Registrar salida a terminal').disabled).toBe(false);

    await act(async () => boton('Registrar salida a terminal').click());
    expect(axios.put).toHaveBeenCalledTimes(1);
    const [url, cuerpo] = axios.put.mock.calls[0];
    expect(url).toBe('/punto_venta.resguardos.salida_terminal/5');
    expect(cuerpo.version).toBe(3);
    expect(cuerpo.receptor).toBe('Juan Pérez');
    expect(cuerpo.idempotency_key.length).toBeLessThanOrEqual(55);
    expect(boton('Registrando salida…').disabled).toBe(true);
    await act(async () => boton('Registrando salida…').click());
    expect(axios.put).toHaveBeenCalledTimes(1);

    await act(async () => resolver({ data: { resguardo: { salida_terminal: { at: '2026-11-21T10:00:00Z', receptor: 'Juan Pérez' } } } }));
});

it('conserva la clave del intento al reintentar y la cambia si se edita el receptor', async () => {
    axios.put.mockRejectedValue({ response: { status: 500 } });
    await pintar(municipal());
    await escribir(campo('Quién recibe en la terminal'), 'Ana');
    await act(async () => boton('Registrar salida a terminal').click());
    expect(document.body.textContent).toContain('No pudimos registrar la salida');
    await act(async () => boton('Registrar salida a terminal').click());
    expect(axios.put.mock.calls[1][1].idempotency_key).toBe(axios.put.mock.calls[0][1].idempotency_key);

    await escribir(campo('Quién recibe en la terminal'), 'Ana Ruiz');
    await act(async () => boton('Registrar salida a terminal').click());
    expect(axios.put.mock.calls[2][1].idempotency_key).not.toBe(axios.put.mock.calls[0][1].idempotency_key);
});

it('muestra en línea el mensaje del servidor ante 409 o 422 y refresca al registrar', async () => {
    axios.put.mockRejectedValueOnce({ response: { status: 409, data: { message: 'La salida ya se registró desde otra terminal.' } } });
    await pintar(municipal());
    await escribir(campo('Quién recibe en la terminal'), 'Ana');
    await act(async () => boton('Registrar salida a terminal').click());
    expect(document.querySelector('[role="alert"]').textContent).toContain('La salida ya se registró desde otra terminal.');
    expect(boton('Actualizar datos')).toBeTruthy();

    axios.put.mockRejectedValueOnce({ response: { status: 422, data: { errors: { receptor: ['El nombre de quien recibe admite hasta 120 caracteres.'] } } } });
    await escribir(campo('Quién recibe en la terminal'), 'Ana B');
    await act(async () => boton('Registrar salida a terminal').click());
    expect(document.querySelector('[role="alert"]').textContent).toContain('admite hasta 120');

    const onAccionExito = vi.fn();
    axios.put.mockResolvedValueOnce({ data: { resguardo: { version: 4 } } });
    await pintar(municipal(), { onAccionExito });
    await escribir(campo('Quién recibe en la terminal'), 'Ana C');
    await act(async () => boton('Registrar salida a terminal').click());
    expect(onAccionExito).toHaveBeenCalled();
});

it('no muestra acciones en Tienda ni Nacional', async () => {
    const ajeno = { ...base, puede_salida_terminal: true, caratula: { pedido_id: 44, puede_generar: true, puede_descargar: true, puede_regenerar: true, id: 1 } };
    await pintar(ajeno);
    expect(boton('Registrar salida a terminal')).toBeUndefined();
    expect(boton('Generar carátula')).toBeUndefined();
    expect(boton('Descargar carátula')).toBeUndefined();
    await pintar({ ...base });
    expect(document.body.textContent).not.toContain('Salida a terminal');
});

it('muestra la salida registrada y no «Entregado»', async () => {
    const resguardo = municipal({
        estado: 'entregado', estado_etiqueta: 'Salida a terminal', puede_salida_terminal: false,
        entrega_completada_at: '2026-11-21T10:00:00Z',
        salida_terminal: { at: '2026-11-21T10:00:00Z', receptor: 'Juan Pérez' },
        bultos: [{ id: 1, folio: 'B-1', tipo: 'caja', estado: 'entregado', entrega_at: '2026-11-21T10:00:00Z' }],
    });
    await pintar(resguardo);
    const texto = document.body.textContent;
    expect(texto).toContain('Salida a terminal registrada · Juan Pérez ·');
    expect(texto).toContain('Salida a terminal');
    expect(texto).not.toMatch(/entregado/i);
    expect(texto).not.toContain('Entrega completada');
    expect(boton('Registrar salida a terminal')).toBeUndefined();
});

it('ofrece generar sin carátula', async () => {
    await pintar(municipal({ puede_salida_terminal: false, caratula: { pedido_id: 44, id: null, puede_generar: true, puede_regenerar: false, puede_descargar: false } }));
    expect(boton('Descargar carátula')).toBeUndefined();
    expect(boton('Regenerar carátula')).toBeUndefined();
    await act(async () => boton('Generar carátula').click());
    expect(router.post).toHaveBeenCalledWith('/control_pedidos.tienda.municipal.caratula.generar/44', {}, expect.objectContaining({ preserveScroll: true }));
});

it('ofrece descargar y regenerar con carátula', async () => {
    await pintar(conCaratula());
    expect(boton('Generar carátula')).toBeUndefined();
    const descarga = boton('Descargar carátula');
    expect(descarga.getAttribute('href')).toBe('/control_pedidos.tienda.municipal.caratula.pdf/44/9');
    expect(descarga.getAttribute('target')).toBe('_blank');
    expect(boton('Regenerar carátula')).toBeTruthy();

    await pintar(conCaratula({ puede_regenerar: false, puede_descargar: false }));
    expect(boton('Descargar carátula')).toBeUndefined();
    expect(boton('Regenerar carátula')).toBeUndefined();
});

it('regenerar exige motivo', async () => {
    await pintar(conCaratula());
    await act(async () => boton('Regenerar carátula').click());
    const motivo = campo('Motivo de la regeneración');
    expect(motivo).toBeTruthy();
    expect(motivo.required).toBe(true);
    const confirmar = boton('Confirmar regeneración');
    expect(confirmar.disabled).toBe(true);
    await act(async () => confirmar.click());
    expect(router.post).not.toHaveBeenCalled();

    await escribir(motivo, '  Dato corregido  ');
    expect(boton('Confirmar regeneración').disabled).toBe(false);
    await act(async () => boton('Confirmar regeneración').click());
    expect(router.post).toHaveBeenCalledWith(
        '/control_pedidos.tienda.municipal.caratula.regenerar/44',
        { motivo_regeneracion: 'Dato corregido' },
        expect.objectContaining({ preserveScroll: true }),
    );
});

it('una falla del servidor al generar la carátula se muestra en línea, sin el modal de Inertia', async () => {
    let retorno;
    router.post.mockImplementation((_url, _datos, opciones) => {
        retorno = opciones.onHttpException?.({ status: 500 });
        opciones.onFinish?.();
    });
    await pintar(municipal({ puede_salida_terminal: false, caratula: { pedido_id: 44, id: null, puede_generar: true, puede_regenerar: false, puede_descargar: false } }));
    expect(document.body.textContent).toContain('Aún no hay carátula para este envío.');
    await act(async () => boton('Generar carátula').click());
    expect(retorno).toBe(false);
    expect(document.querySelector('[role="alert"]').textContent).toContain('No se pudo generar la carátula');

    router.post.mockImplementation((_url, _datos, opciones) => {
        retorno = opciones.onNetworkError?.(new Error('sin red'));
        opciones.onFinish?.();
    });
    await act(async () => boton('Generar carátula').click());
    expect(retorno).toBe(false);
    expect(document.querySelector('[role="alert"]').textContent).toContain('No se pudo generar la carátula');
});

it('tras «Actualizar datos» el siguiente intento usa otra clave', async () => {
    axios.put.mockRejectedValueOnce({ response: { status: 409, data: { message: 'Otro usuario modificó este resguardo.' } } });
    await pintar(municipal());
    await escribir(campo('Quién recibe en la terminal'), 'Ana');
    await act(async () => boton('Registrar salida a terminal').click());
    await act(async () => boton('Actualizar datos').click());
    axios.put.mockRejectedValueOnce({ response: { status: 500 } });
    await act(async () => boton('Registrar salida a terminal').click());
    expect(axios.put.mock.calls[1][1].idempotency_key).not.toBe(axios.put.mock.calls[0][1].idempotency_key);
});
