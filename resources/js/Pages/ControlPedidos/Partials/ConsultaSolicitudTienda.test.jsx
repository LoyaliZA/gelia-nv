// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConsultaSolicitudTienda from './ConsultaSolicitudTienda';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let root, container;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const pedido = { id: 1, puede_cerrar_consulta: true, solicitud_preparacion: { modalidad_entrega: 'tienda', area_revision: 'preparacion_tienda', revision_disponibilidad: { estado: 'sin_excepciones' } } };
describe('Respuesta actual en las vistas de consulta', () => {
    it('muestra la respuesta sin habilitar cierre ni ajustes aunque el usuario tenga permiso', async () => {
        await act(() => root.render(<ConsultaSolicitudTienda pedido={pedido} />));
        expect(container.textContent).toContain('Resultado de la revisión en tienda');
        expect(container.textContent).toContain('Sin incidencias');
        expect(container.querySelectorAll('button,input,select,textarea')).toHaveLength(0);
    });
    it('muestra cierre y respuesta física sin habilitar acciones', async () => {
        await act(() => root.render(<ConsultaSolicitudTienda pedido={{ ...pedido, consulta_cerrada: true, tarea_preparacion: { estado: 'RESPONDIDA', respuesta: { respondida: true, apartado_confirmado: true, productos: [], documentos: [], cantidad: 0 } } }} />));
        expect(container.textContent).toContain('Resultado del apartado en tienda');
        expect(container.textContent).toContain('Consulta cerrada');
        expect(container.querySelectorAll('button,input')).toHaveLength(0);
    });
    it('distingue un borrador sin enviar de una respuesta pendiente', async () => {
        await act(() => root.render(<ConsultaSolicitudTienda pedido={{ id: 2, solicitud_preparacion: { modalidad_entrega: 'tienda' } }} />));
        expect(container.textContent).toContain('aún no se ha enviado');
    });
    it('no reemplaza la consulta de pedidos legados ni de envíos', async () => {
        for (const solicitud of [null, { codigo_modalidad: 'RECOGE_TIENDA' }, { modalidad_entrega: 'nacional' }]) {
            await act(() => root.render(<ConsultaSolicitudTienda pedido={{ id: 3, solicitud_preparacion: solicitud }} />));
            expect(container.textContent).toBe('');
        }
    });
});
