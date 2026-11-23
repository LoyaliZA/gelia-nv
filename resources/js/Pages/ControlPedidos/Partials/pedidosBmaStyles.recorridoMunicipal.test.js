import { describe, expect, it } from 'vitest';
import { badgeEstatusPedido, estadoRecorridoMunicipal, etiquetaEstatusPedido } from './pedidosBmaStyles';

const enviado = { fase_ciclo: 'ENVIADO', nombre_visual: 'Enviado' };
const municipal = (extra = {}, solicitud = {}) => ({
    estatus: enviado,
    solicitud_preparacion: { modalidad_entrega: 'municipal', recorrido_fisico_municipal: true, ...solicitud },
    ...extra,
});

describe('etiquetaEstatusPedido municipal con recorrido físico', () => {
    it('muestra la salida a terminal por encima del estado de sucursal', () => {
        const pedido = municipal({ estatus_sucursal: null }, { salida_terminal: { at: '2026-11-21T10:00:00-06:00', receptor: 'Terminal ficticia' } });
        expect(etiquetaEstatusPedido(enviado, { pedido })).toBe('Salida a terminal');
        expect(badgeEstatusPedido(enviado, { pedido }).label).toBe('Salida a terminal');
    });

    it('distingue traslado, recepción y custodia en sucursal', () => {
        expect(etiquetaEstatusPedido(enviado, { pedido: municipal({ estatus_sucursal: 'en_transito' }) })).toBe('En traslado a sucursal');
        for (const estado of ['recepcion_gerente_parcial', 'recibido', 'en_recepcion', 'custodia_parcial']) {
            expect(etiquetaEstatusPedido(enviado, { pedido: municipal({ estatus_sucursal: estado }) })).toBe('Pendiente de custodia');
        }
        expect(etiquetaEstatusPedido(enviado, { pedido: municipal({ estatus_sucursal: 'en_custodia' }) })).toBe('En custodia');
    });

    it('nunca muestra «Enviado» para un municipal marcado sin estado de sucursal', () => {
        expect(etiquetaEstatusPedido(enviado, { pedido: municipal({ estatus_sucursal: null }) })).toBe('En traslado a sucursal');
    });

    it('no cambia fases previas a la salida ni pedidos cancelados', () => {
        const borrador = { fase_ciclo: 'BORRADOR', nombre_visual: 'Borrador' };
        expect(estadoRecorridoMunicipal(municipal({ estatus: borrador }), 'BORRADOR')).toBeNull();
        expect(estadoRecorridoMunicipal(municipal({ estatus_sucursal: 'en_custodia' }), 'CANCELADO')).toBeNull();
    });

    it('conserva «Enviado» para municipales sin marca y para nacionales', () => {
        const sinMarca = { estatus: enviado, estatus_sucursal: 'en_custodia', solicitud_preparacion: { modalidad_entrega: 'municipal' } };
        expect(etiquetaEstatusPedido(enviado, { pedido: sinMarca })).toBe(etiquetaEstatusPedido(enviado));
        const nacional = { estatus: enviado, solicitud_preparacion: { modalidad_entrega: 'nacional', recorrido_fisico_municipal: true } };
        expect(etiquetaEstatusPedido(enviado, { pedido: nacional })).toBe(etiquetaEstatusPedido(enviado));
    });

    it('no altera la etiqueta de transferencia de Tienda', () => {
        const tienda = { estatus: enviado, estatus_sucursal: 'en_custodia', solicitud_preparacion: { modalidad_entrega: 'tienda', cobertura_tienda: { tipo: 'transferencia' } } };
        expect(etiquetaEstatusPedido(enviado, { pedido: tienda })).toBe('En custodia');
    });
});
