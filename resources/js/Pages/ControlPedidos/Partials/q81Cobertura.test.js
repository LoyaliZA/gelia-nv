import { expect, it } from 'vitest';
import { calcularTotalACubrirPedido, calcularResumenCoberturaPago, mezclarResumenCoberturaConPedido } from './pedidosBmaStyles';
import { separarCostoEnvioDeReexpedicion } from './resolverReexpedicionForm';

// Los mismos importes contractuales se ejercitan en Q8AccesoPagosSafEvidenciaTest.
for (const detalle of [false, true]) for (const saf of [0, 80]) for (const parcial of [0, 200]) for (const extra of [0, 25]) {
    it(`presenta pendiente y excedente coherentes: cajas=${detalle}, SAF=${saf}, parcial=${parcial}, extra=${extra}`, () => {
        const total = calcularTotalACubrirPedido({ totalMercancia: 1000, costoEnvio: detalle ? 100 : 250,
            costoReexpedicion: 150, envioEsBase: detalle, separarCostoEnvio: separarCostoEnvioDeReexpedicion });
        expect(total).toBe(1250);
        const antes = calcularResumenCoberturaPago({ totalMercancia: 1000, costoEnvio: total - 1000, saldoAFavorAplicado: saf, totalPagado: parcial });
        expect(antes.pendiente).toBe(1250 - saf - parcial);
        const despues = calcularResumenCoberturaPago({ totalMercancia: 1000, costoEnvio: total - 1000,
            saldoAFavorAplicado: saf, totalPagado: parcial + antes.pendiente + extra });
        const visible = mezclarResumenCoberturaConPedido({ pendiente: 999, excedente_generado: 999, bloqueos: ['Revisar comprobante'] }, despues);
        expect(visible.pendiente).toBe(0);
        expect(visible.excedente_generado).toBe(extra);
        expect(visible.cobertura).toBe(extra ? 'con_excedente' : 'cubierto');
        expect(visible.bloqueos).toEqual(['Revisar comprobante']);
    });
}
