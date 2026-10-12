// @vitest-environment happy-dom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ZonaAdjuntoVoucher from './ZonaAdjuntoVoucher';
import { compressImageToWebp } from '@/utils/compressImage';
vi.mock('@/utils/compressImage', () => ({ compressImageToWebp: vi.fn(), validateImageSource: vi.fn(() => null) }));
let container, root;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:preview'); vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {}); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.restoreAllMocks(); });
const cargar = (files) => act(() => { const input = container.querySelector('input[type="file"]'); Object.defineProperty(input, 'files', { configurable: true, value: files }); input.dispatchEvent(new Event('change', { bubbles: true })); });

it('informa archivos rechazados y conserva los PDFs válidos', async () => {
    const change = vi.fn(); await act(() => root.render(<ZonaAdjuntoVoucher vouchers={[]} onChange={change} />));
    const pdf = new File(['pdf'], 'pago.pdf', { type: 'application/pdf' });
    await cargar([new File(['txt'], 'notas.txt', { type: 'text/plain' }), pdf]);
    expect(change).toHaveBeenLastCalledWith([pdf]); expect(container.querySelector('[role="alert"]').textContent).toContain('notas.txt');
});

it('bloquea acciones mientras prepara una imagen y comunica cuando está lista', async () => {
    let resolver; compressImageToWebp.mockImplementation(() => new Promise((resolve) => { resolver = resolve; }));
    const busy = vi.fn(); const change = vi.fn(); await act(() => root.render(<ZonaAdjuntoVoucher vouchers={[]} onChange={change} onBusyChange={busy} />));
    await cargar([new File(['imagen'], 'pago.jpg', { type: 'image/jpeg' })]);
    expect(busy).toHaveBeenCalledWith(true); expect(container.querySelector('input').disabled).toBe(true);
    const webp = new File(['webp'], 'pago.webp', { type: 'image/webp' }); await act(async () => { resolver(webp); });
    expect(change).toHaveBeenCalledWith([webp]); expect(busy).toHaveBeenLastCalledWith(false);
});

it('revoca las vistas previas cuando se quita un archivo', async () => {
    const file = new File(['pdf'], 'pago.pdf', { type: 'application/pdf' });
    function Harness() { const [files, setFiles] = useState([file]); return <ZonaAdjuntoVoucher vouchers={files} onChange={setFiles} />; }
    await act(() => root.render(<Harness />)); expect(container.textContent).toContain('Revisar');
    await act(() => container.querySelector('[aria-label="Quitar pago.pdf"]').click());
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview'); expect(container.textContent).not.toContain('pago.pdf');
});
