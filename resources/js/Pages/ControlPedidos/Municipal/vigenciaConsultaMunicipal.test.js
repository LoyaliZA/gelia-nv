import { expect, it } from 'vitest';
import { fusionarLecturaMunicipal, lecturaMunicipalMasNueva } from './vigenciaConsultaMunicipal';

const pedido = (respondidaAt, extra = {}) => ({
    updated_at: '2026-11-17T12:00:00-06:00',
    solicitud_preparacion: {
        modalidad_entrega: 'municipal',
        consulta_municipal: { estado: 'respondida' },
        revision_disponibilidad: { respondida_at: respondidaAt, respuesta_id: extra.respuesta_id || 'a', estado: 'sin_excepciones' },
        finanzas_municipales: { leyenda_terminal: 'PAGADO' },
    },
});

it('acepta una respuesta posterior y descarta una anterior', () => {
    const actual = pedido('2026-11-17T12:10:00-06:00');
    const nueva = pedido('2026-11-17T12:20:00-06:00', { respuesta_id: 'b' });
    const vieja = pedido('2026-11-17T12:05:00-06:00', { respuesta_id: 'c' });
    expect(lecturaMunicipalMasNueva(actual, nueva)).toBe(true);
    expect(lecturaMunicipalMasNueva(actual, vieja)).toBe(false);
    expect(fusionarLecturaMunicipal(actual, vieja).solicitud_preparacion.revision_disponibilidad.respuesta_id).toBe('a');
    expect(fusionarLecturaMunicipal(actual, nueva).solicitud_preparacion.revision_disponibilidad.respuesta_id).toBe('b');
});

it('no usa el UUID como orden cuando la fecha empata', () => {
    const actual = pedido('2026-11-17T12:10:00-06:00', { respuesta_id: 'vigente' });
    const otra = pedido('2026-11-17T12:10:00-06:00', { respuesta_id: 'tardia' });
    expect(lecturaMunicipalMasNueva(actual, otra)).toBe(false);
});

it('conserva terminal y finanzas locales al fusionar la revisión', () => {
    const actual = { ...pedido('2026-11-17T12:00:00-06:00'), catalogo_paqueteria_id: 9, cliente_id: 3 };
    const lectura = {
        ...pedido('2026-11-17T12:30:00-06:00', { respuesta_id: 'nueva' }),
        revisiones_producto: [{ id: 1 }],
        documentos: [{ id: 4, tipo: 'evidencia_condicion' }],
    };
    const fusion = fusionarLecturaMunicipal(actual, lectura);
    expect(fusion.catalogo_paqueteria_id).toBe(9);
    expect(fusion.cliente_id).toBe(3);
    expect(fusion.revisiones_producto).toEqual([{ id: 1 }]);
    expect(fusion.solicitud_preparacion.finanzas_municipales.leyenda_terminal).toBe('PAGADO');
});

it('ordena reapertura, segunda respuesta y cierre en el mismo segundo por la bitácora', () => {
    const base = pedido('2026-11-17T12:00:00-06:00');
    const primera = { ...base, version_consulta: 10 };
    const reapertura = { ...base, version_consulta: 12, consulta_cerrada: false, solicitud_preparacion: { ...base.solicitud_preparacion,
        revision_disponibilidad: { ...base.solicitud_preparacion.revision_disponibilidad, actualizacion_id: 'z', actualizacion_pendiente: true } } };
    const segunda = { ...pedido('2026-11-17T12:00:00-06:00', { respuesta_id: 'b' }), version_consulta: 13 };
    expect(lecturaMunicipalMasNueva(primera, reapertura)).toBe(true);
    expect(lecturaMunicipalMasNueva(reapertura, segunda)).toBe(true);
    expect(lecturaMunicipalMasNueva(segunda, reapertura)).toBe(false);
    expect(lecturaMunicipalMasNueva(segunda, primera)).toBe(false);
});

it('fusiona ajustes y decisiones, conserva finanzas y permite limpiar un bloqueo', () => {
    const actual = { ...pedido('2026-11-17T12:00:00-06:00'), version_consulta: 20, motivo_bloqueo_cierre: 'Pendiente' };
    const lectura = { ...pedido('2026-11-17T12:00:00-06:00'), version_consulta: 21, motivo_bloqueo_cierre: null };
    Object.assign(lectura.solicitud_preparacion, { anexo_sin_remision: [{ solicitud_id: 'a' }], retiro_piezas: [{ solicitud_id: 'r' }], acciones_disponibilidad: { pieza: 'retirar' }, finanzas_municipales: { leyenda_terminal: 'AJENA' } });
    const fusion = fusionarLecturaMunicipal(actual, lectura);
    expect(fusion.solicitud_preparacion.anexo_sin_remision).toEqual([{ solicitud_id: 'a' }]);
    expect(fusion.solicitud_preparacion.retiro_piezas).toEqual([{ solicitud_id: 'r' }]);
    expect(fusion.solicitud_preparacion.acciones_disponibilidad).toEqual({ pieza: 'retirar' });
    expect(fusion.solicitud_preparacion.finanzas_municipales).toEqual(actual.solicitud_preparacion.finanzas_municipales);
    expect(fusion.motivo_bloqueo_cierre).toBeNull();
});
