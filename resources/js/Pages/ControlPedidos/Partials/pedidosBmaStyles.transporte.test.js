import { describe, expect, it } from 'vitest';
import { etiquetaTransportePedido, LABEL_ENVIO_PARA_TIENDA } from './pedidosBmaStyles';

describe('etiquetaTransportePedido', () => {
    it('prioriza paquetería en envíos con logística', () => {
        expect(etiquetaTransportePedido({
            origen: { requiere_logistica: true },
            paqueteria: { nombre: 'DHL' },
            envio_tienda: { nombre: 'Tienda' },
        })).toBe('DHL');
    });

    it('muestra envío para tienda cuando el catálogo es Tienda', () => {
        expect(etiquetaTransportePedido({
            origen: { requiere_logistica: false },
            envio_tienda: { nombre: 'Tienda' },
        })).toBe(LABEL_ENVIO_PARA_TIENDA);
    });

    it('usa envio_tienda_otro cuando aplica', () => {
        expect(etiquetaTransportePedido({
            origen: { requiere_logistica: false },
            envio_tienda: { nombre: 'Otro', es_otro: true },
            envio_tienda_otro: 'Mostrador norte',
        })).toBe('Mostrador norte');
    });

    it('respalda con origen sin logística aunque falte envio_tienda', () => {
        expect(etiquetaTransportePedido({
            origen: { requiere_logistica: false },
        })).toBe(LABEL_ENVIO_PARA_TIENDA);
    });

    it('devuelve null en envío sin paquetería asignada', () => {
        expect(etiquetaTransportePedido({
            origen: { requiere_logistica: true },
        })).toBeNull();
    });
});
