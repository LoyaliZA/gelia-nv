// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import Formulario from './FormularioRegistrarMunicipal';
import { terminalesMunicipales } from './terminalMunicipal';
import { etiquetaOrigenGuia } from '../Partials/pedidosBmaStyles';

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1 } } } }) }));
vi.mock('./ExpedienteConsignatario', () => ({ default: ({ onSeleccionar }) => <button onClick={() => onSeleccionar(12)}>Elegir persona ficticia</button> }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));
vi.mock('../Partials/AccionesRapidasDisponibilidad', () => ({ default: () => null }));
let root, host;
const terminal = { id: 2, nombre: 'Terminal ficticia', categoria: 'local_regional', sin_logistica: false, permite_costo_diferido: true, permite_por_cobrar: true };
const pedido = { id: 44, folio: 'PBMA-44', solicitud_preparacion: { modalidad_entrega: 'municipal' } };
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    sessionStorage.clear();
    window.route = (name, id) => `${name}/${id}`;
    window.axios = { get: vi.fn(async () => ({ data: { ...pedido, catalogo_paqueteria_id: 2, solicitud_preparacion: { modalidad_entrega: 'municipal', expediente: { consignatario_id: 12 }, finanzas_municipales: {} } } })), post: vi.fn(async (_url, body) => ({ data: { catalogo_paqueteria_id: body.catalogo_paqueteria_id, paqueteria: terminal, solicitud_preparacion: { modalidad_entrega: 'municipal', expediente: { consignatario_id: 12 }, finanzas_municipales: {} } } })) };
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); });
const render = async (props = {}) => act(() => root.render(<Formulario abierto {...props} pedido={pedido} catalogos={{ paqueterias: [terminal], paqueterias_municipio: [] }}>{({ contenido, pie }) => <>{contenido}{pie}</>}</Formulario>));
const button = text => [...document.querySelectorAll('button')].find(el => el.textContent === text);
const select = async (label, value) => act(() => { const el = [...document.querySelectorAll('label')].find(el => el.textContent.startsWith(label)).querySelector('select'); el.value = value; el.dispatchEvent(new Event('change', { bubbles: true })); });
it('usa categoría Local / Regional activa sin depender de Sin logística o costo diferido', () => {
    const conLogistica = { id: 3, categoria: 'comercial', sin_logistica: true, permite_costo_diferido: true };
    expect(terminalesMunicipales({ paqueterias: [terminal, conLogistica, { ...terminal, id: 4, activo: false }] })).toEqual([terminal]);
});
it('presenta modalidad municipal y conserva las etiquetas de los otros pedidos', () => {
    expect(etiquetaOrigenGuia(pedido)).toBe('Envío municipal');
    expect(etiquetaOrigenGuia({})).toBe('Guía generada por la empresa');
    expect(etiquetaOrigenGuia({ cliente_proporciona_guia: true })).toBe('Guía proporcionada por el cliente');
});
it('permite enviar al completar campos aunque haya cambios sin guardar', async () => {
    await render();
    expect(document.body.textContent).not.toContain('Municipio o destino');
    expect(document.body.textContent).toContain('Terminal destino');
    expect(button('Enviar consulta').disabled).toBe(true);
    await select('Terminal destino', '2');
    await act(() => button('Elegir persona ficticia').click());
    await select('Costo de transporte', 'diferido');
    expect(button('Enviar consulta').disabled).toBe(false);
    await act(async () => button('Guardar solicitud').click());
    const body = window.axios.post.mock.calls[0][1];
    expect(body).not.toHaveProperty('municipio_destino');
    expect(body).toMatchObject({ catalogo_paqueteria_id: 2, consignatario_id: 12, costo_transporte: 'diferido' });
    expect(button('Enviar consulta').disabled).toBe(false);
    await select('Leyenda para la terminal', 'POR_COBRAR');
    expect(button('Enviar consulta').disabled).toBe(false);
});
it('conserva el borrador al cerrar y volver a montar', async () => {
    await render();
    await select('Terminal destino', '2');
    await select('Costo de transporte', 'diferido');
    await act(() => root.unmount()); root = createRoot(host);
    await render();
    expect(document.querySelector('select').value).toBe('2');
    expect([...document.querySelectorAll('select')][2].value).toBe('diferido');
    expect(button('Enviar consulta').disabled).toBe(true);
});

