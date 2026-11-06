// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import MensajeInput from './MensajeInput';
vi.mock('@/hooks/useIconoHover', () => ({ default: () => ({ nodo: null, triggerProps: {} }) }));
vi.mock('./GrabadorAudio', () => ({ default: () => null }));
vi.mock('./BandejaStickers', () => ({ default: () => null }));
vi.mock('./BarraRespuestaMensaje', () => ({ default: () => null }));
let root, container;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
async function escribir(texto) {
    await act(() => { const el = container.querySelector('textarea'); Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(el, texto); el.dispatchEvent(new Event('input', { bubbles: true })); });
}
async function enviar() { await act(() => container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))); }
it('conserva el mensaje si falla el envío y lo limpia al reintentar con éxito', async () => {
    const enviarTexto = vi.fn().mockRejectedValueOnce(new Error('Sin red')).mockResolvedValueOnce({ id: 1 });
    await act(() => root.render(<MensajeInput onEnviarTexto={enviarTexto} />));
    await escribir('Hola equipo'); await enviar();
    expect(container.querySelector('textarea').value).toBe('Hola equipo');
    expect(container.querySelector('[role=alert]').textContent).toContain('No se pudo enviar');
    await enviar();
    expect(enviarTexto).toHaveBeenCalledTimes(2);
    expect(container.querySelector('textarea').value).toBe('');
    expect(container.querySelector('[role=alert]')).toBeNull();
});
it('un envío pendiente no borra el texto nuevo ni permite duplicar el envío', async () => {
    let terminar; const enviarTexto = vi.fn(() => new Promise(r => { terminar = r; }));
    await act(() => root.render(<MensajeInput onEnviarTexto={enviarTexto} />));
    await escribir('Primer mensaje'); await enviar(); await enviar();
    expect(enviarTexto).toHaveBeenCalledTimes(1);
    await escribir('Segundo mensaje'); await act(() => terminar({ id: 1 }));
    expect(container.querySelector('textarea').value).toBe('Segundo mensaje');
});
it('cierra los adjuntos con Escape y no envía Enter durante composición de texto', async () => {
    const enviarTexto = vi.fn(); await act(() => root.render(<MensajeInput onEnviarTexto={enviarTexto} />));
    await act(() => container.querySelector('[aria-label="Adjuntar archivo"]').click());
    expect(container.querySelector('.gelia-menu-adjuntos')).not.toBeNull();
    await act(() => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
    expect(container.querySelector('.gelia-menu-adjuntos')).toBeNull();
    await escribir('Componiendo');
    await act(() => container.querySelector('textarea').dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', isComposing: true, bubbles: true })));
    expect(enviarTexto).not.toHaveBeenCalled();
});
