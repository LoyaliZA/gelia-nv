// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ModalAccionSolicitud from '@/Pages/Solicitudes/Partials/ModalAccionSolicitud';
import FiltrosSolicitudes from '@/Components/Filtros/FiltrosSolicitudes';
import { eventosHistorial, leerSnapshotSeguro } from '@/Pages/Solicitudes/Partials/solicitudHistorial';
import { efectosCancelacionSolicitud, recorridoListaCancelacion } from '@/Pages/Solicitudes/Partials/efectosCancelacionSolicitud';
import { ACCION_VISUAL, estiloEstadoSolicitud, identidadProceso } from '@/Pages/Solicitudes/Partials/solicitudesStyles';
import ModalDesgloseTipoCliente from '@/Pages/Solicitudes/Partials/ModalDesgloseTipoCliente';

let root, container;
const solicitud = { id: 42, monto_cotizado: 3500, catalogo_lista_descuento_id: 9, proceso: { nombre: 'TAG y cambio de lista' }, cliente: { nombre: 'Cliente', lista_descuento: { monto_requerido: 3000 } }, lista_descuento: { monto_requerido: 5000 } };
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

describe('solicitud visual identity', () => {
    it('gives each process its own icon key and keeps status tones off the process color', () => {
        expect(identidadProceso({ proceso: { nombre: 'ASIGNAR TAG' } }).clave).toBe('tag');
        expect(identidadProceso({ proceso: { nombre: 'CAMBIO DE LISTA' } }).clave).toBe('lista');
        expect(identidadProceso({ proceso: { nombre: 'TAG y cambio de lista' } }).clave).toBe('tag-lista');
        expect(identidadProceso({ compra_en_tienda: true }).clave).toBe('tienda');
        expect(estiloEstadoSolicitud('respondida').tono).toBe('exito');
        expect(estiloEstadoSolicitud('incorrecta').tono).toBe('peligro');
        expect(estiloEstadoSolicitud(undefined).tono).toBe('aviso');
        expect(ACCION_VISUAL.aprobar.tono).toBe('exito');
        expect(ACCION_VISUAL.pago.tono).toBe('info');
        expect(ACCION_VISUAL.solicitar.tono).toBe('info');
        expect(ACCION_VISUAL.error.tono).toBe('aviso');
        expect(ACCION_VISUAL.eliminar.tono).toBe('peligro');
        expect(new Set([ACCION_VISUAL.solicitar.tono, ACCION_VISUAL.error.tono, ACCION_VISUAL.eliminar.tono]).size).toBe(3);
    });

    it('renders the action modal with the flow header and a tonal notice', async () => {
        await act(() => root.render(React.createElement(ModalAccionSolicitud, { accion: 'pago', solicitud, onClose: vi.fn() })));
        expect(document.querySelector('h2.gelia-solicitudes-titulo')?.textContent).toBe('Confirmar pago');
        expect(document.querySelector('.gelia-flujo-icono')?.getAttribute('data-tono')).toBe('info');
        expect(document.querySelector('.gelia-aviso-solicitud')?.getAttribute('data-tono')).toBe('info');
        expect(document.querySelector('[data-dialog-close]')?.getAttribute('aria-label')).toBe('Cerrar acción');
    });
});

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

    it('does not ask for a lower list and previews the list the client returns to', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const conAscenso = {
            ...solicitud,
            estado: { nombre: 'Respondida' },
            lista_descuento: { nombre: 'MAYOREO ORO', monto_requerido: 5000 },
            vendedor: { name: 'Ana Ventas' },
            auditorias: [{ id: 1, datos_snapshot: { antes: { lista_nombre: 'PUBLICO GENERAL', tag_vendedor_nombre: 'Tag previo' } } }],
        };
        await act(() => root.render(React.createElement(ModalAccionSolicitud, { accion: 'solicitar', solicitud: conAscenso, onClose: vi.fn() })));
        expect(document.querySelector('#accion-lista')).toBeNull();
        expect(document.body.textContent).toContain('El cliente regresa a PUBLICO GENERAL');
        expect(document.body.textContent).toContain('El TAG se retira');
        await input('#accion-motivo', 'El cliente pospuso su compra.');
        await submit();
        expect(post).toHaveBeenCalledWith('/solicitudes.solicitar_cancelacion/42', { motivo_cancelacion: 'El cliente pospuso su compra.' }, expect.any(Object));
    });

    it('previews the rollback in the supervision confirmation modal', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        const conAscenso = {
            ...solicitud,
            estado: { nombre: 'Respondida' },
            cancelacion_solicitada_at: '2026-11-26T12:00:00Z',
            motivo_cancelacion: 'El cliente pospuso su compra.',
            monto_aplicado_al_cliente: 6000,
            lista_descuento: { nombre: 'MAYOREO ORO', monto_requerido: 5000 },
            vendedor: { name: 'Ana Ventas' },
            auditorias: [{ id: 1, datos_snapshot: { antes: { lista_nombre: 'PUBLICO GENERAL', tag_vendedor_nombre: null } } }],
        };
        await act(() => root.render(React.createElement(ModalAccionSolicitud, { accion: 'cancelar', solicitud: conAscenso, onClose: vi.fn() })));
        expect(document.body.textContent).toContain('Cambios al confirmar la cancelación');
        expect(document.body.textContent).toContain('El cliente regresa a PUBLICO GENERAL');
        expect(document.body.textContent).toContain('El TAG se retira');
        expect(document.body.textContent).toContain('Se revierte el monto operacional');
        await submit();
        expect(put).toHaveBeenCalledWith('/solicitudes.cancelar/42', {}, expect.any(Object));
    });

    it('does not ask for a lower list when the request did not change the client list', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const sinLista = { ...solicitud, catalogo_lista_descuento_id: null };
        await act(() => root.render(React.createElement(ModalAccionSolicitud, { accion: 'solicitar', solicitud: sinLista, listas: [{ id: 1, nombre: 'Público', monto_requerido: 0 }], onClose: vi.fn() })));
        expect(document.querySelector('#accion-lista')).toBeNull();
        await input('#accion-motivo', 'El pedido cambió de piezas.');
        await submit();
        expect(post).toHaveBeenCalledWith('/solicitudes.solicitar_cancelacion/42', { motivo_cancelacion: 'El pedido cambió de piezas.' }, expect.any(Object));
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

describe('desglose de tipo de cliente', () => {
    it('opens the type breakdown as a dialog with the flow header', async () => {
        await act(() => root.render(React.createElement(ModalDesgloseTipoCliente, {
            resumen: { tipos: [{ id: 3, nombre: 'ACTIVO', solicitudes: 1, clientes: 1, vigentes: 0, errores: 0 }] },
            onFiltrarTipo: vi.fn(),
            onLimpiarTipo: vi.fn(),
            onClose: vi.fn(),
        })));
        expect(document.querySelector('h2.gelia-solicitudes-titulo')?.textContent).toBe('Tipos de cliente');
        expect(document.querySelector('[aria-label="Filtrar por ACTIVO: 1 solicitudes"]')).not.toBeNull();
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

describe('cancellation rollback preview', () => {
    const base = {
        proceso: { nombre: 'ASIGNAR TAG Y CAMBIO DE LISTA' },
        catalogo_lista_descuento_id: 9,
        lista_descuento: { nombre: 'MAYOREO ORO' },
        vendedor: { name: 'Ana Ventas' },
        cliente: { lista_almacenada_nombre: 'PUBLICO GENERAL' },
        auditorias: [{ id: 1, datos_snapshot: { antes: { lista_nombre: 'PUBLICO GENERAL', tag_vendedor_nombre: 'Tag previo' } } }],
    };

    it('keeps the original ascent until cancellation is requested', () => {
        expect(recorridoListaCancelacion({ ...base, estado: { nombre: 'Pendiente' } })).toEqual(expect.objectContaining({
            origen: 'PUBLICO GENERAL',
            destino: 'MAYOREO ORO',
            hayCambio: true,
            cancelada: false,
        }));
    });

    it('inverts the list flow as soon as cancellation is requested', () => {
        expect(recorridoListaCancelacion({
            ...base,
            estado: { nombre: 'Respondida' },
            cancelacion_solicitada_at: '2026-11-26T12:00:00Z',
        })).toEqual(expect.objectContaining({
            origen: 'MAYOREO ORO',
            destino: 'PUBLICO GENERAL',
            hayCambio: true,
            cancelada: true,
            descenso: true,
        }));
    });

    it('inverts the list flow after an approved request is cancelled', () => {
        const cancelada = {
            ...base,
            estado: { nombre: 'Cancelada' },
            auditorias: [
                ...base.auditorias,
                { id: 2, estado_anterior: { nombre: 'Respondida' }, estado_nuevo: { nombre: 'Cancelada' } },
            ],
        };
        expect(recorridoListaCancelacion(cancelada)).toEqual(expect.objectContaining({
            origen: 'MAYOREO ORO',
            destino: 'PUBLICO GENERAL',
            hayCambio: true,
            cancelada: true,
            descenso: true,
        }));
        expect(efectosCancelacionSolicitud(cancelada).retiraTag).toBe(true);
    });
});
