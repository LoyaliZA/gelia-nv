import { describe, expect, it } from 'vitest';
import {
    aplicarCursorLectura,
    conversacionConMensajeNuevo,
    fusionarMensajesHilo,
} from './mensajeriaMensaje';

describe('cursor de lectura', () => {
    it('pone en cero el no leído de quien lee, en cualquier pestaña', () => {
        const resultado = aplicarCursorLectura({
            conversaciones: [{ id: 4, unread_count: 3 }, { id: 5, unread_count: 1 }],
            mensajes: [{ id: 8, estado_lectura: 'enviado' }],
            conversacionActivaId: 4,
            detalle: { alcance: 'lector', conversacion_id: 4 },
        });

        expect(resultado.conversaciones[0].unread_count).toBe(0);
        expect(resultado.conversaciones[1].unread_count).toBe(1);
        expect(resultado.mensajes[0].estado_lectura).toBe('enviado');
    });

    it('marca leído el hilo abierto de quien envió', () => {
        const resultado = aplicarCursorLectura({
            conversaciones: [{ id: 4, unread_count: 0 }],
            mensajes: [
                { id: 8, estado_lectura: 'enviado' },
                { id: 9, estado_lectura: 'entregado' },
            ],
            conversacionActivaId: 4,
            detalle: {
                alcance: 'autor',
                conversacion_id: 4,
                mensajes: [
                    { id: 8, estado_lectura: 'leido' },
                    { id: 9, estado_lectura: 'entregado' },
                ],
            },
        });

        expect(resultado.mensajes.map((m) => m.estado_lectura)).toEqual(['leido', 'entregado']);
    });

    it('no baja un acuse si llega un estado anterior', () => {
        const resultado = aplicarCursorLectura({
            conversaciones: [],
            mensajes: [{ id: 8, estado_lectura: 'leido' }],
            conversacionActivaId: 4,
            detalle: {
                alcance: 'autor',
                conversacion_id: 4,
                mensajes: [{ id: 8, estado_lectura: 'entregado' }],
            },
        });

        expect(resultado.mensajes[0].estado_lectura).toBe('leido');
    });
});

describe('mensaje nuevo en la bandeja', () => {
    it('no duplica el contador si la vista previa ya trae ese mensaje', () => {
        const actualizada = conversacionConMensajeNuevo({
            id: 1,
            unread_count: 2,
            ultimo_mensaje_at: '2026-11-02T18:00:00Z',
        }, {
            contenido: 'Hola',
            tipo: 'texto',
            created_at: '2026-11-02T18:00:00Z',
        }, false);

        expect(actualizada.unread_count).toBe(2);
    });

    it('suma uno si el mensaje es más nuevo y el chat no está abierto', () => {
        const actualizada = conversacionConMensajeNuevo({
            id: 1,
            unread_count: 2,
            ultimo_mensaje_at: '2026-11-02T17:00:00Z',
        }, {
            contenido: 'Hola',
            tipo: 'texto',
            created_at: '2026-11-02T18:00:00Z',
        }, false);

        expect(actualizada.unread_count).toBe(3);
        expect(actualizada.ultimo_mensaje_preview).toBe('Hola');
    });
});

describe('fusionarMensajesHilo', () => {
    it('actualiza el estado del hilo abierto y conserva el historial', () => {
        const mezcla = fusionarMensajesHilo(
            [
                { id: 1, estado_lectura: 'enviado' },
                { id: 2, estado_lectura: 'enviado' },
            ],
            [
                { id: 2, estado_lectura: 'leido' },
                { id: 3, estado_lectura: 'enviado' },
            ]
        );

        expect(mezcla).toEqual([
            { id: 1, estado_lectura: 'enviado' },
            { id: 2, estado_lectura: 'leido' },
            { id: 3, estado_lectura: 'enviado' },
        ]);
    });
});
