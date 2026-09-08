// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { crearAdaptadorVozSimulado } from './pdvSpeechAdapter';
import { crearColaAnunciosTts } from './pdvSpeechQueue';

const envelope = (id, extra = {}) => ({
    event_id: id,
    tipo: 'turno.asignado',
    dominio: 'turnos',
    datos: {
        folio: `V-${id}`,
        snapshot_nombre_llamado: 'Cliente Prueba',
        atencion: { primer_nombre: 'Ana' },
    },
    ...extra,
});

describe('pdvSpeechQueue', () => {
    it('procesa múltiples eventos en serie y omite duplicados', () => {
        const adaptador = crearAdaptadorVozSimulado();
        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => false,
            audioDesbloqueado: () => true,
        });

        expect(cola.encolar(envelope('1'))).toBe(true);
        expect(cola.encolar(envelope('2'))).toBe(true);
        expect(cola.encolar(envelope('1'))).toBe(false);

        expect(adaptador.historial).toHaveLength(2);
        expect(cola.anunciados()).toBe(2);
        cola.destruir();
    });

    it('no reproduce cuando está silenciado y limpia pendientes', () => {
        const adaptador = crearAdaptadorVozSimulado();
        let silenciado = true;
        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => silenciado,
            audioDesbloqueado: () => true,
        });

        expect(cola.encolar(envelope('a'))).toBe(true);
        expect(adaptador.historial).toHaveLength(0);
        expect(cola.anunciados()).toBe(1);

        silenciado = false;
        cola.alternarSilencio(false);
        expect(cola.encolar(envelope('b'))).toBe(true);
        expect(adaptador.historial.length).toBeGreaterThanOrEqual(1);
        cola.destruir();
    });

    it('continúa la cola tras fallo de audio', () => {
        const adaptador = crearAdaptadorVozSimulado();
        const fallos = [];
        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => false,
            audioDesbloqueado: () => true,
            onFallo: (id) => fallos.push(id),
        });

        const originalHablar = adaptador.hablar.bind(adaptador);
        let llamadas = 0;
        adaptador.hablar = (texto, opts) => {
            llamadas += 1;
            if (llamadas === 1) {
                opts.onError?.();
                return;
            }
            originalHablar(texto, opts);
        };

        cola.encolar(envelope('err'));
        cola.encolar(envelope('ok'));

        expect(fallos).toEqual(['err']);
        expect(adaptador.historial).toHaveLength(1);
        cola.destruir();
    });

    it('espera desbloqueo de audio antes de hablar', () => {
        const adaptador = crearAdaptadorVozSimulado();
        let desbloqueado = false;
        const estados = [];
        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => false,
            audioDesbloqueado: () => desbloqueado,
            onEstado: (estado) => estados.push(estado),
        });

        cola.encolar(envelope('bloq'));
        expect(adaptador.historial).toHaveLength(0);
        expect(cola.pendientes()).toBe(1);
        expect(estados).toContain('bloqueado');

        desbloqueado = true;
        cola.marcarAudioDesbloqueado();
        expect(adaptador.historial).toHaveLength(1);
        cola.destruir();
    });

    it('cancela al destruir', () => {
        const adaptador = crearAdaptadorVozSimulado();
        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => false,
            audioDesbloqueado: () => true,
        });

        cola.encolar(envelope('x'));
        cola.destruir();
        expect(cola.encolar(envelope('y'))).toBe(false);
    });

    it('encola críticas antes que normales', () => {
        const adaptador = crearAdaptadorVozSimulado();
        let desbloqueado = false;
        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => false,
            audioDesbloqueado: () => desbloqueado,
            resolverTexto: (envelope) => `Mensaje ${envelope.datos.folio}`,
        });

        cola.encolar(envelope('normal', { tipo: 'turno.alta' }));
        cola.encolar(envelope('critica', { tipo: 'atencion.espera_proximo_vencer' }));

        desbloqueado = true;
        cola.marcarAudioDesbloqueado();

        expect(adaptador.historial[0]).toContain('V-critica');
        cola.destruir();
    });
});
