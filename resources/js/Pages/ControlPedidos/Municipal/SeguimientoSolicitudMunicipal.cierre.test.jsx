// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Seguimiento from './SeguimientoSolicitudMunicipal';

vi.mock('../Partials/AccionesRapidasDisponibilidad', () => ({ default: () => <span>Acciones</span> }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({ default: () => <input aria-label="Producto" /> }));

let root;
let host;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); });

it('al cerrar envía modalidad esperada y versión de consulta', async () => {
    const onCerrar = vi.fn();
    await act(() => root.render(<Seguimiento onCerrar={onCerrar} pedido={{
        id: 44,
        version_consulta: 8,
        puede_cerrar_consulta: true,
        cliente: { nombre: 'Cliente ficticio' },
        paqueteria: { nombre: 'Terminal ficticia' },
        solicitud_preparacion: {
            modalidad_entrega: 'municipal',
            consulta_municipal: { area: 'cedis', estado: 'respondida', enviada_at: '2026-11-17T10:00:00-06:00' },
            revision_disponibilidad: { estado: 'sin_excepciones', respondida_at: '2026-11-17T11:00:00-06:00' },
        },
    }} />));
    await act(() => [...host.querySelectorAll('button')].find((el) => el.textContent.includes('Cerrar consulta')).click());
    expect(onCerrar).toHaveBeenCalledWith({ modalidad_esperada: 'municipal', version_consulta: 8 });
});

it('oculta el cierre cuando el servidor bloquea por reserva', async () => {
    await act(() => root.render(<Seguimiento onCerrar={() => {}} pedido={{
        id: 44,
        puede_cerrar_consulta: false,
        motivo_bloqueo_cierre: 'Hay un cambio de modalidad en curso',
        cliente: { nombre: 'Cliente ficticio' },
        paqueteria: { nombre: 'Terminal ficticia' },
        solicitud_preparacion: {
            modalidad_entrega: 'municipal',
            consulta_municipal: { area: 'cedis', estado: 'respondida' },
            revision_disponibilidad: { estado: 'sin_excepciones' },
        },
    }} />));
    expect([...host.querySelectorAll('button')].some((el) => el.textContent.includes('Cerrar consulta'))).toBe(false);
    expect(host.textContent).toContain('Hay un cambio de modalidad en curso');
});
