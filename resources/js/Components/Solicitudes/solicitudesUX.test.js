// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ModalAccionSolicitud from '@/Pages/Solicitudes/Partials/ModalAccionSolicitud';
import FiltrosSolicitudes from '@/Components/Filtros/FiltrosSolicitudes';
import { eventosHistorial, leerSnapshotSeguro } from '@/Pages/Solicitudes/Partials/solicitudHistorial';

let root, container;
const solicitud = { id: 42, monto_cotizado: 3500, proceso: { nombre: 'TAG y cambio de lista' }, cliente: { nombre: 'Cliente', lista_descuento: { monto_requerido: 3000 } }, lista_descuento: { monto_requerido: 5000 } };
const input = async (selector, value) => act(() => {
    const field = document.querySelector(selector);
    const prototype = field.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, 'value').set.call(field, value);
    field.dispatchEvent(new Event('input', { bubbles: true }));
});
const submit = () => act(() => document.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('route', (name, id) => `/${name}/${id}`);
    vi.spyOn(HTMLElement.prototype, 'getClientRects').mockReturnValue([{ width: 100, height: 44 }]);
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('Tag and Lista action forms', () => {
    it('keeps payment data and focuses the error when saving fails, and closes only after success', async () => {
        const close = vi.fn(); let options;
        const put = vi.spyOn(router, 'put').mockImplementation((url, data, callbacks) => { options = callbacks; });
        await act(() => root.render(React.createElement(ModalAccionSolicitud, { accion: 'pago', solicitud, onClose: close })));
        await input('#accion-monto', '4200.50');
        await submit();
        expect(put).toHaveBeenLastCalledWith('/solicitudes.confirmar_pago/42', { modo: 'pago', monto_final_pagado: '4200.50' }, expect.any(Object));
        await act(() => { options.onError({ monto_final_pagado: 'Revisa el monto cobrado.' }); options.onFinish(); });
        expect(close).not.toHaveBeenCalled();
        expect(document.querySelector('#accion-monto').value).toBe('4200.50');
        expect(document.activeElement.id).toBe('accion-monto');
        expect(document.querySelector('[role="alert"]').textContent).toContain('Revisa el monto');
        await submit();
        await act(() => { options.onSuccess({}); options.onFinish(); });
        expect(close).toHaveBeenCalledOnce();
    });

    it('offers only active lower lists and sends the cancellation reason to the existing endpoint', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const listas = [
            { id: 1, nombre: 'Público', monto_requerido: 0 },
            { id: 2, nombre: 'Plata', monto_requerido: 3000 },
            { id: 3, nombre: 'Oro', monto_requerido: 5000 },
            { id: 4, nombre: 'Desactivada', monto_requerido: 2000, activo: false },
            { id: 5, nombre: 'Colaborador', monto_requerido: 1000 },
        ];
        await act(() => root.render(React.createElement(ModalAccionSolicitud, { accion: 'solicitar', solicitud, listas, onClose: vi.fn() })));
        expect([...document.querySelectorAll('#accion-lista option')].map(option => option.value)).toEqual(['', '2', '1']);
        await act(() => { const select = document.querySelector('#accion-lista'); select.value = '2'; select.dispatchEvent(new Event('change', { bubbles: true })); });
        await input('#accion-motivo', 'El cliente pospuso su compra.');
        await submit();
        expect(post).toHaveBeenCalledWith('/solicitudes.solicitar_cancelacion/42', { motivo_cancelacion: 'El cliente pospuso su compra.', catalogo_lista_rebaja_id: '2' }, expect.any(Object));
    });

    it('retains a visible confirmation and the audit reason when deleting a request', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        await act(() => root.render(React.createElement(ModalAccionSolicitud, { accion: 'eliminar', solicitud, onClose: vi.fn() })));
        expect(destroy).not.toHaveBeenCalled();
        await input('#accion-motivo', 'Solicitud duplicada por error.');
        await submit();
        expect(destroy).toHaveBeenCalledWith('/solicitudes.destroy/42', expect.objectContaining({ data: { motivo: 'Solicitud duplicada por error.' } }));
    });
});

describe('Tag and Lista filters', () => {
    it('keeps the query and selected owner while explicitly applying advanced filters', async () => {
        const apply = vi.fn();
        await act(() => root.render(React.createElement(FiltrosSolicitudes, { variante: 'tag-lista', tabActiva: 'TODAS', busqueda: 'FOL-42', tipoFecha: 'TODAS', fechaInicio: '', fechaFin: '', filtroVendedor: '7', filtroMotivo: '', vendedores: [{ id: 7, name: 'Ventas' }], filtrosActivos: 0, onAplicarFiltros: apply, onCambiarTab: vi.fn() })));
        await act(() => document.querySelector('button[aria-controls]').click());
        expect(apply).not.toHaveBeenCalled();
        await act(() => { const period = document.querySelector('#filtro-fecha-tipo'); period.value = 'MES'; period.dispatchEvent(new Event('change', { bubbles: true })); });
        await act(() => [...document.querySelectorAll('button')].find(button => button.textContent === 'Aplicar filtros').click());
        expect(apply).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'FOL-42', vendedor_id: '7', tipo_fecha: 'MES' }));
    });
});

describe('request history', () => {
    it('interleaves audits, questions and responses by date and can reverse the chronology without changing the records', () => {
        const source = { auditorias: [{ id: 99, created_at: '2026-10-09T09:00:00Z' }, { id: 1, created_at: '2026-10-09T11:00:00Z' }], consultas: [{ id: 3, created_at: '2026-10-09T10:00:00Z', updated_at: '2026-10-09T12:00:00Z', estado: 'respondida' }, { id: 4, created_at: '2026-10-09T08:00:00Z', estado: 'pendiente' }] };
        const before = JSON.stringify(source);
        expect(eventosHistorial(source).map(event => event.key)).toEqual(['respuesta-3', 'audit-1', 'consulta-3', 'audit-99', 'consulta-4']);
        expect(eventosHistorial(source, 'antiguo').map(event => event.key)).toEqual(['consulta-4', 'audit-99', 'consulta-3', 'audit-1', 'respuesta-3']);
        expect(JSON.stringify(source)).toBe(before);
    });
    it('handles historical malformed snapshots without breaking the request view', () => {
        expect(leerSnapshotSeguro('{broken')).toBeNull();
        expect(leerSnapshotSeguro('{"monto_cotizado":3000}')).toEqual({ monto_cotizado: 3000 });
        expect(leerSnapshotSeguro(null)).toBeNull();
    });
});
