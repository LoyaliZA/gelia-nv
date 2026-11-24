import { describe, expect, it } from 'vitest';
import { etiquetaRespuestaDetalle, resolverModalidadDetalle, textoPagosPedido } from './resolverModalidadDetalle';

describe('resolverModalidadDetalle', () => {
    it('separa las tres capturas actuales del circuito anterior', () => {
        expect(resolverModalidadDetalle({ solicitud_preparacion: { modalidad_entrega: 'tienda' } })).toBe('tienda');
        expect(resolverModalidadDetalle({ solicitud_preparacion: { modalidad_entrega: 'municipal' } })).toBe('municipal');
        expect(resolverModalidadDetalle({ solicitud_preparacion: { modalidad_entrega: 'nacional' } })).toBe('nacional');
        expect(resolverModalidadDetalle({ solicitud_preparacion: null })).toBe('legado');
        expect(resolverModalidadDetalle({ solicitud_preparacion: { atencion_tienda: null } })).toBe('tienda');
    });
    it('resume la respuesta y los pagos sin inventar texto', () => {
        expect(etiquetaRespuestaDetalle({})).toBe('Pendiente');
        expect(etiquetaRespuestaDetalle({ pesaje_respondido_at: '2026-11-24' })).toBe('Recibida');
        expect(textoPagosPedido({ fuentes_pago: ['BANORTE'] })).toBe('BANORTE');
        expect(textoPagosPedido({ banco: { nombre: 'SANTANDER' } })).toBe('SANTANDER');
        expect(textoPagosPedido({})).toBeNull();
    });
});
