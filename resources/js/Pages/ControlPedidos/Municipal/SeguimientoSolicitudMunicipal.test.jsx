// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Seguimiento from './SeguimientoSolicitudMunicipal';

vi.mock('../Partials/AccionesRapidasDisponibilidad', () => ({ default: () => <span>Acciones</span> }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({
    default: () => null,
    MiniaturaDocumento: ({ documento }) => <img alt="" src={documento.url} />,
    esPdf: () => false,
}));
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({ default: () => <input aria-label="Producto" /> }));

let root, host;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); });

const pedido = {
    id: 44, folio: 'PBMA-44', folio_remision: 'WIZ-44', cliente: { nombre: 'Cliente ficticio' },
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
    expect(host.textContent).not.toContain('Modificar pedido');
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
    const modificar = [...host.querySelectorAll('button')].find(b => b.textContent.includes('Modificar pedido'));
    expect(modificar).toBeTruthy();
    await act(() => modificar.click());
    const nota = host.querySelector('#ajuste-nota');
    await act(() => {
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        setter.call(nota, 'Retirar la pieza dañada');
        nota.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(() => modificar.click());
    expect(host.querySelector('#ajuste-nota').value).toBe('Retirar la pieza dañada');
    expect(modificar.getAttribute('aria-expanded')).toBe('false');
});

it('muestra la captura vigente una sola vez al final del seguimiento', async () => {
    await act(() => root.render(<Seguimiento pedido={{
        ...pedido,
        documentos: [{ id: 9, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto.jpg', mime_type: 'image/jpeg', activo: true }],
        solicitud_preparacion: {
            ...pedido.solicitud_preparacion,
            consulta_municipal: { ...pedido.solicitud_preparacion.consulta_municipal, estado: 'respondida' },
            revision_disponibilidad: { estado: 'sin_excepciones', respondida_at: '2026-11-17T11:00:00-06:00', evidencia_actual_ids: [9] },
        },
    }} />));
    expect(host.querySelectorAll('[aria-label="Captura del pedido"]')).toHaveLength(1);
    expect(host.querySelector('[aria-label="Resultado de la consulta en CEDIS"]')?.querySelector('[aria-label="Captura del pedido"]')).toBeNull();
});
