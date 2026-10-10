// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SolicitudDialog from './SolicitudDialog';
import { useAdjuntoRespuesta } from './AdjuntoRespuesta';
import FiltrosFacturas from '@/Pages/Facturas/Partials/FiltrosFacturas';
import FiltrosOperativas from '@/Pages/CancelacionesCotizaciones/Partials/FiltrosOperativas';
import { compressImageToWebp } from '@/utils/compressImage';

vi.mock('@/utils/compressImage', () => ({ compressImageToWebp: vi.fn(), validateImageSource: () => null }));
let root, container, originalRects;
const key = (key) => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    originalRects = HTMLElement.prototype.getClientRects;
    HTMLElement.prototype.getClientRects = () => [{ width: 100, height: 44 }];
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:preview');
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
    document.body.style.overflow = 'auto';
});
afterEach(async () => {
    await act(() => root.unmount()); container.remove(); document.body.innerHTML = '';
    HTMLElement.prototype.getClientRects = originalRects; vi.restoreAllMocks(); vi.clearAllMocks();
});

const dialog = (props) => React.createElement(SolicitudDialog, { title: 'Respuesta', ...props }, React.createElement('div', { className: 'gelia-modal-shell' }, React.createElement('button', { 'data-dialog-close': true }, 'Cerrar'), props.children));
describe('request workflow safety', () => {
    it('asks before discarding a dirty response and allows returning to editing', async () => {
        const close = vi.fn();
        await act(() => root.render(dialog({ onClose: close, dirty: true })));
        await act(() => key('Escape'));
        expect(close).not.toHaveBeenCalled();
        expect(document.body.textContent).toContain('¿Descartar los cambios?');
        await act(() => [...document.querySelectorAll('button')].find((b) => b.textContent === 'Seguir editando').click());
        expect(document.body.textContent).toContain('Cerrar');
        await act(() => document.querySelector('button[data-dialog-close]').click());
        await act(() => [...document.querySelectorAll('button')].find((b) => b.textContent === 'Descartar cambios').click());
        expect(close).toHaveBeenCalledTimes(1);
    });
    it('blocks both keyboard and close controls while uploading', async () => {
        const close = vi.fn();
        await act(() => root.render(dialog({ onClose: close, busy: true })));
        await act(() => key('Escape'));
        await act(() => document.querySelector('button[data-dialog-close]').click());
        expect(close).not.toHaveBeenCalled();
        await act(() => root.render(dialog({ onClose: close })));
        await act(() => key('Escape'));
        expect(close).toHaveBeenCalledTimes(1);
    });
    it('closes only a nested preview and restores focus and scroll to the response', async () => {
        const closeParent = vi.fn(), closePreview = vi.fn();
        const tree = (preview) => dialog({ key: 'response', onClose: closeParent, dirty: true, children: preview && dialog({ key: 'preview', onClose: closePreview }) });
        const trigger = document.createElement('button'); document.body.prepend(trigger); trigger.focus();
        await act(() => root.render(tree(false)));
        const responseButton = document.querySelector('button[data-dialog-close]'); responseButton.focus();
        await act(() => root.render(tree(true)));
        await act(() => key('Escape'));
        expect(closePreview).toHaveBeenCalledTimes(1); expect(closeParent).not.toHaveBeenCalled();
        await act(() => [...document.querySelectorAll('button[data-dialog-close]')].at(-1).click());
        expect(closePreview).toHaveBeenCalledTimes(2); expect(document.body.textContent).not.toContain('¿Descartar los cambios?');
        await act(() => root.render(tree(false)));
        expect(document.activeElement).toBe(responseButton); expect(document.body.style.overflow).toBe('hidden');
        await act(() => root.render(null));
        expect(document.activeElement).toBe(trigger); expect(document.body.style.overflow).toBe('auto');
    });
});

