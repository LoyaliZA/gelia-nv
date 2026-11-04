import { describe, expect, it } from 'vitest';
import { estadoConsultaPedido, badgeEstatusEnvio, etiquetaEstatusEnvio, textoWhatsAppPedido } from './pedidosBmaStyles';
import { badgesListaPedido } from './pedidosListadoUi';
const pendiente = {
    id: 1, estatus: { fase_ciclo: 'PESAJE_PENDIENTE' }, estatus_envio: 'pendiente_pesaje',
    solicitud_preparacion: { modalidad_entrega: 'tienda', area_revision: 'preparacion_tienda' },
    origen: { requiere_logistica: false },
};
const conRespuesta = (pedido = pendiente, estado = 'sin_excepciones') => ({ ...pedido,
    solicitud_preparacion: { ...pedido.solicitud_preparacion, revision_disponibilidad: { estado } },
});
const etiquetas = (pedido) => badgesListaPedido(pedido).map(b => b.label);
describe('Consulta separada del apartado y del pesaje', () => {
    it.each(['preparacion_tienda', 'control_pedidos'])('unifica la consulta de %s, sin depender del departamento', (area) => {
        for (const departamento_id of [1, 2]) {
            const pedido = { ...pendiente, departamento_id, solicitud_preparacion: { ...pendiente.solicitud_preparacion, area_revision: area } };
            expect(etiquetas(pedido)).toEqual(['Pendiente de respuesta']);
            expect(etiquetas(conRespuesta(pedido))).toEqual(['Consulta respondida']);
        }
    });
    it.each(['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'])('una respuesta %s sustituye la etiqueta pendiente aunque la fase no cambie', (estado) => {
        const pedido = conRespuesta(pendiente, estado);
        expect(etiquetas(pedido)).toEqual(['Consulta respondida']);
        expect(etiquetaEstatusEnvio('pendiente_pesaje', { pedido })).toBe('Consulta respondida');
        expect(decodeURIComponent(textoWhatsAppPedido(pedido))).toContain('Estado: Consulta respondida');
    });
    it('responder en tienda no confirma el apartado ni activa cotización de envío', () => {
        expect(etiquetas({ ...conRespuesta(), estatus_envio: 'pesaje_listo' })).toEqual(['Consulta respondida']);
    });
    it('el apartado confirmado se presenta con etiqueta gráfica legible independiente', () => {
        const pedido = { ...conRespuesta(), estatus_envio: 'apartado_confirmado' };
        expect(etiquetas(pedido)).toEqual(['Consulta respondida', 'Apartado confirmado']);
        const badge = badgeEstatusEnvio('apartado_confirmado', { pedido, faseCiclo: 'PESAJE_PENDIENTE' });
        expect(badge.className).toContain('border');
        expect(badge.style.color).toBe('var(--theme-text-main)');
        expect(etiquetaEstatusEnvio('apartado_confirmado')).toBe('Apartado confirmado');
    });
    it('reconoce el apartado físico aun cuando el estado de envío quedó en pesaje listo', () => {
        const pedido = { ...conRespuesta(), estatus_envio: 'pesaje_listo', tarea_preparacion: { respuesta: { respondida: true, apartado_confirmado: true } } };
        expect(etiquetas(pedido)).toEqual(['Consulta respondida', 'Apartado confirmado']);
    });
    it('la evidencia o la tarea en atención no equivalen a responder', () => {
        expect(estadoConsultaPedido({ ...pendiente, tarea_preparacion: { estado: 'EN_ATENCION', respuesta: { respondida: false, documentos: [{ url: '/foto.png' }] } } })).toBe('pendiente');
    });
    it('un borrador sin enviar y las fases posteriores conservan su estado', () => {
        const borrador = { ...pendiente, estatus: { fase_ciclo: 'BORRADOR' }, estatus_envio: 'completo' };
        expect(estadoConsultaPedido(borrador)).toBeNull();
        expect(etiquetas(borrador)).toEqual(['Borrador']);
        expect(etiquetas({ ...conRespuesta(), estatus_envio: 'apartado_confirmado', estatus: { fase_ciclo: 'ENTREGADO' } })).toEqual(['Entregado']);
    });
    it('una reconsulta explícita pendiente no usa la respuesta anterior como vigente', () => {
        expect(etiquetas({ ...conRespuesta(), consulta_actualizacion_pendiente: true })).toEqual(['Pendiente de respuesta']);
    });
    it('mantiene el pesaje para un envío nacional con logística', () => {
        const pedido = { ...pendiente, origen: { requiere_logistica: true }, solicitud_preparacion: { modalidad_entrega: 'nacional', area_revision: 'control_pedidos' } };
        expect(estadoConsultaPedido(pedido)).toBeNull();
        expect(etiquetas(pedido)).toEqual(['Pesaje pendiente']);
        expect(etiquetas({ ...pedido, estatus_envio: 'pesaje_listo' })).toEqual(['Pesaje pendiente', 'Pesaje listo — cotizar envío']);
    });
});
it('al cerrar la consulta distingue separación aprobada y pesaje respondido', () => {
 expect(etiquetas({...conRespuesta(),consulta_cerrada:true})).toEqual(['Separación aprobada']);
 const logistico={...pendiente,origen:{requiere_logistica:true},solicitud_preparacion:{modalidad_entrega:'nacional'},consulta_cerrada:true};
 expect(etiquetas(logistico)).toEqual(['Pesaje respondido']);
});
