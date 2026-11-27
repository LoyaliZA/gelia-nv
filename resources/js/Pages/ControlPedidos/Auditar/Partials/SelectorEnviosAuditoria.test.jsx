// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import SelectorEnviosAuditoria, {
    esGrupoMultienvio,
    estadoRevisionEnvio,
    progresoAprobadosEnvios,
} from './SelectorEnviosAuditoria';

let host;
let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

const hermano = (extra = {}) => ({
    id: 1, orden: 1, folio: 'PBMA-1', folio_remision: 'BND-A',
    estatus_fase: 'PENDIENTE_AUXILIAR', pago_validado: false, tiene_remision: false,
    ...extra,
});

const pedido = (extra = {}, hermanos = null) => ({
    id: 1,
    folio_remision: 'BND-A',
    multienvio: {
        id: 9, orden: 1, n_total: 3, estado: 'activo', parcial: false,
        hermanos: hermanos || [
            hermano(),
            hermano({ id: 2, orden: 2, folio: 'PBMA-2', folio_remision: 'BND-B' }),
            hermano({ id: 3, orden: 3, folio: 'PBMA-3', folio_remision: 'BND-C', pago_validado: true }),
        ],
    },
    ...extra,
});

it('nombra el estado de revisión de cada envío', () => {
    expect(estadoRevisionEnvio(hermano()).texto).toBe('Pago por validar');
    expect(estadoRevisionEnvio(hermano({ pago_validado: true })).texto).toBe('Pago validado');
    expect(estadoRevisionEnvio(hermano({ pago_validado: true, tiene_remision: true })).texto).toBe('Remisionado');
    expect(estadoRevisionEnvio(hermano({ estatus_fase: 'EN_CEDIS', pago_validado: true, tiene_remision: true })).texto).toBe('Aprobado');
    expect(estadoRevisionEnvio(hermano({ estatus_fase: 'RECHAZADO_VENDEDORA' })).texto).toBe('Con observación');
});

it('cuenta aprobados sin incluir pendientes ni observados', () => {
    expect(progresoAprobadosEnvios([
        hermano(),
        hermano({ id: 2, estatus_fase: 'EN_CEDIS' }),
        hermano({ id: 3, estatus_fase: 'RECHAZADO_VENDEDORA' }),
    ])).toEqual({ aprobados: 1, total: 3 });
});

it('un pedido ordinario o un grupo de un envío no es un selector', () => {
    expect(esGrupoMultienvio({})).toBe(false);
    expect(esGrupoMultienvio({ multienvio: { hermanos: [hermano()] } })).toBe(false);
    expect(esGrupoMultienvio(pedido())).toBe(true);
});

it('lista los tres envíos, marca el actual y avisa el progreso', async () => {
    await act(async () => root.render(<SelectorEnviosAuditoria pedido={pedido()} />));
    const selector = host.querySelector('[data-selector-envios-auditoria]');
    expect(selector.textContent).toContain('0 de 3 envíos aprobados');
    const tabs = host.querySelectorAll('[role="tab"]');
    expect(tabs).toHaveLength(3);
    expect(tabs[0].getAttribute('aria-current')).toBe('true');
    expect(tabs[0].disabled).toBe(true);
    expect(tabs[1].textContent).toContain('Envío 2 de 3');
    expect(tabs[1].textContent).toContain('BND-B');
    expect(tabs[2].textContent).toContain('Pago validado');
});

it('al elegir otro envío llama onSeleccionar con su id', async () => {
    const onSeleccionar = vi.fn();
    await act(async () => root.render(<SelectorEnviosAuditoria pedido={pedido()} onSeleccionar={onSeleccionar} />));
    await act(async () => host.querySelectorAll('[role="tab"]')[1].click());
    expect(onSeleccionar).toHaveBeenCalledWith(2);
});

it('no se muestra sin grupo', async () => {
    await act(async () => root.render(<SelectorEnviosAuditoria pedido={{ id: 8, folio_remision: 'X' }} />));
    expect(host.querySelector('[data-selector-envios-auditoria]')).toBeNull();
});
