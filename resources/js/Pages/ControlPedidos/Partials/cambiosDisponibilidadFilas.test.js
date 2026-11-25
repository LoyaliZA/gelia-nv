import { describe, expect, it } from 'vitest';
import { filasDeCambios, tonoDePieza } from './cambiosDisponibilidad';

describe('tonoDePieza', () => {
    it.each([
        [{ estado_fisico: 'bueno' }, 'exito'],
        [{ estado_fisico: 'sin_existencia' }, 'peligro'],
        [{ estado_fisico: 'regular' }, 'aviso'],
        [{ estado_fisico: 'regular', resolucion: { accion: 'confirmar' } }, 'exito'],
        [{ estado_fisico: 'regular', resolucion: { accion: 'aceptar_condicion' } }, 'exito'],
        [{ estado_fisico: 'bueno', resolucion: { accion: 'retirar' } }, 'peligro'],
        [{ estado_fisico: 'bueno', resolucion: { accion: 'sustituir' } }, 'aviso'],
    ])('%j → %s', (fila, tono) => expect(tonoDePieza(fila)).toBe(tono));
});

describe('filasDeCambios', () => {
    const tienda = { modalidad_entrega: 'tienda', area_revision: 'preparacion_tienda' };
    it('un anexo sin responder es una fila amarilla «Por revisar»', () => {
        const { actuales } = filasDeCambios({ ...tienda, anexo_sin_remision: [{ solicitud_id: 's', sku: 'A', descripcion: 'Perfume A', cantidad: 2, pendiente_revision: true }] }, []);
        expect(actuales).toHaveLength(1);
        expect(actuales[0]).toMatchObject({ tono: 'aviso', etiqueta: 'Por revisar', tipo: 'anexo', descripcion: 'Perfume A', sku: 'A', cantidad: 2 });
    });
    it('con avance pendiente sigue amarillo aunque la unidad tenga resultado guardado', () => {
        const { actuales } = filasDeCambios({ ...tienda, anexo_sin_remision: [{ solicitud_id: 's', sku: 'A', pendiente_revision: true,
            unidades: [{ unidad_id: 'anexo:s:sku:A', resultado: 'correcto' }] }] }, []);
        expect(actuales[0]).toMatchObject({ tono: 'aviso', etiqueta: 'Por revisar' });
    });
    it('una decisión de confirmar sin pieza en la tabla es una fila verde', () => {
        const solicitud = { acciones_disponibilidad: { 5: { accion: 'confirmar', revision_id: 5, descripcion: 'Perfume B', sku: 'B' } } };
        const { actuales } = filasDeCambios(solicitud, []);
        expect(actuales[0]).toMatchObject({ tono: 'exito', etiqueta: 'Confirmado con el cliente', tipo: 'confirmacion' });
    });
    it('no duplica la decisión de una pieza que ya está en la tabla', () => {
        const solicitud = { acciones_disponibilidad: { 5: { accion: 'confirmar', revision_id: 5, descripcion: 'Perfume B' } } };
        expect(filasDeCambios(solicitud, [], { revisionIdsEnTabla: new Set([5]) })).toEqual({ actuales: [], anteriores: [] });
    });
    it('un retiro es una fila roja y se omite si su pieza ya está en la tabla', () => {
        const solicitud = { retiro_piezas: [{ solicitud_id: 'r', piezas: [{ sku: 'C', descripcion: 'Perfume C', cantidad: 1, revision_id: 9 }] }] };
        expect(filasDeCambios(solicitud, []).anteriores.concat(filasDeCambios(solicitud, []).actuales)[0]).toMatchObject({ tono: 'peligro', etiqueta: 'Retiro solicitado' });
        const oculta = filasDeCambios(solicitud, [], { revisionIdsEnTabla: new Set([9]) });
        expect([...oculta.actuales, ...oculta.anteriores]).toHaveLength(0);
    });
    it('una solicitud sin cambios no produce filas', () => {
        expect(filasDeCambios({}, [])).toEqual({ actuales: [], anteriores: [] });
    });
});
