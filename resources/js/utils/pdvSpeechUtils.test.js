// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    debeAnunciarTtsPdv,
    mensajeTtsPdv,
    PDV_TTS_TIPOS,
    seleccionarVozPdv,
} from './pdvSpeechUtils';

const envelopeLlamado = (extra = {}) => ({
    event_id: 'evt-tts-1',
    tipo: 'turno.asignado',
    dominio: 'turnos',
    audiencia: 'sucursal',
    datos: {
        folio: 'V-0100',
        snapshot_nombre_llamado: 'María López',
        atencion: { primer_nombre: 'Ana' },
    },
    ...extra,
});

describe('pdvSpeechUtils', () => {
    it('solo anuncia tipos aprobados con datos suficientes', () => {
        expect(PDV_TTS_TIPOS.has('turno.asignado')).toBe(true);
        expect(PDV_TTS_TIPOS.has('turno.alta')).toBe(false);
        expect(debeAnunciarTtsPdv(envelopeLlamado())).toBe(true);
        expect(debeAnunciarTtsPdv(envelopeLlamado({ tipo: 'turno.alta' }))).toBe(false);
        expect(debeAnunciarTtsPdv(envelopeLlamado({
            datos: { folio: 'V-1' },
        }))).toBe(false);
    });

    it('forma el guion neutro sin categorías VIP/Diamante', () => {
        const mensaje = mensajeTtsPdv(envelopeLlamado({
            datos: {
                folio: 'V-0200',
                snapshot_nombre_llamado: 'Rosa Hernández',
                prioridad_vip: true,
                prioridad_diamante: true,
                atencion: { primer_nombre: 'Luis' },
            },
        }));

        expect(mensaje).toBe('Turno V-0200. Rosa Hernández. Favor de pasar con Luis.');
        expect(mensaje).not.toMatch(/\bvip\b|\bdiamante\b/i);
    });

    it('usa atencion_primer_nombre del payload público', () => {
        const mensaje = mensajeTtsPdv({
            tipo: 'turno.asignado',
            datos: {
                folio: 'V-0300',
                snapshot_nombre_llamado: 'Visitante Uno',
                atencion_primer_nombre: 'Carmen',
            },
        });

        expect(mensaje).toContain('Favor de pasar con Carmen.');
    });

    it('omite apellido de quien atiende y usa fallback sin primer nombre', () => {
        const sinAtencion = mensajeTtsPdv({
            tipo: 'turno.reatencion',
            datos: {
                folio: 'V-0400',
                snapshot_nombre_llamado: 'Pedro Ruiz',
            },
        });
        expect(sinAtencion).toBe('Turno V-0400. Pedro Ruiz. Favor de atender.');
    });

    it('selecciona voz es-MX con fallback a español', () => {
        const voces = [
            { name: 'English US', lang: 'en-US' },
            { name: 'Google español', lang: 'es-ES' },
        ];
        expect(seleccionarVozPdv(voces)?.lang).toBe('es-ES');

        const conMexico = [
            { name: 'Otra', lang: 'en-US' },
            { name: 'MX', lang: 'es-MX' },
        ];
        expect(seleccionarVozPdv(conMexico)?.lang).toBe('es-MX');
    });
});
