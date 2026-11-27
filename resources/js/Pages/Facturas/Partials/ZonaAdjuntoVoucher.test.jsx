// @vitest-environment happy-dom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ZonaAdjuntoVoucher from './ZonaAdjuntoVoucher';
import { compressImageToWebp } from '@/utils/compressImage';
import ZonaAdjuntoPdf from './ZonaAdjuntoPdf';
vi.mock('@/utils/compressImage', () => ({ compressImageToWebp: vi.fn(), validateImageSource: vi.fn(() => null) }));
let container, root;
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:preview'); vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {}); });
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

const archivoDeTamano = (name, type, size) => {
    const file = new File(['contenido'], name, { type });
    Object.defineProperty(file, 'size', { value: size });
    return file;
};

it.each(['application/zip', 'application/x-zip-compressed', ''])('acepta ZIP de 10 MB por selección y sin visor (%s)', async (type) => {
    const file = archivoDeTamano('pago.ZIP', type, 10 * 1024 * 1024);
    function Harness() { const [files, setFiles] = useState([]); return <ZonaAdjuntoVoucher permitirZip vouchers={files} onChange={setFiles} />; }
    await act(() => root.render(<Harness />));
    expect(container.querySelector('input').accept).toContain('.zip');
    await cargar([file]);
    expect(container.textContent).toContain('pago.ZIP');
    expect(container.textContent).not.toContain('Revisar');
    expect(compressImageToWebp).not.toHaveBeenCalled();
    expect(container.textContent).toContain('ZIP: máximo 10 MB');
    await act(() => container.querySelector('[aria-label="Quitar pago.ZIP"]').click());
    expect(container.textContent).not.toContain('pago.ZIP');
});

it('rechaza ZIP mayor de 10 MB por arrastre', async () => {
    const change = vi.fn();
    await act(() => root.render(<ZonaAdjuntoVoucher permitirZip vouchers={[]} onChange={change} />));
    const event = new Event('drop', { bubbles: true });
    Object.defineProperty(event, 'dataTransfer', { value: { files: [archivoDeTamano('pago.zip', 'application/zip', 10 * 1024 * 1024 + 1)] } });
    await act(() => container.querySelector('[aria-label="Zona para adjuntar comprobantes"]').dispatchEvent(event));
    expect(change).toHaveBeenLastCalledWith([]);
    expect(container.querySelector('[role="alert"]').textContent).toContain('10 MB');
});

it.each([['jpg', 'image/jpeg'], ['jpeg', 'image/jpeg'], ['png', 'image/png'], ['webp', 'image/webp'], ['pdf', 'application/pdf']])('conserva 5 MB para %s', async (extension, type) => {
    const change = vi.fn();
    compressImageToWebp.mockImplementation(async (file) => file);
    await act(() => root.render(<ZonaAdjuntoVoucher permitirZip vouchers={[]} onChange={change} />));
    const file = archivoDeTamano(`pago.${extension}`, type, 5 * 1024 * 1024);
    await cargar([file]);
    expect(change).toHaveBeenLastCalledWith([file]);
    await cargar([archivoDeTamano(`grande.${extension}`, type, 5 * 1024 * 1024 + 1)]);
    expect(change).toHaveBeenLastCalledWith([]);
    expect(container.querySelector('[role="alert"]').textContent).toContain('5 MB');
});

it('acepta ZIP por arrastre y mantiene cinco comprobantes combinados', async () => {
    const change = vi.fn();
    await act(() => root.render(<ZonaAdjuntoVoucher permitirZip vouchers={[]} existentes={[{ id: 1, label: 'Guardado' }]} onChange={change} />));
    const files = ['uno.zip', 'dos.pdf', 'tres.zip', 'cuatro.pdf', 'sexto.zip'].map((name) => new File(['contenido'], name));
    const event = new Event('drop', { bubbles: true });
    Object.defineProperty(event, 'dataTransfer', { value: { files } });
    await act(() => container.querySelector('[aria-label="Zona para adjuntar comprobantes"]').dispatchEvent(event));
    expect(change).toHaveBeenLastCalledWith(files.slice(0, 4));
    expect(container.querySelector('[role="alert"]').textContent).toContain('hasta 5 comprobantes');
});

it('mantiene las restricciones anteriores cuando ZIP está deshabilitado', async () => {
    const change = vi.fn();
    await act(() => root.render(<ZonaAdjuntoVoucher vouchers={[]} onChange={change} />));
    expect(container.querySelector('input').accept).not.toContain('.zip');
    await cargar([new File(['zip'], 'pago.zip', { type: 'application/zip' }), archivoDeTamano('pago.pdf', 'application/pdf', 5 * 1024 * 1024 + 1)]);
    expect(change).toHaveBeenLastCalledWith([]);
    expect(container.querySelector('[role="alert"]').textContent).toContain('5 MB');
});

it('conserva el límite de 5 MB y solo PDF para facturas emitidas', async () => {
    const change = vi.fn();
    await act(() => root.render(<ZonaAdjuntoPdf archivos={[]} onChange={change} />));
    await cargar([archivoDeTamano('emitida.pdf', 'application/pdf', 5 * 1024 * 1024 + 1), new File(['zip'], 'pago.zip', { type: 'application/zip' })]);
    expect(change).toHaveBeenLastCalledWith([]);
    expect(container.querySelector('[role="alert"]').textContent).toContain('5 MB');
    expect(container.querySelector('[role="alert"]').textContent).toContain('Solo se admiten archivos PDF');
});
