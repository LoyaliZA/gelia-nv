import { describe, expect, it } from 'vitest';
import { cambiosDisponibilidad, equivalenciasUnidades } from './cambiosDisponibilidad';

describe('identidad de las unidades por revisar', () => {
    it('distingue líneas con la misma solicitud, documento y SKU', () => {
        const documentos = [{ id: 71, url: '/pedido.png' }];
        const solicitud = { anexo_sin_remision: ['linea-a', 'linea-b'].map(linea_id => ({
            linea_id, solicitud_id: 'misma-solicitud', documento_id: 71, sku: 'MISMO',
        })) };
        const cambios = cambiosDisponibilidad(solicitud, documentos);
        expect(cambios.map(c => c.clave)).toEqual(['anexo:linea-a:doc:71', 'anexo:linea-b:doc:71']);
    });

    it('reconcilia la clave provisional con su documento sin elegir otra línea', () => {
        const documentos = [{ id: 71, url: '/a' }, { id: 72, url: '/b' }];
        const anteriores = cambiosDisponibilidad({ anexo_sin_remision: [{ documento_id: 71 }, { documento_id: 72 }] }, documentos);
        const actuales = cambiosDisponibilidad({ anexo_sin_remision: [
            { linea_id: 'linea-a', documento_id: 71, unidades: [{ unidad_id: 'anexo:linea-a:doc:71', documento_id: 71 }] },
            { linea_id: 'linea-b', documento_id: 72, unidades: [{ unidad_id: 'anexo:linea-b:doc:72', documento_id: 72 }] },
        ] }, documentos);
        const equivalencias = equivalenciasUnidades(anteriores, actuales);
        expect(equivalencias.get('anexo:doc:71').clave).toBe('anexo:linea-a:doc:71');
        expect(equivalencias.get('anexo:doc:72').clave).toBe('anexo:linea-b:doc:72');
        expect(equivalenciasUnidades(anteriores, [actuales[1]]).has('anexo:doc:71')).toBe(false);
    });

    it('no reasigna una clave histórica ambigua entre varias líneas', () => {
        const antes = [0, 1].map(indice => ({ clave: 'anexo:sol:doc:71', tipo: 'anexo', documento_id: 71, indice, solicitud_id: 'sol', esDocumento: true }));
        const despues = antes.map((c, i) => ({ ...c, clave: `anexo:linea-${i}:doc:71`, linea_id: `linea-${i}` }));
        expect(equivalenciasUnidades(antes, despues).size).toBe(0);
    });

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
