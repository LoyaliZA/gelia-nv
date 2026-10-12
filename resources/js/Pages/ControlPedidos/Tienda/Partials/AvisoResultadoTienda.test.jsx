// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it } from 'vitest';
import AvisoResultadoTienda from './AvisoResultadoTienda';

let root;
let container;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});
const render = (flash) => act(() => root.render(<AvisoResultadoTienda flash={flash} />));
it('confirma la toma de tarea sin bloquear la pantalla ni quitar el foco', async () => {
    const trigger = document.createElement('button');
    document.body.append(trigger);
    trigger.focus();
    const overflow = document.body.style.overflow;
    await render({ success: 'Tarea tomada correctamente.' });
    expect(container.querySelector('[role="status"]').textContent).toContain('Tarea tomada correctamente.');
    expect(document.querySelector('[role="dialog"]')).toBeNull();
    expect(document.body.style.overflow).toBe(overflow);
    expect(document.activeElement).toBe(trigger);
    trigger.remove();
});
it('muestra errores sin abrir otro modal y limpia el aviso al navegar', async () => {
    await render({ error: 'La tarea ya no está disponible.' });
    expect(container.querySelector('[role="alert"]').textContent).toContain('La tarea ya no está disponible.');
    expect(document.querySelector('[role="dialog"]')).toBeNull();
    await render({});
    expect(container.textContent).toBe('');
});
