// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import ModalDetalleDelegado from './ModalDetalleDelegado';

const { post, get } = vi.hoisted(() => ({ post: vi.fn(), get: vi.fn() }));
vi.mock('@inertiajs/react', () => ({ router: { post } }));
vi.mock('axios', () => ({ default: { get } }));
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
