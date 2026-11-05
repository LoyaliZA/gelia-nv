// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import AccionesRapidasDisponibilidad from './AccionesRapidasDisponibilidad';
import SeguimientoSolicitudTienda from './SeguimientoSolicitudTienda';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let container, root;
const faltante = { id: 8, descripcion_producto: 'Aroma agotado', sku: 'SKU-8', estado_fisico: 'sin_existencia', cantidad_solicitada: 1 };
const boton = texto => [...container.querySelectorAll('button')].find(b => b.textContent === texto);
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('un faltante ofrece solo retirar o sustituir, sin aceptar su inexistencia', async () => {
    await act(() => root.render(<AccionesRapidasDisponibilidad pedidoId={1} pieza={faltante} />));
    expect(boton('Retirar')).toBeDefined(); expect(boton('Sustituir')).toBeDefined(); expect(boton('Confirmar estado')).toBeUndefined();
    await act(() => boton('Retirar').click()); expect(axios.post).not.toHaveBeenCalled();
    axios.post.mockResolvedValue({ data: { id: 1 } }); await act(() => boton('Confirmar retiro').click());
    expect(axios.post.mock.calls[0][1].get('revision_id')).toBe('8'); expect(axios.post.mock.calls[0][1].get('accion')).toBe('retirar');
});
it('un detalle permite confirmar estado y una sustitución admite captura pegada', async () => {
    await act(() => root.render(<AccionesRapidasDisponibilidad pedidoId={1} pieza={{ ...faltante, estado_fisico: 'regular' }} />));
    expect(boton('Confirmar estado')).toBeDefined();
    await act(() => boton('Sustituir').click()); await act(() => boton('Por captura o PDF').click());
    expect(container.textContent).toContain('Arrastra o pega');
    await act(() => boton('Solicitar sustitución').click()); expect(axios.post).not.toHaveBeenCalled(); expect(container.textContent).toContain('Seleccione la alternativa');
});
it('la consulta cerrada con faltante muestra alerta, reabre y conserva el ajuste detallado plegado', async () => {
    const pedido = { id: 1, solicitud_preparacion: { modalidad_entrega: 'tienda', revision_disponibilidad: { estado: 'excepciones_pendientes' } }, revisiones_producto: [faltante], motivo_bloqueo_cierre: 'Faltante por resolver' };
    axios.post.mockResolvedValue({ data: {} }); axios.get.mockResolvedValue({ data: { ...pedido, consulta_cerrada: false } });
    await act(() => root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} revisiones={[faltante]} consultaCerrada area="control_pedidos" />));
    expect(container.querySelector('[role=alert]').className).toContain('tienda-alerta-disponibilidad');
    expect(container.textContent).toContain('Consulta cerrada con piezas sin existencias');
    await act(() => boton('Reabrir consulta').click());
    expect(axios.post.mock.calls[0][0]).toContain('reabrir-consulta'); expect(boton('Retirar')).toBeDefined();
    expect(container.querySelector('[aria-label="Ajuste de piezas"]')).toBeNull();
    await act(() => boton('Modificar pedido').click()); expect(container.querySelector('[aria-label="Ajuste de piezas"]')).not.toBeNull();
});
it('los ajustes guardados permanecen visibles cuando la captura avanzada está plegada', async () => {
    const pedido = { id: 1, solicitud_preparacion: { revision_disponibilidad: { estado: 'excepciones_pendientes' }, anexo_sin_remision: [{ descripcion: 'Alternativa enviada', entrada: 'documento' }] } };
    await act(() => root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} area="control_pedidos" />));
    expect(container.textContent).toContain('Alternativa enviada'); expect(container.textContent).toContain('Cambios guardados del pedido');
    expect(container.querySelector('[aria-label="Ajuste de piezas"]')).toBeNull();
});

it('genera la clave contra duplicados cuando el acceso HTTP no ofrece randomUUID', () => {
    const original = globalThis.crypto;
    vi.stubGlobal('crypto', { getRandomValues: original.getRandomValues.bind(original) });
    try { expect(uuidSolicitud()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/); }
    finally { vi.unstubAllGlobals(); }
});
