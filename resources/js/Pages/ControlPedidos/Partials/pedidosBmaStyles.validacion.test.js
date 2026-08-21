import { describe, expect, it } from 'vitest';
import { calcularPesoCobradoGuia, esCotizacionLista, validarCamposEnvioPedido } from './pedidosBmaStyles';

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

    it('no exige dirección ni paquetería al enviar', () => {
        const r = validarCamposEnvioPedido(baseEnvio, {
            requiereLogistica: true,
            direccionesNormalizadas: true,
            esResguardoAbierto: true,
            tienePesajeRespondido: true,
            tienePdfPedido: true,
            pagoPendiente: 0,
            consultaCerrada: true,
            requiereConsultaCerrada: true,
        });
        expect(r.valido).toBe(true);
        expect(r.claves).not.toContain('domicilio');
        expect(r.claves).not.toContain('paqueteria');
        expect(r.claves).not.toContain('codigo_postal');
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
