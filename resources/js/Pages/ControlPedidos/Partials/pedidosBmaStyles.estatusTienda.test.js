import { describe, expect, it } from 'vitest';
import { etiquetaEstatusPedido, textoWhatsAppPedido } from './pedidosBmaStyles';

describe('etiquetaEstatusPedido tienda', () => {
    const pendiente = { fase_ciclo: 'PESAJE_PENDIENTE', nombre_visual: 'Pesaje pendiente' };
    const respondido = { fase_ciclo: 'PESAJE_RESPONDIDO', nombre_visual: 'Pesaje respondido' };

    it('mantiene pesaje cuando el origen requiere logística', () => {
        expect(etiquetaEstatusPedido(pendiente, { requiereLogistica: true })).toBe('Pesaje pendiente');
        expect(etiquetaEstatusPedido(respondido)).toBe('Pesaje respondido');
    });

    it('usa separación cuando el origen es tienda', () => {
        expect(etiquetaEstatusPedido(pendiente, { requiereLogistica: false })).toBe('Separación pendiente');
        expect(etiquetaEstatusPedido(respondido, { requiereLogistica: false })).toBe('Separación respondida');
    });

    it('incluye la etiqueta de tienda en el texto de WhatsApp', () => {
        const texto = decodeURIComponent(textoWhatsAppPedido({
            folio: 'BMA-1',
            cliente: { nombre: 'Cliente' },
            total_a_cobrar: 10,
            estatus: pendiente,
            origen: { requiere_logistica: false },
        }));
        expect(texto).toContain('Estado: Separación pendiente');
    });
});