let attachment;
function AttachmentHarness({ onChange }) { attachment = useAdjuntoRespuesta(null, onChange); return null; }
describe('response attachment validation', () => {
    it('rejects oversized PDFs and unsupported files with visible guidance', async () => {
        const change = vi.fn();
        await act(() => root.render(React.createElement(AttachmentHarness, { onChange: change })));
        await act(() => attachment.adjuntar(new File([new Uint8Array(6 * 1024 * 1024)], 'grande.pdf', { type: 'application/pdf' })));
        expect(attachment.error).toContain('excede 5 MB'); expect(change).not.toHaveBeenCalled();
        await act(() => attachment.adjuntar(new File(['text'], 'doc.txt', { type: 'text/plain' })));
        expect(attachment.error).toContain('JPG'); expect(change).not.toHaveBeenCalled();
        await act(() => attachment.adjuntar(new File(['pdf'], 'evidencia.pdf', { type: 'application/pdf' })));
        expect(change).toHaveBeenCalledTimes(1); expect(attachment.error).toBe('');
    });
    it('keeps the most recent selection when image preparation finishes out of order', async () => {
        const change = vi.fn(); let resolveFirst, resolveSecond;
        compressImageToWebp.mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve; })).mockImplementationOnce(() => new Promise((resolve) => { resolveSecond = resolve; }));
        await act(() => root.render(React.createElement(AttachmentHarness, { onChange: change })));
        let first, second;
        await act(() => { first = attachment.adjuntar(new File(['first'], 'primera.png', { type: 'image/png' })); });
        await act(() => { second = attachment.adjuntar(new File(['second'], 'segunda.png', { type: 'image/png' })); });
        const latest = new File(['latest'], 'segunda.webp', { type: 'image/webp' });
        await act(async () => { resolveSecond(latest); await second; });
        await act(async () => { resolveFirst(new File(['old'], 'primera.webp', { type: 'image/webp' })); await first; });
        expect(change).toHaveBeenCalledTimes(1); expect(change).toHaveBeenCalledWith(latest); expect(attachment.busy).toBe(false);
    });
});


describe('desktop request filters', () => {
    it('preserves the chosen salesperson while submitting the invoice search', async () => {
        const apply = vi.fn();
        await act(() => root.render(React.createElement(FiltrosFacturas, { filtros: { q: 'FAC-42', vendedor_id: '7' }, vendedores: [{ id: 7, name: 'Ventas' }], tabActiva: 'TODAS', onAplicarFiltros: apply, onTabChange: vi.fn() })));
        await act(() => document.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
        expect(apply).toHaveBeenLastCalledWith({ q: 'FAC-42', vendedor_id: '7', page: 1 });
        await act(() => [...document.querySelectorAll('button')].find((button) => button.textContent.includes('Limpiar filtros')).click());
        expect(apply).toHaveBeenLastCalledWith({ q: undefined, vendedor_id: undefined, page: 1 });
        expect(document.querySelector('#facturas-busqueda').value).toBe('');
    });
    it('keeps state and operation filters independent when opening advanced controls', async () => {
        const state = vi.fn(), type = vi.fn(), apply = vi.fn();
        await act(() => root.render(React.createElement(FiltrosOperativas, { tabActiva: 'TODAS', tipoOperativo: '', busqueda: 'R-42', onCambiarTab: state, onCambiarTipo: type, onAplicarFiltros: apply })));
        await act(() => [...document.querySelectorAll('button')].find((button) => button.textContent === 'Pendientes').click());
        expect(state).toHaveBeenLastCalledWith('PENDIENTES');
        await act(() => [...document.querySelectorAll('button')].find((button) => button.textContent === 'Cotización').click());
        expect(type).toHaveBeenLastCalledWith('COTIZACION');
        await act(() => document.querySelector('button[aria-controls]').click());
        expect(document.querySelector('#operativas-vendedor')).not.toBeNull();
        expect(document.querySelector('#operativas-busqueda').value).toBe('R-42');
        expect(apply).not.toHaveBeenCalled();
    });
});
