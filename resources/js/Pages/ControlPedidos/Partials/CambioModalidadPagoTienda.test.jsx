// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import CambioModalidadPagoTienda from './CambioModalidadPagoTienda';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let container, root;
const solicitud = { cobertura_tienda: { tipo: 'apartado' }, atencion_tienda: 'no' };
const estado = { version: 'a'.repeat(64), puede_solicitar: true, puede_autorizar: false, solicitud: null, historial: [], bloqueo_aplicacion: null };
const onSolicitud = vi.fn(), onPendiente = vi.fn();
const boton = nombre => [...container.querySelectorAll('button')].find(b => b.textContent.includes(nombre));
async function render(extra = {}) { await act(async () => { root.render(<CambioModalidadPagoTienda pedidoId={9} solicitud={solicitud} onSolicitud={onSolicitud} onPendiente={onPendiente} {...extra} />); await Promise.resolve(); }); }
async function texto(selector, value) { await act(() => { const el = container.querySelector(selector); Object.getOwnPropertyDescriptor(el instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLSelectElement.prototype, 'value').set.call(el, value); el.dispatchEvent(new Event('change', { bubbles: true })); el.dispatchEvent(new Event('input', { bubbles: true })); }); }
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; axios.get.mockResolvedValue({ data: estado }); container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('solicitar conserva modalidad vigente, exige motivo y cancelar no escribe', async () => {
 await render(); await act(() => boton('Solicitar cambio').click());
 expect(onPendiente).toHaveBeenLastCalledWith(true);
 expect(boton('Enviar solicitud').disabled).toBe(true);
 await act(() => boton('Cancelar').click());
 expect(axios.post).not.toHaveBeenCalled(); expect(onPendiente).toHaveBeenLastCalledWith(false);
});
it('envía una solicitud con versión y UUID, y adopta la modalidad vigente sin cambiarla', async () => {
 await render(); await act(() => boton('Solicitar cambio').click());
 await texto('select', 'credito'); await texto('textarea', 'El cliente solicita crédito');
 const pendiente = { ...estado, solicitud: { id: '11111111-1111-4111-8111-111111111111', estado: 'pendiente', anterior: { tipo: 'apartado' }, cobertura: 'credito', motivo: 'El cliente solicita crédito' } };
 axios.post.mockResolvedValue({ data: { estado_cobertura: pendiente, solicitud_preparacion: solicitud } });
 await act(async () => boton('Enviar solicitud').click());
 expect(axios.post.mock.lastCall[1]).toMatchObject({ accion: 'solicitar', cobertura: 'credito', version: estado.version, motivo: 'El cliente solicita crédito' });
 expect(axios.post.mock.lastCall[1].solicitud_id).toMatch(/^[a-f0-9-]{36}$/);
 expect(onSolicitud).toHaveBeenCalledWith(solicitud);
 expect(container.textContent).toContain('Cambio pendiente de autorización');
});
it('un pedido con remisión permite rechazar pero bloquea aplicar y no ofrece autoautorizarse', async () => {
 const pendiente = { id: '11111111-1111-4111-8111-111111111111', estado: 'pendiente', anterior: { tipo: 'apartado' }, cobertura: 'credito', motivo: 'Corrección del cliente' };
 axios.get.mockResolvedValue({ data: { ...estado, solicitud: pendiente, puede_autorizar: true, bloqueo_aplicacion: 'Hay remisión. Regularice su proceso.' } });
 await render(); await texto('textarea', 'No procede por remisión vigente');
 expect(boton('Autorizar y aplicar').disabled).toBe(true);
 expect(boton('Rechazar cambio').disabled).toBe(false);
 axios.post.mockResolvedValue({ data: { estado_cobertura: estado, solicitud_preparacion: solicitud } });
 await act(async () => boton('Rechazar cambio').click());
 expect(axios.post.mock.lastCall[1]).toMatchObject({ accion: 'rechazar', solicitud_id: pendiente.id });
 axios.get.mockResolvedValue({ data: { ...estado, solicitud: pendiente, puede_autorizar: false } });
 await render({ pedidoId: 10 });
 expect(boton('Autorizar y aplicar')).toBeUndefined();
});
it('un error de lectura permite reintentar sin habilitar una modificación directa', async () => {
 axios.get.mockRejectedValueOnce(new Error('red')); await render();
 expect(container.querySelector('[role="alert"]')).toBeTruthy(); expect(boton('Solicitar cambio')).toBeUndefined();
 await act(async () => boton('Reintentar consulta').click());
 expect(boton('Solicitar cambio')).toBeTruthy();
});

it('autorizar envía solo la decisión y adopta el cambio aplicado por el servidor', async () => {
 const pendiente = { id: '11111111-1111-4111-8111-111111111111', estado: 'pendiente', anterior: { tipo: 'apartado' }, cobertura: 'credito', motivo: 'Solicitud del cliente' };
 axios.get.mockResolvedValue({ data: { ...estado, solicitud: pendiente, puede_autorizar: true } });
 await render(); await texto('textarea', 'Aprobado antes de separar mercancía');
 const aplicada = { cobertura_tienda: { tipo: 'credito' } };
 axios.post.mockResolvedValue({ data: { estado_cobertura: estado, solicitud_preparacion: aplicada } });
 await act(async () => boton('Autorizar y aplicar').click());
 expect(axios.post.mock.lastCall[1]).toEqual({ accion: 'autorizar', solicitud_id: pendiente.id, motivo: 'Aprobado antes de separar mercancía' });
 expect(onSolicitud).toHaveBeenCalledWith(aplicada);
});
