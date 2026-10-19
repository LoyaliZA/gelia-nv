// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FichaPerfumeCard from './FichaPerfumeCard';
import { parsearFichaPerfume } from './fichaPerfume';

const respuesta = `### Perfume de ejemplo EDP
SKU: PRUEBA1
## Díselo así
Si te gustan los aromas frescos, este puede ser una buena opción para el día. Pruébalo para conocer cómo evoluciona en ti.
## Notas
- **Salida:** bergamota
- **Corazón:** Sin información confirmada
- **Fondo:** maderas
## Recomendación rápida
- **Para quién:** Quien disfruta aromas frescos.
- **Ocasiones:** Uso diario.
- **Clima/temporada:** Clima templado, como sugerencia.
## Fuentes
- [Fabricante](https://fabricante.example/perfume)
## Ten en cuenta
Confirma la versión del envase.`;

let container;
let root;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.restoreAllMocks();
});

describe('Ficha de venta', () => {
    it('separa speech, notas, sugerencias y evidencia sin completar datos faltantes', () => {
        const ficha = parsearFichaPerfume(respuesta);
        expect(ficha.titulo).toBe('Perfume de ejemplo EDP');
        expect(ficha.identificacion).toBe('SKU: PRUEBA1');
        expect(ficha.speech).toContain('Si te gustan');
        expect(ficha.notas.corazon).toBe('Sin información confirmada');
        expect(ficha.usos.clima).toContain('como sugerencia');
        expect(ficha.fuentes).toContain('https://fabricante.example/perfume');
        expect(ficha.aviso).toBe('Confirma la versión del envase.');
    });

    it('recupera el formato anterior guardado con títulos en negritas', () => {
        const anterior = '**Cloud EDP (SKU PRUEBA1)**\n\n**Para explicárselo al cliente**\n\nUn perfume de ejemplo.\n\n**Notas (según las fuentes consultadas)**\n- **Salida:** pera\n- **Fondo:** maderas\n\n**Cómo recomendarlo**\n- **Le puede gustar a quien busca:** dulzura\n\n**Fuentes**\n[Referencia](https://fuente.example)\n\n*Advertencia: confirma la versión.*';
        const ficha = parsearFichaPerfume(anterior);
        expect(ficha.notas.salida).toBe('pera');
        expect(ficha.notas.corazon).toBeUndefined();
        expect(ficha.usos.preferencia).toBe('dulzura');
        expect(ficha.aviso).toBe('confirma la versión.');
    });

    it('no convierte consultas generales o fallos de investigación en fichas', () => {
        expect(parsearFichaPerfume('No encontré fuentes suficientes.')).toBeNull();
        expect(parsearFichaPerfume('## Notas\nNo registradas.')).toBeNull();
    });

    it('mantiene la advertencia visible y las fuentes en un detalle accesible', async () => {
        await act(() => root.render(<FichaPerfumeCard ficha={parsearFichaPerfume(respuesta)} />));
        expect(container.querySelector('aside').textContent).toContain('Confirma la versión');
        expect(container.querySelector('details').open).toBe(false);
        expect(container.querySelector('details a').href).toBe('https://fabricante.example/perfume');
        expect(container.querySelectorAll('dl')).toHaveLength(2);
    });

    it('copia únicamente el speech y anuncia el resultado', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });
        const ficha = parsearFichaPerfume(respuesta);
        await act(() => root.render(<FichaPerfumeCard ficha={ficha} />));
        await act(async () => container.querySelector('button').click());
        expect(writeText).toHaveBeenCalledWith(ficha.speech);
        expect(container.querySelector('[role="status"]').textContent).toBe('Speech copiado');
        expect(writeText.mock.calls[0][0]).not.toContain('fabricante.example');
    });

    it('si el navegador rechaza copiar, conserva el texto y explica cómo recuperarlo', async () => {
        Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: vi.fn().mockRejectedValue(new Error('Sin permiso')) } });
        await act(() => root.render(<FichaPerfumeCard ficha={parsearFichaPerfume(respuesta)} />));
        await act(async () => container.querySelector('button').click());
        expect(container.querySelector('[role="alert"]').textContent).toContain('seleccionar el texto');
        expect(container.textContent).toContain('Si te gustan los aromas frescos');
    });
});
