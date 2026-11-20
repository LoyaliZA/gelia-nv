import { describe, expect, it } from 'vitest';
import { cambiosDisponibilidad } from './cambiosDisponibilidad';

describe('identidad de las unidades por revisar', () => {
    it('reutiliza el unidad_id del servidor para la nueva pieza solicitada sin perder el anexo ya revisado', () => {
        const documentos = [{ id: 5, url: '/a' }, { id: 9, url: '/b' }];
        const solicitud = { anexo_sin_remision: [
            { solicitud_id: 's1', documento_id: 5, documentos: [{ id: 5 }], unidades: [{ unidad_id: 'anexo:s1:doc:5', documento_id: 5, resultado: 'incidencia' }] },
            { entrada: 'documento', documento_id: 9, descripcion: 'Alternativa en documento', cantidad: 1, pendiente_revision: true, sustituye_revision_id: 3,
                unidades: [{ unidad_id: 'anexo:L-1:doc:9', documento_id: 9, resultado: null }] },
        ] };
        const cambios = cambiosDisponibilidad(solicitud, documentos);
        const nueva = cambios.find((c) => c.documento_id === 9);
        expect(nueva.unidad_id).toBe('anexo:L-1:doc:9');
        expect(nueva.pendiente).toBe(true);
        expect(cambios.find((c) => c.documento_id === 5).unidad_id).toBe('anexo:s1:doc:5');
    });
});
