// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import BandejaStickers from './BandejaStickers';
import MensajeriaService from '@/Services/MensajeriaService';
vi.mock('@/Services/MensajeriaService', () => ({ default: { listarStickers: vi.fn(), crearSticker: vi.fn(), eliminarSticker: vi.fn() } }));
vi.mock('@/hooks/useIconoHover', () => ({ default: () => ({ nodo: null, triggerProps: {} }) }));
vi.mock('@/utils/animarMensajeria', () => ({ animarEntrada: vi.fn() }));
vi.mock('./CreadorSticker', () => ({ default: () => null }));
let container, root;
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('permite reintentar la carga y elimina el error al recuperar los stickers', async () => {
    MensajeriaService.listarStickers.mockRejectedValueOnce(new Error('Sin red')).mockResolvedValueOnce([{ id: 1, url: '/sticker-prueba.png' }]);
    await act(() => root.render(<BandejaStickers abierto />));
    expect(container.querySelector('[role=alert]')).not.toBeNull();
    expect(container.textContent).not.toContain('Todavía no hay stickers');
    await act(() => [...container.querySelectorAll('button')].find(b => b.textContent === 'Volver a cargar').click());
    expect(container.querySelector('[role=alert]')).toBeNull();
    expect(container.querySelector('[aria-label="Enviar sticker"]')).not.toBeNull();
});
it('un envío fallido muestra un error y conserva el sticker disponible', async () => {
    MensajeriaService.listarStickers.mockResolvedValue([{ id: 1, url: '/sticker-prueba.png' }]);
    const enviar = vi.fn().mockRejectedValue(new Error('Sin red'));
    await act(() => root.render(<BandejaStickers abierto onEnviar={enviar} />));
    await act(() => container.querySelector('[aria-label="Enviar sticker"]').click());
    expect(container.querySelector('[role=alert]').textContent).toContain('No se pudo enviar');
    expect(container.querySelector('[aria-label="Enviar sticker"]')).not.toBeNull();
});
