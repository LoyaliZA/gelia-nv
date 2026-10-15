import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { expect, it, vi } from 'vitest';
import Abastecimiento from './Abastecimiento';
vi.mock('@inertiajs/react', () => ({ Head: () => null, Link: ({ children, ...props }) => <a {...props}>{children}</a>, router: { post: vi.fn(), get: vi.fn() } }));
vi.mock('../../Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
globalThis.route = (name) => name;
it('muestra la tarea receptora y revisión por pieza sin datos de envío comercial', () => {
    const html = renderToStaticMarkup(<Abastecimiento tarea={null} almacenes={[]} traspasos={{ data: [{ id: 1, folio: 'TRA-1', version: 1, pedido: 'PED-1', tarea_id: 5,
        origen: 'CEDIS', destino: 'City', salida_at: null, puede_salida: true,
        lineas: [{ id: 2, sku: 'CREMA', descripcion: 'Crema', esperadas: 2, buenas: 0, danadas: 0, pendientes: 2, incidencia_abierta: 0, faltantes_reportados: 0, rechazadas: 0 }], operaciones: [] }], links: [] }} />);
    expect(html).toContain('CEDIS');
    expect(html).toContain('City');
    expect(html).toContain('Tarea receptora #5');
    expect(html).toContain('Pieza 1: identidad, cantidad y condición buena verificadas');
    expect(html).toContain('Pieza 2: identidad, cantidad y condición buena verificadas');
    expect(html).not.toMatch(/peso_real_kg|catalogo_tipo_caja_id|catalogo_tipo_guia_id|anaquel/i);
});
it('muestra pendientes y distingue reportar faltante de acreditar llegada', () => {
    const html = renderToStaticMarkup(<Abastecimiento tarea={null} traspasos={{ data: [{ id: 1, folio: 'TRA-1', version: 3, pedido: 'PED-1', tarea_id: 5,
        origen: 'Carmen', destino: 'City', salida_at: '2026-10-15T12:00:00', puede_llegada: true,
        lineas: [{ id: 2, sku: 'CREMA', esperadas: 3, buenas: 2, danadas: 0, pendientes: 1, incidencia_abierta: 0, faltantes_reportados: 1, rechazadas: 0 }], operaciones: [] }], links: [] }} />);
    expect(html).toContain('Reportar piezas faltantes');
    expect(html).toContain('Buenas recibidas 2');
    expect(html).toContain('En tránsito 1');
    expect(html).toContain('Faltantes reportados 1');
});
