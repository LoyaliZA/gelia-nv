// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import ModalDetalleDelegado from './ModalDetalleDelegado';

const { post, inertiaDelete, get, enviarPdf, retirarPdf } = vi.hoisted(() => ({
    post: vi.fn(), inertiaDelete: vi.fn(), get: vi.fn(), enviarPdf: vi.fn(), retirarPdf: vi.fn(),
}));
vi.mock('@inertiajs/react', () => ({ router: { post, delete: inertiaDelete } }));
vi.mock('axios', () => ({ default: { get, post: enviarPdf, delete: retirarPdf } }));
vi.mock('../../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null, MiniaturaDocumento: () => null }));

it('mantiene columnas, un rastreo y Asignar bloqueado hasta completar las guías', async () => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('route', (name, id) => `/${name}/${id}`);
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    const cajas = [1, 2, 3].map((id) => ({ id, orden: id, estatus_recoleccion: 'pendiente' }));
    const pedido = {
        id: 42, folio: 'NAC-42', numero_rastreo: '7845210030', guias_pdf_por_caja: true,
        estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' }, cajas,
    };
    const documentos = cajas.map(({ id }) => ({
        id, pedido_bma_caja_id: id, tipo: 'guia', activo: true, relacion_tipo: 'guia_caja_avance',
        nombre_original: `Guía ${id}.pdf`,
    }));
    const montar = (actual) => act(() => root.render(<ModalDetalleDelegado abierto pedido={actual} onClose={() => {}} />));
    const asignar = () => [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Asignar');
    try {
        await montar({ ...pedido, documentos: documentos.slice(0, 2) });
        expect([...document.querySelectorAll('th')].map((th) => th.textContent)).toEqual([
            'Caja', 'Medidas (cm)', 'Peso real', 'Peso cobrado', 'Flete', 'Seguro', 'Guía PDF',
        ]);
        expect(document.body.textContent).toContain('2 de 3 guías cargadas');
        expect(document.querySelector('button[aria-label="Cargar PDF de la caja 3"]')).toBeTruthy();
        expect(asignar().disabled).toBe(true);
        expect(post).not.toHaveBeenCalled();

        await montar(null);
        await montar({ ...pedido, documentos });
        expect(document.body.textContent).toContain('3 de 3 guías cargadas');
        expect(document.body.textContent).not.toContain('Guías confirmadas');
        expect(document.querySelectorAll('input[aria-label="Número de rastreo"]')).toHaveLength(1);
        expect(asignar().disabled).toBe(false);
        await act(() => asignar().form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
        expect(post).toHaveBeenCalledExactlyOnceWith(
            '/control_pedidos.delegado.asignar_guia/42',
            { numero_rastreo: '7845210030' },
            expect.any(Object),
        );
        const asignacion = post.mock.lastCall[2];
        const refrescar = async (actual) => {
            get.mockResolvedValueOnce({ data: { pedidos: { data: [actual] } } });
            await act(async () => {
                await asignacion.onSuccess();
                asignacion.onFinish();
            });
        };

        await refrescar({ ...pedido, documentos });
        expect(document.body.textContent).toContain('3 de 3 guías cargadas');
        expect(document.body.textContent).not.toContain('Guías confirmadas');

        const confirmados = documentos.map((doc) => ({ ...doc, relacion_tipo: 'guia_caja_confirmada' }));
        const retirado = { id: 4, orden: 4, estado_operativo: 'retirada' };
        const confirmacion = {
            ...pedido,
            cajas: [...cajas, retirado],
            documentos: [
                ...confirmados,
                { ...confirmados[0], id: 5, activo: false },
                { ...confirmados[0], id: 6, pedido_bma_caja_id: 4 },
            ],
        };
        await refrescar(confirmacion);
        expect(document.querySelector('aside [role="status"]').textContent).toBe('3 guías cargadasGuías confirmadas');
        expect(asignar()).toBeUndefined();
        expect(document.querySelector('button[aria-label="Copiar: 7845210030"]')).toBeTruthy();

        const pendiente = {
            ...confirmacion,
            documentos: [
                { ...confirmados[0], activo: false }, documentos[0], ...confirmados.slice(1),
            ],
        };
        await refrescar(pendiente);
        expect(document.body.textContent).toContain('3 de 3 guías cargadas');
        expect(document.body.textContent).not.toContain('Guías confirmadas');
        expect(asignar().disabled).toBe(false);

        await refrescar({
            ...confirmacion,
            estatus: { fase_ciclo: 'ENVIADO' },
            cajas: [...cajas.map((caja) => ({ ...caja, estatus_recoleccion: 'recolectada' })), retirado],
        });
        expect(document.querySelector('aside [role="status"]').textContent).toBe('3 guías cargadasGuías confirmadas');
        expect(document.querySelector('button[aria-label="Copiar: 7845210030"]')).toBeTruthy();
    } finally {
        await act(() => root.unmount());
        host.remove();
        vi.unstubAllGlobals();
    }
});

it('carga, reemplaza y retira PDFs por caja sin visitas de Inertia ni consultas de bandeja', async () => {
    vi.clearAllMocks();
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (name, id) => `/${name}/${id}`);
    const confirmar = vi.fn();
    vi.stubGlobal('confirm', confirmar);
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    const onPdfCajaGuardado = vi.fn();
    const onClose = vi.fn();
    const guia = (id, cajaId, nombre, relacion = 'guia_caja_avance') => ({
        id, pedido_bma_caja_id: cajaId, tipo: 'guia', activo: true, relacion_tipo: relacion, nombre_original: nombre,
    });
    const pedido = {
        id: 42, folio: 'NAC-42', numero_rastreo: '7845210030', guias_pdf_por_caja: true,
        estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' },
        cajas: [{ id: 1 }, { id: 2 }],
        documentos: [guia(10, 1, 'Caja uno.pdf', 'guia_caja_confirmada'), guia(15, null, 'General.pdf', null)],
    };
    const boton = (etiqueta) => document.querySelector(`button[aria-label="${etiqueta}"]`);
    const asignar = () => [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Asignar');
    const elegirPdf = (nombre) => {
        const input = boton('Cargar PDF de la caja 2')?.previousElementSibling
            || boton('Reemplazar PDF de la caja 2').previousElementSibling;
        Object.defineProperty(input, 'files', { configurable: true, value: [new File(['pdf'], nombre, { type: 'application/pdf' })] });
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    try {
        await act(() => root.render(<ModalDetalleDelegado abierto pedido={pedido} onClose={onClose} onPdfCajaGuardado={onPdfCajaGuardado} />));
        const dialogo = document.querySelector('[role="dialog"]');
        expect(dialogo.textContent).toContain('1 de 2 guías cargadas');
        expect(asignar().disabled).toBe(true);

        let completarCarga;
        enviarPdf.mockReturnValueOnce(new Promise((resolve) => { completarCarga = resolve; }));
        await act(() => elegirPdf('Nueva.pdf'));
        const [url, datos, config] = enviarPdf.mock.lastCall;
        expect(url).toBe('/control_pedidos.delegado.guia_pdf.store/42');
        expect(datos.get('caja_id')).toBe('2');
        expect(datos.get('guia_pdf').name).toBe('Nueva.pdf');
        expect(config.headers.Accept).toBe('application/json');
        expect(boton('Cargar PDF de la caja 2').textContent).toContain('Guardando');
        expect(dialogo.textContent).toContain('1 de 2 guías cargadas');

        await act(async () => completarCarga({ data: { guia: guia(20, 2, 'Nueva.pdf'), message: 'PDF guardado' } }));
        expect(dialogo.textContent).toContain('Nueva.pdf · Avance');
        expect(dialogo.textContent).toContain('2 de 2 guías cargadas');
        expect(asignar().disabled).toBe(false);

        enviarPdf.mockResolvedValueOnce({ data: { guia: guia(21, 2, 'Reemplazo.pdf') } });
        await act(async () => elegirPdf('Reemplazo.pdf'));
        expect(dialogo.textContent).toContain('Reemplazo.pdf · Avance');
        expect(dialogo.textContent).not.toContain('Nueva.pdf');
        expect(dialogo.textContent).toContain('2 de 2 guías cargadas');

        confirmar.mockReturnValueOnce(false).mockReturnValue(true);
        await act(async () => boton('Retirar PDF de la caja 2').click());
        expect(retirarPdf).not.toHaveBeenCalled();
        retirarPdf.mockRejectedValueOnce({ response: { status: 422, data: { message: 'La caja ya no está disponible.' } } });
        await act(async () => boton('Retirar PDF de la caja 2').click());
        expect(dialogo.querySelector('[role="alert"]').textContent).toBe('La caja ya no está disponible.');
        expect(dialogo.textContent).toContain('2 de 2 guías cargadas');

        retirarPdf.mockResolvedValueOnce({ data: { guia: null } });
        await act(async () => {
            const retirar = boton('Retirar PDF de la caja 2');
            retirar.focus();
            retirar.click();
        });
        expect(retirarPdf).toHaveBeenLastCalledWith('/control_pedidos.delegado.guia_pdf.destroy/42', {
            headers: { Accept: 'application/json' }, data: { caja_id: 2 },
        });
        expect(dialogo.textContent).not.toContain('Reemplazo.pdf');
        expect(dialogo.textContent).toContain('Caja uno.pdf · Confirmada');
        expect(dialogo.textContent).toContain('1 de 2 guías cargadas');
        expect(asignar().disabled).toBe(true);
        expect(document.activeElement).toBe(boton('Cargar PDF de la caja 2'));
        expect(dialogo.querySelector('[role="alert"]')).toBeNull();
        expect(document.querySelector('[role="dialog"]')).toBe(dialogo);
        expect(onClose).not.toHaveBeenCalled();
        expect(onPdfCajaGuardado).toHaveBeenCalledTimes(3);
        expect(post).not.toHaveBeenCalled();
        expect(inertiaDelete).not.toHaveBeenCalled();
        expect(get).not.toHaveBeenCalled();

        await act(() => boton('Vincular PDF previo a la caja 2').click());
        expect(post).toHaveBeenCalledExactlyOnceWith(
            '/control_pedidos.delegado.guia_pdf.store/42',
            { caja_id: 2, guia_general_id: 15 },
            expect.objectContaining({ only: ['pedidos', 'metricas', 'filtros'] }),
        );
    } finally {
        await act(() => root.unmount());
        host.remove();
        vi.unstubAllGlobals();
    }
});
