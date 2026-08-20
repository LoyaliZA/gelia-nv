import { describe, expect, it } from 'vitest';
import { validarCamposEnvioPedido } from './pedidosBmaStyles';

const baseTienda = {
    folio_remision: 'F-1',
    cliente_id: 1,
    origen_id: 2,
    almacen_id: 3,
    total_mercancia: 100,
};

describe('validarCamposEnvioPedido — tienda', () => {
    it('exige PDF o foto del pedido (no solo pago)', () => {
        const sinPdf = validarCamposEnvioPedido(baseTienda, {
            requiereLogistica: false,
            tienePdfPedido: false,
            pagoPendiente: 0,
        });
        expect(sinPdf.valido).toBe(false);
        expect(sinPdf.claves).toContain('pdf_pedido');

        const conPdf = validarCamposEnvioPedido(baseTienda, {
            requiereLogistica: false,
            tienePdfPedido: true,
            pagoPendiente: 0,
        });
        expect(conPdf.valido).toBe(true);
    });
});
