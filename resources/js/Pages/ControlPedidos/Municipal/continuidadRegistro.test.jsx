// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import Formulario from '../Partials/FormularioRegistrarTienda';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1, departamento_id: 1, permissions: [] } } } }) }));
vi.mock('../Partials/useActualizacionesPedidos', () => ({ default: () => {} }));
vi.mock('./ExpedienteConsignatario', () => ({ default: () => <span>Expediente del cliente</span> }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));
let root, host;
const pedido = { id: 31, folio: 'PBMA-31', folio_remision: 'WIZ-31', cliente_id: 3,
    cliente: { id: 3, numero_cliente: '900031', nombre: 'Cliente ficticio' }, departamento_operacion_id: 1,
    revision_registro: 1, base_captura: 'base', estatus: { fase_ciclo: 'BORRADOR' },
    documentos: [{ id: 2, tipo: 'pdf_pedido', mime_type: 'image/png', nombre_original: 'wizerp.png', url: '/qa/wizerp.png' }],
    solicitud_preparacion: { modalidad_entrega: 'tienda' } };
const cambiar = (id, value) => act(async () => {
    const el = document.getElementById(id);
    Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value').set.call(el, value);
    el.dispatchEvent(new Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
});
beforeEach(() => {
    vi.useFakeTimers(); sessionStorage.clear();
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (nombre, id) => `${nombre}/${id}`);
    window.axios = axios;
    axios.get.mockResolvedValue({ data: pedido });
    axios.post.mockImplementation(async (_url, body) => ({ data: { ...pedido, solicitud_preparacion: body.solicitud_preparacion } }));
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.useRealTimers(); vi.unstubAllGlobals(); });
it('conserva cliente, folio y documento en la misma captura al elegir Municipal', async () => {
    const continuar = vi.fn();
    await act(async () => root.render(<Formulario abierto pedido={pedido} onContinuarEnvio={continuar} catalogos={{ departamentos: [{ id: 1, nombre: 'Departamento' }] }} />));
    await cambiar('folio-tienda', 'WIZ-CORREGIDO');
    await cambiar('modalidad-tienda', 'municipal');
    expect(continuar).not.toHaveBeenCalled();
    expect(document.getElementById('folio-tienda').value).toBe('WIZ-CORREGIDO');
    expect(document.getElementById('cliente-tienda').value).toBe('900031');
    expect(document.getElementById('modalidad-tienda').value).toBe('municipal');
    expect(document.body.textContent).toContain('Listo. wizerp.png');
    expect(document.body.textContent).toContain('Terminal destino');
    expect(document.body.textContent).toContain('Guardar solicitud');
    expect(document.body.textContent).not.toContain('Atención en tienda');
});
it('mantiene la continuación nacional con los datos comunes', async () => {
    const continuar = vi.fn();
    await act(async () => root.render(<Formulario abierto pedido={pedido} onContinuarEnvio={continuar} />));
    await cambiar('modalidad-tienda', 'nacional');
    expect(continuar).not.toHaveBeenCalled();
    expect(document.getElementById('folio-tienda').value).toBe('WIZ-31');
    expect(document.getElementById('cliente-tienda').value).toBe('900031');
    expect(document.body.textContent).toContain('Envío nacional');
    expect(document.body.textContent).toContain('Salida: CEDIS');
    expect(document.body.textContent).not.toContain('Terminal destino');
    expect(document.getElementById('paqueteria-nacional')).toBeTruthy();
});
