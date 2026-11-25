import { expect, it } from 'vitest';
import { cambiosDisponibilidad, particionarCambiosConsulta } from '../../Partials/cambiosDisponibilidad';
import { solicitudNacionalConCambios } from './ajustesNacionalesComoCambios';

const pedido = {
    consulta_actualizacion_pendiente: true,
    pesaje_respondido_at: '2026-11-24T10:00:00Z',
    documentos: [{ id: 7, url: '/anexo.png', activo: true, created_at: '2026-11-24T09:00:00Z' }, { id: 8, url: '/retiro.png', activo: true, created_at: '2026-11-24T08:00:00Z' }, { id: 9, url: '/nuevo.png', activo: true, created_at: '2026-11-24T10:30:00Z' }],
    solicitud_preparacion: {
        ajustes_nacionales: [
            { solicitud_id: 'viejo', tipo: 'anexo', registrado_at: '2026-11-24T09:00:00Z', documento_ids: [7] },
            { solicitud_id: 'sin-fecha-retiro', tipo: 'retiro', documento_ids: [], piezas: [{ revision_id: 9, sku: 'Q', descripcion: 'Antiguo', cantidad: 1 }] },
            { solicitud_id: 'sin-fecha-anexo-nuevo', tipo: 'anexo', documento_ids: [9] },
            { solicitud_id: 'sin-fecha-anexo-viejo', tipo: 'anexo', documento_ids: [8] },
            { solicitud_id: 'a', tipo: 'anexo', registrado_at: '2026-11-24T11:00:00Z', documento_ids: [7] },
            { solicitud_id: 'r', tipo: 'retiro', registrado_at: '2026-11-24T12:00:00Z', documento_ids: [8], piezas: [{ revision_id: 3, sku: 'X1', descripcion: 'Aroma', cantidad: 1 }] },
            { solicitud_id: 's', tipo: 'sustitucion', registrado_at: '2026-11-24T13:00:00Z', documento_ids: [], piezas: [{ revision_id: 4, sku: 'Y1', descripcion: 'Viejo', cantidad: 1 }], alternativas: [{ sku: 'Z9', descripcion: 'Nuevo', cantidad: 1 }] },
        ],
    },
};

it('traduce los ajustes pendientes a cambios por revisar como los de Tienda', () => {
    const cambios = cambiosDisponibilidad(solicitudNacionalConCambios(pedido), pedido.documentos);
    const anexos = cambios.filter((c) => c.tipo === 'anexo');
    expect(anexos).toHaveLength(3);
    expect(anexos.every((c) => c.pendiente)).toBe(true);
    expect(anexos.find((c) => c.sku === 'Z9')).toMatchObject({ descripcion: 'Nuevo', retiradas: [{ revision_id: 4, sku: 'Y1', descripcion: 'Viejo' }] });
    const retiros = cambios.filter((c) => c.tipo === 'retiro');
    expect(retiros.map(c => c.solicitud_id)).toEqual(['r']);
    expect(retiros.every((c) => c.pendiente === false)).toBe(true);
});

it('un ajuste sin fecha solo cuenta si sus documentos son posteriores a la última respuesta', () => {
    const ids = solicitudNacionalConCambios(pedido).anexo_sin_remision.map((a) => a.solicitud_id);
    expect(ids).toContain('sin-fecha-anexo-nuevo');
    expect(ids).not.toContain('sin-fecha-anexo-viejo');
    expect(solicitudNacionalConCambios(pedido).retiro_piezas.map((r) => r.solicitud_id)).not.toContain('sin-fecha-retiro');
});

it('sin actualización pendiente no agrega cambios', () => {
    const solicitud = solicitudNacionalConCambios({ ...pedido, consulta_actualizacion_pendiente: false });
    expect(solicitud.anexo_sin_remision).toBeUndefined();
});

it('conserva ambas piezas de la sustitución nacional en el historial tras el repesaje', () => {
    const respondido = { ...pedido, consulta_actualizacion_pendiente: false, pesaje_respondido_at: '2026-11-24T14:00:00Z' };
    const solicitud = solicitudNacionalConCambios(respondido, { incluirHistorial: true });
    const cambios = cambiosDisponibilidad(solicitud, pedido.documentos);
    expect(particionarCambiosConsulta(cambios, solicitud, 'revision')).toMatchObject({ actuales: [], anteriores: [
        { pendiente: false, descripcion: 'Nuevo', retiradas: [{ revision_id: 4, descripcion: 'Viejo' }] },
    ] });
});
