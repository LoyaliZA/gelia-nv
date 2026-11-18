// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Seguimiento from './SeguimientoSolicitudMunicipal';

vi.mock('../Partials/AccionesRapidasDisponibilidad', () => ({ default: () => <span>Acciones</span> }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));

let root, host;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); });

const pedido = {
    folio: 'PBMA-44', folio_remision: 'WIZ-44', cliente: { nombre: 'Cliente ficticio' },
    paqueteria: { nombre: 'Terminal ficticia' },
    almacen: { nombre: 'CEDIS Norte' },
    solicitud_preparacion: {
        modalidad_entrega: 'municipal',
        consulta_municipal: { area: 'cedis', estado: 'pendiente', enviada_at: '2026-11-17T10:00:00-06:00' },
    },
};

it('identifica el envío municipal y nombra la espera sin hablar de aceptación del cliente', async () => {
    await act(() => root.render(<Seguimiento pedido={pedido} />));
    expect(host.textContent).toContain('Envío municipal');
    expect(host.textContent).toContain('Terminal ficticia');
    expect(host.textContent).toContain('CEDIS Norte');
    expect(host.textContent).toContain('Consulta pendiente: CEDIS');
    expect(host.textContent).toContain('CEDIS o Tienda confirman mercancía');
    expect(host.textContent).not.toContain('aceptación del cliente');
});

it('muestra excepciones pendientes y bloquea el cierre', async () => {
    await act(() => root.render(<Seguimiento pedido={{
        ...pedido,
        motivo_bloqueo_cierre: 'Hay excepciones pendientes. La consulta no puede cerrarse.',
        solicitud_preparacion: {
            ...pedido.solicitud_preparacion,
            consulta_municipal: { ...pedido.solicitud_preparacion.consulta_municipal, estado: 'excepciones_pendientes' },
            revision_disponibilidad: { estado: 'excepciones_pendientes', respondida_at: '2026-11-17T11:00:00-06:00', aceptacion_vigente: false },
        },
        revisiones_producto: [{ id: 1, descripcion_producto: 'Pieza', estado_fisico: 'danado', comentario: 'Golpe' }],
    }} onCerrar={() => {}} />));
    expect(host.textContent).toContain('Excepciones pendientes');
    expect([...host.querySelectorAll('button')].some(b => b.textContent.includes('Cerrar consulta'))).toBe(false);
});
