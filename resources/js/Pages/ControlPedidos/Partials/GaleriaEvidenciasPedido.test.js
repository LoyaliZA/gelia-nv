// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';

vi.mock('../../Activos/Partials/useDispositivoCampo', () => ({ esDispositivoCampo: () => false }));
let root;
let container;
let onChange;
const photo = (name = 'pieza.jpg') => new File(['foto'], name, { type: 'image/jpeg' });
const pick = async (files) => {
    const input = container.querySelector('input[multiple]');
    Object.defineProperty(input, 'files', { configurable: true, value: files });
    await act(() => input.dispatchEvent(new Event('change', { bubbles: true })));
};
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    onChange = vi.fn();
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:nueva');
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.restoreAllMocks();
    vi.useRealTimers();
});
const mount = async (props = {}) => act(() => root.render(React.createElement(GaleriaEvidenciasPedido, { onChange, onVer: vi.fn(), ...props })));

describe('evidencias para respuesta y separación', () => {
    it('retains existing files while accepting valid files and explaining oversized files', async () => {
        const existente = photo('anterior.jpg');
        const pesada = new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'grande.jpg', { type: 'image/jpeg' });
        const nueva = photo();
        await mount({ archivos: [existente], previews: [{ name: existente.name, url: 'blob:anterior' }], maxMb: 5 });
        await pick([pesada, nueva]);
        expect(onChange.mock.calls[0][0]).toEqual([existente, nueva]);
        expect(container.querySelector('[role="alert"]').textContent).toContain('supera 5 MB');
        expect(URL.createObjectURL).toHaveBeenCalledTimes(1);
    });

    it('counts remote evidence toward the limit and creates previews only for available slots', async () => {
        await mount({ maxArchivos: 2, previews: [{ name: 'celular.jpg', url: 'https://example.test/photo.jpg', remoto: true }] });
        const primera = photo('uno.jpg');
        await pick([primera, photo('dos.jpg')]);
        expect(onChange.mock.calls[0][0]).toEqual([primera]);
        expect(onChange.mock.calls[0][1]).toHaveLength(2);
        expect(URL.createObjectURL).toHaveBeenCalledTimes(1);
        expect(container.querySelector('[role="alert"]').textContent).toContain('hasta 2 fotos');
    });

    it('accepts PDF evidence for pesaje and rejects it for physical separation', async () => {
        const pdf = new File(['pdf'], 'pedido.pdf', { type: 'application/pdf' });
        await mount();
        await pick([pdf]);
        expect(onChange.mock.calls[0][0]).toEqual([pdf]);
        onChange.mockClear();
        await mount({ soloImagenes: true });
        await pick([pdf]);
        expect(onChange).not.toHaveBeenCalled();
        expect(container.querySelector('[role="alert"]').textContent).toContain('JPG, PNG o WEBP');
    });

    it('removes a local file after remote evidence without removing the wrong attachment', async () => {
        vi.useFakeTimers();
        const local = photo();
        await mount({ archivos: [local], previews: [
            { name: 'remota.jpg', url: 'https://example.test/photo.jpg', remoto: true },
            { name: local.name, url: 'blob:local' },
        ] });
        await act(() => container.querySelectorAll('.gelia-pedidos-evidencia-quitar')[1].click());
        const confirmar = [...document.querySelectorAll('[role="dialog"] button')].find((button) => button.textContent.trim() === 'Quitar');
        await act(() => { confirmar.click(); vi.runAllTimers(); });
        expect(onChange.mock.calls[0][0]).toEqual([]);
        expect(onChange.mock.calls[0][1]).toEqual([{ name: 'remota.jpg', url: 'https://example.test/photo.jpg', remoto: true }]);
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:local');
    });
});
