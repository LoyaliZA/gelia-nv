import { describe, expect, it } from 'vitest';
import { esCapturaTienda, usaRegistroActual } from './flujoCapturaPedido';

const borrador = { id: 125, folio_remision: '20261004', estatus: { fase_ciclo: 'BORRADOR' }, solicitud_preparacion: null, origen: null, origen_id: null, pagos_exhibicion: [], documentos: [] };
describe('Selección de captura de pedidos', () => {
    it('abre el alta y el borrador sin modalidad en el registro actual', () => {
        expect(usaRegistroActual(null)).toBe(true);
        expect(usaRegistroActual(borrador)).toBe(true);
        expect(usaRegistroActual({ ...borrador, solicitud_preparacion: { modalidad_entrega: null } })).toBe(true);
    });
    it('conserva los pedidos emitidos del piloto, aunque sean de Tienda', () => {
        expect(usaRegistroActual({ ...borrador, estatus: { fase_ciclo: 'PESAJE_PENDIENTE' }, usa_preparacion_tienda: true })).toBe(false);
        expect(usaRegistroActual({ ...borrador, origen: { nombre: 'Tienda' } })).toBe(false);
        expect(usaRegistroActual({ ...borrador, solicitud_preparacion: { codigo_modalidad: 'RECOGE_TIENDA' } })).toBe(false);
    });
    it.each([
        { pagos_exhibicion: [{ id: 1 }] }, { documentos: [{ tipo: 'remision' }] },
        { tarea_preparacion: { id: 1 } }, { numero_remision: 'REM-1' },
        { pesaje_solicitado_at: '2026-11-01' }, { pago_validado_at: '2026-11-01' },
        { resguardo_apartado_at: '2026-11-01' },
    ])('no reclasifica un borrador con operación previa: %j', (operacion) => {
        expect(usaRegistroActual({ ...borrador, ...operacion })).toBe(false);
    });
    it('reconoce Tienda actual aun después de emitirse y conserva los segmentos de envío', () => {
        expect(esCapturaTienda({ solicitud_preparacion: { modalidad_entrega: 'tienda' } })).toBe(true);
        expect(esCapturaTienda({ solicitud_preparacion: { codigo_modalidad: 'RECOGE_TIENDA', atencion_tienda: null } })).toBe(true);
        for (const modalidad of ['municipal', 'nacional']) {
            expect(usaRegistroActual({ solicitud_preparacion: { modalidad_entrega: modalidad, piezas_tienda: [] } })).toBe(false);
        }
    });
});
