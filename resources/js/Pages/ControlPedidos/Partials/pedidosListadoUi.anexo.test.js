import { describe, expect, it, vi } from 'vitest';
import { accionesPedidoLista } from './pedidosListadoUi';

const principal = {
    id: 5,
    puede_mutar: true,
    numero_remision: 'REM-1',
    estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' },
    solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida' },
};
const acciones = (pedido, { permiso = true, onAnexoExtraordinario = vi.fn() } = {}) => accionesPedidoLista({
    pedido,
    can: (nombre) => permiso && ['control_pedidos.crear', 'control_pedidos.ver_detalle'].includes(nombre),
    onVer: vi.fn(),
    onAnexoExtraordinario,
});
const anexo = (lista) => lista.find((accion) => accion.key === 'anexo-extraordinario');

describe('Anexo extraordinario en el menú del pedido', () => {
    it('se ofrece en un nacional con remisión o pago, antes y después de salir, dentro de Operaciones', () => {
        const alAbrir = vi.fn();
        const accion = anexo(acciones(principal, { onAnexoExtraordinario: alAbrir }));
        expect(accion.label).toBe('Anexo extraordinario');
        expect(accion.grupo).toMatch(/Operaciones/i);
        accion.onClick();
        expect(alAbrir).toHaveBeenCalledWith(principal);

        expect(anexo(acciones({ ...principal, numero_remision: null, pago_validado_at: '2026-11-23', estatus: { fase_ciclo: 'ENVIADO' } }))).toBeDefined();
    });

    it('no se ofrece sin pago ni remisión, cancelado, sin permiso o fuera del nacional actual', () => {
        expect(anexo(acciones({ ...principal, numero_remision: null }))).toBeUndefined();
        expect(anexo(acciones({ ...principal, cancelado_at: '2026-11-23' }))).toBeUndefined();
        expect(anexo(acciones({ ...principal, estatus: { fase_ciclo: 'CANCELADO' } }))).toBeUndefined();
        expect(anexo(acciones(principal, { permiso: false }))).toBeUndefined();
        expect(anexo(acciones({ ...principal, solicitud_preparacion: { modalidad_entrega: 'nacional' } }))).toBeUndefined();
        expect(anexo(acciones({ ...principal, solicitud_preparacion: { modalidad_entrega: 'municipal', captura_nacional: 'compartida' } }))).toBeUndefined();
    });

    it('un anexo no genera otro anexo y la acción exige el manejador', () => {
        const hijo = { ...principal, solicitud_preparacion: { ...principal.solicitud_preparacion, complemento_nacional: { pedido_origen_id: 1 } } };
        expect(anexo(acciones(hijo))).toBeUndefined();
        expect(anexo(accionesPedidoLista({ pedido: principal, can: () => true, onVer: vi.fn() }))).toBeUndefined();
    });
});
