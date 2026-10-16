/**
 * @vitest-environment node
 */
import { describe, expect, it } from 'vitest';
import { resumenActualizacionConsulta } from './resumenActualizacionConsulta';
import { calcularTotalACubrirPedido } from '../../Partials/pedidosBmaStyles';
import { separarCostoEnvioDeReexpedicion } from '../../Partials/resolverReexpedicionForm';

const pieza = (extra) => ({
    revision_id: 7,
    sku: 'PZ-1',
    label: 'Pieza',
    estado: 'danado',
    cantidad_solicitada: 1,
    cantidad_encontrada: 1,
    ...extra,
});

describe('resumen de actualización CEDIS', () => {
    it('no anuncia cambio de piezas por una observación', () => {
        const resumen = resumenActualizacionConsulta([pieza()], [pieza()]);
        expect(resumen.sinCambioPiezas).toBe(true);
        expect(resumen.retiradas).toHaveLength(0);
    });

    it('distingue retirada, cantidad y sustitución', () => {
        const antes = [pieza(), pieza({ revision_id: 8, sku: 'PZ-2', label: 'Otra' })];
        const despues = [
            pieza({ cantidad_encontrada: 0 }),
            pieza({ revision_id: 9, sku: 'PZ-3', label: 'Nueva' }),
        ];
        const resumen = resumenActualizacionConsulta(antes, despues);
        expect(resumen.cantidades).toHaveLength(1);
        expect(resumen.retiradas.map((p) => p.sku)).toEqual(['PZ-2']);
        expect(resumen.agregadas.map((p) => p.sku)).toEqual(['PZ-3']);
    });
});

describe('envío a cubrir', () => {
    it('no suma otra vez la reexpedición si el campo ya la incluye', () => {
        const total = calcularTotalACubrirPedido({
            totalMercancia: 1000,
            costoEnvio: 150,
            costoReexpedicion: 150,
            separarCostoEnvio: separarCostoEnvioDeReexpedicion,
            envioEsBase: false,
        });
        expect(total).toBe(1150);
    });

    it('suma la reexpedición una vez cuando el campo es solo flete', () => {
        const total = calcularTotalACubrirPedido({
            totalMercancia: 1000,
            costoEnvio: 100,
            costoReexpedicion: 150,
            envioEsBase: true,
        });
        expect(total).toBe(1250);
    });
});
