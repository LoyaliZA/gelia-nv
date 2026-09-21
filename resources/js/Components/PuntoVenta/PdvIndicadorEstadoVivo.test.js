// @vitest-environment happy-dom
import { createElement } from 'react';
import { describe, expect, it } from 'vitest';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { Volume2 } from 'lucide-react';
import PdvIndicadorEstadoVivo from './PdvIndicadorEstadoVivo.jsx';

describe('PdvIndicadorEstadoVivo', () => {
    it('expone etiqueta de activación para audio bloqueado sin texto de no disponible', () => {
        const contenedor = document.createElement('div');
        document.body.appendChild(contenedor);
        const root = createRoot(contenedor);

        act(() => {
            root.render(createElement(PdvIndicadorEstadoVivo, {
                icono: Volume2,
                etiqueta: 'Audio bloqueado por el navegador. Toca para activar.',
                titulo: 'Toca para activar audio',
                tono: 'aviso',
                pulsando: true,
                clickeable: true,
                onClick: () => {},
                dataAtributo: 'audio-bloqueado',
            }));
        });

        const boton = contenedor.querySelector('[data-pdv-indicador-estado="audio-bloqueado"]');
        expect(boton).toBeTruthy();
        expect(boton.getAttribute('aria-label')).toContain('Toca para activar');
        expect(boton.getAttribute('aria-label')).not.toMatch(/no disponible/i);

        act(() => root.unmount());
        contenedor.remove();
    });
});
