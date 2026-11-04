// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import SeguimientoTransferenciaTienda, { esTransferenciaT02 } from './SeguimientoTransferenciaTienda';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let container, root;
const pedido = { id: 55, solicitud_preparacion: { modalidad_entrega: 'tienda', cobertura_tienda: { tipo: 'transferencia' } } };
const respuesta = { aplica: true, estado: 'en_custodia', version: 4, consulta_cerrada: true, pago_validado: true, numero_remision: 'REM-55', remision_adjunta: true, origen: 'CEDIS', sucursal: 'Sucursal destino', ubicacion: 'Anaquel A', puede_entregar: true, almacenes_destino: [], historial: {} };
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; axios.get.mockResolvedValue({ data: respuesta }); container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('distingue transferencia ordinaria de pago al recoger, crédito y mismo día', () => {
    expect(esTransferenciaT02(pedido)).toBe(true);
    for (const cobertura of ['apartado', 'credito']) expect(esTransferenciaT02({ ...pedido, solicitud_preparacion: { ...pedido.solicitud_preparacion, cobertura_tienda: { tipo: cobertura } } })).toBe(false);
    expect(esTransferenciaT02({ ...pedido, solicitud_preparacion: { ...pedido.solicitud_preparacion, atencion_tienda: 'prevista' } })).toBe(false);
});
it('conserva origen, destino y custodia, y exige foto y receptor antes de entregar', async () => {
    await act(() => root.render(<SeguimientoTransferenciaTienda pedido={pedido} />));
    expect(container.textContent).toContain('CEDIS'); expect(container.textContent).toContain('Sucursal destino'); expect(container.textContent).toContain('Anaquel A');
    await act(() => [...container.querySelectorAll('button')].find(button => button.textContent === 'Confirmar entrega al cliente').click());
    expect(axios.post).not.toHaveBeenCalled(); expect(container.textContent).toContain('Adjunte la fotografía');
});
it('el plazo vencido informa gestión sin ofrecer liberar mercancía y respeta solo lectura', async () => {
    axios.get.mockResolvedValue({ data: { ...respuesta, pendiente_gestion: true, limite_recoleccion: '2026-11-04T00:00:00-06:00' } });
    await act(() => root.render(<SeguimientoTransferenciaTienda pedido={pedido} soloLectura />));
    expect(container.textContent).toContain('Pendiente de gestión'); expect(container.textContent).toContain('no cancela la venta ni libera');
    expect(container.querySelectorAll('input,textarea,select')).toHaveLength(0);
    expect([...container.querySelectorAll('button')].some(button => button.textContent === 'Confirmar entrega al cliente')).toBe(false);
});
