// @vitest-environment happy-dom
import { expect, it, vi } from 'vitest';
import { accionesPedidoLista } from './pedidosListadoUi';

const can = () => true;
const propsBase = {
    can,
    onVer: vi.fn(),
    onBitacora: vi.fn(),
    onEditar: vi.fn(),
    onCancelar: vi.fn(),
    onMovimientoTienda: vi.fn(),
    puedeEditar: () => true,
    puedeCancelar: () => true,
};

it('ofrece Cambiar modalidad con flags de servidor sin exigir custodia', () => {
    const pedido = {
        puede_mutar: true,
        conversion_modalidad: { puede_iniciar: true, puede_continuar: false },
        solicitud_preparacion: { modalidad_entrega: 'tienda' },
        estatus: { fase_ciclo: 'PESAJE_PENDIENTE' },
    };
    const acciones = accionesPedidoLista({ ...propsBase, pedido });
    expect(acciones.map((a) => a.label)).toContain('Cambiar modalidad');
    expect(acciones.find((a) => a.label === 'Cambiar modalidad').grupo).toBe('Operaciones');
});

it('ofrece Continuar cambio de modalidad cuando puede_continuar', () => {
    const pedido = {
        puede_mutar: true,
        conversion_modalidad: { puede_iniciar: false, puede_continuar: true, accion_ui: 'continuar' },
        solicitud_preparacion: { modalidad_entrega: 'tienda', cambio_modalidad_tienda: { estado: 'pendiente_conexion', destino: 'municipal' } },
        estatus: { fase_ciclo: 'PESAJE_RESPONDIDO' },
    };
    expect(accionesPedidoLista({ ...propsBase, pedido }).map((a) => a.label)).toContain('Cambiar modalidad');
});

it('agrupa consulta, operaciones y administración y no muestra códigos de ficha', () => {
    const pedido = {
        puede_mutar: true,
        conversion_modalidad: { puede_iniciar: true },
        solicitud_preparacion: { modalidad_entrega: 'tienda' },
        estatus: { fase_ciclo: 'BORRADOR' },
    };
    const acciones = accionesPedidoLista({ ...propsBase, pedido, puedeEliminarRegistro: () => true, onEliminarRegistro: vi.fn() });
    const grupos = [...new Set(acciones.map((a) => a.grupo).filter(Boolean))];
    expect(grupos).toEqual(expect.arrayContaining(['Consulta y seguimiento', 'Operaciones', 'Administración']));
    expect(acciones.find((a) => a.label === 'Ver' || a.label === 'Ver detalle')?.grupo).toBe('Consulta y seguimiento');
    expect(acciones.find((a) => a.label === 'Bitácora')?.grupo).toBe('Consulta y seguimiento');
    expect(acciones.find((a) => a.label === 'Cambiar modalidad')?.grupo).toBe('Operaciones');
    expect(acciones.map((a) => a.label).join(' ')).not.toMatch(/M-02|T-03|P0/i);
});
