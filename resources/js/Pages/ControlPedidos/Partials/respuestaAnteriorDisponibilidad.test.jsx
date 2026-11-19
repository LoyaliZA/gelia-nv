// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ConsultaSolicitudTienda from './ConsultaSolicitudTienda';
import SeguimientoSolicitudMunicipal from '../Municipal/SeguimientoSolicitudMunicipal';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let root, container;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const base = (extra = {}) => ({
    id: 1,
    solicitud_preparacion: {
        modalidad_entrega: 'tienda', area_revision: 'preparacion_tienda',
        revision_disponibilidad: { estado: 'excepciones_pendientes', respondida_at: '2026-11-01T10:00:00Z', actualizacion_pendiente: true, actualizacion_id: 'ciclo-2' },
        anexo_sin_remision: [{ solicitud_id: 'sol-1', entrada: 'catalogo', sku: 'B-2', descripcion: 'Parcial B', cantidad: 1, pendiente_revision: true,
            unidades: [{ unidad_id: 'anexo:sol-1:sku:B-2', clase: 'sku', resultado: 'incidencia', revision_ids: [22] }] }],
    },
    revisiones_producto: [
        { id: 11, estado_fisico: 'regular', descripcion_producto: 'Pieza de la respuesta anterior', cantidad_solicitada: 1 },
        { id: 22, estado_fisico: 'regular', descripcion_producto: 'Pieza revisada a medias', cantidad_solicitada: 1 },
    ],
    ...extra,
});
it('Tienda: una pieza guardada por persistir_unidad no se mezcla con la respuesta anterior', async () => {
    await act(() => root.render(<ConsultaSolicitudTienda pedido={base()} />));
    expect(container.textContent).toContain('Pieza de la respuesta anterior');
    expect(container.textContent).not.toContain('Pieza revisada a medias');
    expect(container.textContent).toContain('Respuesta anterior de disponibilidad');
});
it('Tienda: al responder el ciclo la pieza aparece bajo la respuesta vigente', async () => {
    const pedido = base();
    pedido.solicitud_preparacion.revision_disponibilidad.actualizacion_pendiente = false;
    pedido.solicitud_preparacion.anexo_sin_remision[0].pendiente_revision = false;
    pedido.solicitud_preparacion.anexo_sin_remision[0].respondida_at = '2026-11-02T10:00:00Z';
    await act(() => root.render(<ConsultaSolicitudTienda pedido={pedido} />));
    expect(container.textContent).toContain('Pieza revisada a medias');
    expect(container.textContent).not.toContain('Respuesta anterior de disponibilidad');
});
it('Municipal: una pieza guardada por persistir_unidad no se mezcla con la respuesta anterior', async () => {
    const pedido = base();
    pedido.solicitud_preparacion.modalidad_entrega = 'municipal';
    await act(() => root.render(<SeguimientoSolicitudMunicipal pedido={pedido} />));
    expect(container.textContent).toContain('Pieza de la respuesta anterior');
    expect(container.textContent).not.toContain('Pieza revisada a medias');
});
