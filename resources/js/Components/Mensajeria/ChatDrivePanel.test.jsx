// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import ChatDrivePanel from './ChatDrivePanel';
import MensajeriaService from '@/Services/MensajeriaService';
vi.mock('@/Services/MensajeriaService', () => ({ default: { listarMedios: vi.fn() } }));
vi.mock('@/hooks/useIconoHover', () => ({ default: () => ({ nodo: null, triggerProps: {} }) }));
vi.mock('./VisorDocumentoMensaje', () => ({ default: () => null }));
vi.mock('./VisorImagenMensaje', () => ({ default: () => null }));
let root, container;
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('una respuesta antigua no sustituye los archivos de la categoría seleccionada', async () => {
    let terminarAnterior; MensajeriaService.listarMedios.mockImplementationOnce(() => new Promise(r => { terminarAnterior = r; })).mockResolvedValueOnce({ items: [], remitentes: [] });
    await act(() => root.render(<ChatDrivePanel isOpen conversacionId={1} />));
    await act(() => [...container.querySelectorAll('[role=tab]')].find(b => b.textContent === 'Enlaces').click());
    await act(() => terminarAnterior({ items: [{ id: 77, contenido: 'Documento anterior' }], remitentes: [] }));
    expect(container.textContent).toContain('Sin enlaces por aquí');
    expect(container.textContent).not.toContain('Documento anterior');
});
it('permite volver a cargar después de un error', async () => {
    MensajeriaService.listarMedios.mockRejectedValueOnce(new Error('Sin red')).mockResolvedValueOnce({ items: [], remitentes: [] });
    await act(() => root.render(<ChatDrivePanel isOpen conversacionId={1} />));
    expect(container.querySelector('[role=alert]')).not.toBeNull();
    await act(() => [...container.querySelectorAll('button')].find(b => b.textContent === 'Volver a intentar').click());
    expect(container.querySelector('[role=alert]')).toBeNull();
    expect(container.textContent).toContain('Sin documentos por aquí');
});
