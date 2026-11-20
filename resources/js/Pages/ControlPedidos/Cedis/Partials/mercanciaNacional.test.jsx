import { expect, it } from 'vitest';
import { precargarMercanciaNacional } from './ModalResponderPesaje';
import { cedisConsultaEsRevisionMercancia } from '../../Partials/pedidosBmaStyles';

it('mantiene el formulario de pesaje para la paquetería pendiente de carga nacional', () => {
    const pedido = {
        solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida' },
        paqueteria: { nombre: 'PAQ. PENDIENTE', categoria: 'local_regional', requiere_peso: false, sin_logistica: true },
        consulta_cedis_modo_revision_mercancia: false,
    };
    expect(cedisConsultaEsRevisionMercancia(pedido)).toBe(false);
    expect(cedisConsultaEsRevisionMercancia({ ...pedido, solicitud_preparacion: {} })).toBe(true);
});

it('recupera respuestas separadas y omite el retiro aunque las descripciones sean iguales', () => {
    const inicial = [
        { id: 'primera', descripcion: 'Aroma', cantidad: 2, documento_ids: [10] },
        { id: 'segunda', descripcion: 'Aroma', cantidad: 1, documento_ids: [11] },
    ];
    const pedido = {
        solicitud_preparacion: {
            modalidad_entrega: 'nacional', captura_nacional: 'compartida',
            piezas_nacionales: inicial, respuestas_piezas_nacionales: { primera: 1, segunda: 2 },
        },
    };
    const previas = [
        { revision_id: 2, descripcion_producto: 'Aroma', estado_fisico: 'sin_existencia', cantidad_encontrada: 0 },
        { revision_id: 1, descripcion_producto: 'Aroma', estado_fisico: 'bueno', cantidad_encontrada: 2 },
    ];
    expect(precargarMercanciaNacional(pedido, previas)).toMatchObject([
        { client_uuid: 'primera', revision_id: 1, cantidad_encontrada: 2, documentoIds: [10] },
        { client_uuid: 'segunda', revision_id: 2, cantidad_encontrada: 0, documentoIds: [11] },
    ]);
    pedido.solicitud_preparacion.piezas_nacionales_vigentes = [{ ...inicial[0], cantidad: 1 }];
    expect(precargarMercanciaNacional(pedido, previas)).toMatchObject([
        { client_uuid: 'primera', revision_id: 1, cantidad_solicitada: 1, cantidad_encontrada: 1, documentoIds: [10] },
    ]);
});