const completar = async () => {
    await select('Terminal destino', '2');
    await act(async () => button('Elegir persona ficticia').click());
    await select('Costo de transporte', 'diferido');
};
it('confirma datos comunes y municipales antes de enviar, sin guardado manual', async () => {
    const antesDeGuardar = vi.fn(async () => ({ ...pedido, id: 55 }));
    await render({ antesDeGuardar }); await completar();
    await act(async () => button('Enviar consulta').click());
    expect(antesDeGuardar).toHaveBeenCalledTimes(1);
    expect(window.axios.post.mock.calls.map(c => c[0])).toEqual(['control_pedidos.municipal.solicitud/55', 'control_pedidos.municipal.consulta/55']);
    expect(window.axios.post.mock.calls[0][1]).toMatchObject({ catalogo_paqueteria_id: 2, consignatario_id: 12, costo_transporte: 'diferido' });
});
it('no envía cuando falla el guardado municipal y permite reintentar', async () => {
    await render(); await completar();
    window.axios.post.mockRejectedValueOnce({ response: { data: { message: 'No se pudo guardar la solicitud.' } } });
    await act(async () => button('Enviar consulta').click());
    expect(window.axios.post).toHaveBeenCalledTimes(1);
    expect(window.axios.post.mock.calls[0][0]).toContain('municipal.solicitud');
    expect(button('Enviar consulta').disabled).toBe(false);
    expect(document.body.textContent).toContain('No se pudo guardar la solicitud.');
});
it('no guarda ni envía si la captura común no puede confirmarse', async () => {
    await render({ antesDeGuardar: vi.fn(async () => null) }); await completar();
    await act(async () => button('Enviar consulta').click());
    expect(window.axios.post).not.toHaveBeenCalled();
    expect(document.body.textContent).toContain('No se pudieron guardar los datos del pedido');
});
it('habilita enviar sin documento persistido cuando el padre no bloquea el archivo', async () => {
    await render({ motivoComun: '' }); await completar();
    expect(button('Enviar consulta').disabled).toBe(false);
});
it('adjuntar el documento al pedido persistido ocurre antes de solicitud y consulta', async () => {
    const asegurarDocumento = vi.fn(async () => ({ id: 9, url: '/wizerp.pdf' }));
    const antesDeGuardar = vi.fn(async () => ({ ...pedido, id: 55 }));
    await render({ antesDeGuardar, asegurarDocumento }); await completar();
    await act(async () => button('Enviar consulta').click());
    expect(asegurarDocumento).toHaveBeenCalledTimes(1);
    expect(window.axios.post.mock.calls.map(c => c[0])).toEqual(['control_pedidos.municipal.solicitud/55', 'control_pedidos.municipal.consulta/55']);
    expect(document.body.textContent).toContain('Consulta enviada');
});
it('no envía la consulta si el documento no queda persistido', async () => {
    await render({ antesDeGuardar: vi.fn(async () => ({ ...pedido, id: 55 })), asegurarDocumento: vi.fn(async () => null) });
    await completar();
    await act(async () => button('Enviar consulta').click());
    expect(window.axios.post).not.toHaveBeenCalled();
    expect(document.body.textContent).toContain('No se pudo adjuntar el PDF o la imagen');
});
it('bloquea el doble clic mientras termina el guardado previo al envío', async () => {
    let confirmar;
    const antesDeGuardar = vi.fn(() => new Promise(resolve => { confirmar = resolve; }));
    await render({ antesDeGuardar }); await completar();
    const enviar = button('Enviar consulta');
    await act(async () => { enviar.click(); enviar.click(); });
    expect(antesDeGuardar).toHaveBeenCalledTimes(1);
    await act(async () => confirmar(pedido));
    expect(window.axios.post).toHaveBeenCalledTimes(2);
});
