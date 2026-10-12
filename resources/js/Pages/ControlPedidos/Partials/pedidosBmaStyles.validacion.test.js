import { describe, expect, it } from 'vitest';
import { calcularPesoCobradoGuia, esCotizacionLista, pedidoModoRevisionMercancia, soportePedidoDe, validarCamposEnvioPedido } from './pedidosBmaStyles';

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

describe('resguardo abierto — envío diferido', () => {
    const baseEnvio = {
        ...baseTienda,
        peso_real_kg: 2,
        catalogo_tipo_caja_id: 1,
        numero_cajas: 1,
    };

    it('no exige dirección al enviar (sí paquetería)', () => {
        const sinPaq = validarCamposEnvioPedido(baseEnvio, {
            requiereLogistica: true,
            direccionesNormalizadas: true,
            esResguardoAbierto: true,
            tienePesajeRespondido: true,
            tienePdfPedido: true,
            pagoPendiente: 0,
            consultaCerrada: true,
            requiereConsultaCerrada: true,
        });
        expect(sinPaq.valido).toBe(false);
        expect(sinPaq.claves).toContain('paqueteria');
        expect(sinPaq.claves).not.toContain('domicilio');

        const conPaq = validarCamposEnvioPedido({ ...baseEnvio, catalogo_paqueteria_id: 9 }, {
            requiereLogistica: true,
            direccionesNormalizadas: true,
            esResguardoAbierto: true,
            tienePesajeRespondido: true,
            tienePdfPedido: true,
            pagoPendiente: 0,
            consultaCerrada: true,
            requiereConsultaCerrada: true,
        });
        expect(conPaq.valido).toBe(true);
        expect(conPaq.claves).not.toContain('domicilio');
        expect(conPaq.claves).not.toContain('codigo_postal');
    });

    it('cotización lista sin paquetería (pago mercancía)', () => {
        expect(esCotizacionLista({
            requiereLogistica: true,
            cotizacionHabilitada: true,
            esResguardoAbierto: true,
            total_mercancia: 100,
        })).toBe(true);
    });
});

describe('pedidoModoRevisionMercancia', () => {
    it('toda operación con logística es pesaje, incluso una tarifa local sin peso', () => {
        expect(pedidoModoRevisionMercancia(null, { categoria: 'local_regional', requiere_peso: false }, true)).toBe(false);
        expect(pedidoModoRevisionMercancia(null, { categoria: 'local_regional', requiere_peso: true }, true)).toBe(false);
        expect(pedidoModoRevisionMercancia(null, { categoria: 'comercial', requiere_peso: false }, true)).toBe(false);
        expect(pedidoModoRevisionMercancia({}, null, true)).toBe(false);
        expect(pedidoModoRevisionMercancia({}, null, false)).toBe(true);
    });

    it('no exige peso CEDIS cuando la consulta es revisión de mercancía', () => {
        const paq = { categoria: 'local_regional', requiere_peso: false, modalidad_tarifa: 'fija' };
        const out = validarCamposEnvioPedido({
            ...baseTienda,
            catalogo_paqueteria_id: 4,
            catalogo_tipo_guia_id: 1,
            catalogo_zona_id: 1,
            codigo_postal: '86000',
            domicilio_entrega: 'Calle 1',
            costo_envio: 40,
        }, {
            requiereLogistica: false,
            tienePesajeRespondido: true,
            tienePdfPedido: true,
            pagoPendiente: 0,
            consultaCerrada: true,
            requiereConsultaCerrada: true,
            paqueteria: paq,
        });
        expect(out.claves).not.toContain('peso_real');
        expect(out.claves).not.toContain('tipo_caja');
        expect(out.valido).toBe(true);
    });
});

describe('calcularPesoCobradoGuia — ceil max(real, vol)', () => {
    it('sube al kg entero siguiente cuando hay decimales', () => {
        expect(calcularPesoCobradoGuia(8, 8.13)).toBe('9');
        expect(calcularPesoCobradoGuia(8.13, 7)).toBe('9');
        expect(calcularPesoCobradoGuia(12.5, 8)).toBe('13');
    });

    it('conserva enteros exactos y el máximo', () => {
        expect(calcularPesoCobradoGuia(8, 8)).toBe('8');
        expect(calcularPesoCobradoGuia(3, 5)).toBe('5');
        expect(calcularPesoCobradoGuia(4, 2)).toBe('4');
        expect(calcularPesoCobradoGuia('', '')).toBe('');
    });
});

describe('soportePedidoDe', () => {
    it('muestra la cotización cuando el pedido no tiene pdf_pedido', () => {
        const pedido = {
            id: 35,
            documentos: [
                { id: 78, tipo: 'cotizacion', url: '/pedidos/35/documentos/78', mime_type: 'image/jpeg', nombre_original: 'FLUJO BENITO.jpeg' },
            ],
        };
        expect(soportePedidoDe(pedido)?.nombre_original).toBe('FLUJO BENITO.jpeg');
    });

    it('prefiere el pdf del pedido sobre la cotización', () => {
        const pedido = {
            documentos: [
                { id: 1, tipo: 'cotizacion', url: '/c' },
                { id: 2, tipo: 'pdf_pedido', url: '/p' },
            ],
        };
        expect(soportePedidoDe(pedido)?.id).toBe(2);
    });
});
