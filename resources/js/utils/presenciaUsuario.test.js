import { describe, expect, it } from 'vitest';
import { clasePresencia, textoPresencia, textoVistoPorUltimaVez } from './presenciaUsuario';

describe('textoPresencia', () => {
    const ahora = new Date('2026-11-02T22:00:00Z');

    it('muestra En línea y omite estados de trabajo', () => {
        expect(textoPresencia({
            en_linea: true,
            etiqueta: 'En junta',
            mensaje: 'Cierre semanal',
        }, ahora)).toBe('En línea');
        expect(clasePresencia({ en_linea: true, estado: 'disponible' })).toBe('gelia-presencia--en-linea');
    });

    it('cambia a visto por última vez al salir de línea', () => {
        const iso = new Date(ahora.getTime() - 10 * 60 * 1000).toISOString();
        expect(textoPresencia({
            en_linea: false,
            etiqueta: 'Disponible',
            ultima_actividad_at: iso,
        }, ahora)).toBe('Visto por última vez hace 10 min');
        expect(clasePresencia({ en_linea: false, estado: 'disponible' })).toBe('gelia-presencia--ausente');
    });

    it('usa la hora de Ciudad de México si fue hoy', () => {
        const iso = new Date(ahora.getTime() - 2 * 60 * 60 * 1000).toISOString();
        expect(textoVistoPorUltimaVez(iso, ahora)).toMatch(/^Visto por última vez a las /);
    });
});
