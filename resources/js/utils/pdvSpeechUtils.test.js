// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    debeAnunciarTtsPdv,
    etiquetaAudioIndicadorPdv,
    mensajeTtsPdv,
    PDV_TTS_ESTADO,
    PDV_TTS_TIPOS,
    resolverEstadoTtsPdv,
    seleccionarVozPdv,
    seleccionarVozSalaPdv,
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

    it('forma el guion breve con prioridad y sin decir reatención ni lista', () => {
        const mensaje = mensajeTtsPdv(envelopeLlamado({
            tipo: 'turno.reatencion',
            datos: {
                folio: 'V-0200',
                snapshot_nombre_llamado: 'Rosa Hernández',
                prioridad_vip: true,
                prioridad_diamante: true,
                prioridad_discapacidad: true,
                prioridad_adulto_mayor: true,
                atencion: { primer_nombre: 'Luis' },
            },
        }));

        expect(mensaje).toBe('Luis, tienes un nuevo cliente: Rosa Hernández.');
        expect(mensaje).not.toMatch(/reatenci[oó]n|lista/i);
    });

    it('en sala pública no menciona discapacidad, tercera edad ni VIP', () => {
        const mensaje = mensajeTtsPdv({
            tipo: 'turno.asignado',
            audiencia: 'publico',
            datos: {
                folio: 'V-0201',
                snapshot_nombre_llamado: 'Rosa Hernández',
                prioridad_vip: true,
                prioridad_diamante: true,
                prioridad_discapacidad: true,
                prioridad_adulto_mayor: true,
                atencion_primer_nombre: 'Luis',
            },
        });

        expect(mensaje).toBe('Turno V-0201. Rosa Hernández. Tiene prioridad. Pase con Luis.');
        expect(mensaje).not.toMatch(/discapacidad|tercera edad|vip/i);
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

        expect(mensaje).toBe('Carmen, tienes un nuevo cliente: Visitante Uno.');
    });

    it('anuncia prórroga una vez y usa fallback sin primer nombre', () => {
        const sinAtencion = mensajeTtsPdv({
            tipo: 'turno.reatencion',
            datos: {
                folio: 'V-0400',
                snapshot_nombre_llamado: 'Pedro Ruiz',
            },
        });
        expect(sinAtencion).toBe('Tienes un nuevo cliente: Pedro Ruiz.');

        expect(mensajeTtsPdv({
            tipo: 'atencion.prorroga',
            datos: { folio: 'V-0401' },
        })).toBe('Prórroga iniciada. Turno V-0401.');
    });

    it('resuelve estado TTS inicial como bloqueado sin gesto previo', () => {
        expect(resolverEstadoTtsPdv({
            soportado: true,
            silenciado: false,
            audioDesbloqueado: false,
        })).toBe(PDV_TTS_ESTADO.bloqueado);
    });

    it('no etiqueta bloqueado como audio no disponible', () => {
        const bloqueado = etiquetaAudioIndicadorPdv(PDV_TTS_ESTADO.bloqueado);
        const noSoportado = etiquetaAudioIndicadorPdv(PDV_TTS_ESTADO.no_soportado);

        expect(bloqueado.titulo).toBe('Toca para activar audio');
        expect(bloqueado.titulo).not.toMatch(/no disponible/i);
        expect(noSoportado.titulo).toBe('Audio no disponible');
    });

    it('elige voz femenina latina y descarta robóticas o masculinas', () => {
        const soloRobotica = [
            { name: 'English US', lang: 'en-US' },
            { name: 'eSpeak Spanish', lang: 'es-ES' },
            { name: 'Google español', lang: 'es-ES' },
        ];
        expect(seleccionarVozPdv(soloRobotica)).toBeNull();

        const conMexico = [
            { name: 'Jorge', lang: 'es-MX' },
            { name: 'Microsoft Dalia Online (Natural) - Spanish (Mexico)', lang: 'es-MX' },
        ];
        expect(seleccionarVozPdv(conMexico)?.name).toContain('Dalia');

        const latam = [
            { name: 'Festival', lang: 'es-MX' },
            { name: 'Paulina', lang: 'es-US' },
        ];
        expect(seleccionarVozPdv(latam)?.name).toBe('Paulina');
    });

    it('en sala usa español disponible cuando no hay voz latina', () => {
        const soloEspania = [
            { name: 'Google español', lang: 'es-ES' },
        ];

        expect(seleccionarVozPdv(soloEspania)).toBeNull();
        expect(seleccionarVozSalaPdv(soloEspania)?.name).toBe('Google español');
    });

    it('marca voz no disponible cuando no hay voz latina', () => {
        const etiqueta = etiquetaAudioIndicadorPdv(PDV_TTS_ESTADO.sin_voz);
        expect(etiqueta.titulo).toBe('Voz no disponible');
    });
});
