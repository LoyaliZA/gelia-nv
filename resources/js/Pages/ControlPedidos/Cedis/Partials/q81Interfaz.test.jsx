// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import TarjetasCedis from './TarjetasCedis';
import SeccionRevisionFisicaPedido from '../../Partials/SeccionRevisionFisicaPedido';
import EncabezadoRevisionPedido from '../../Auditar/Partials/EncabezadoRevisionPedido';
import BarraAccionesRevision from '../../Auditar/Partials/BarraAccionesRevision';

const perfil = vi.hoisted(() => ({ permissions: ['control_pedidos.cedis.enviar'], roles: [] }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: perfil } } }), router: { post: vi.fn() } }));
let root, container;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('route', () => '/prueba');
    vi.stubGlobal('localStorage', { getItem: vi.fn(() => null) });
    perfil.permissions = ['control_pedidos.cedis.enviar']; perfil.roles = [];
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.unstubAllGlobals(); });
const pedido = { id: 81, folio: 'GELIA-81', folio_remision: 'PED-81', origen: { nombre: 'Tienda', requiere_logistica: false },
    es_traslado_interno_pdv: true, almacen: { nombre: 'Almacén salida' }, sucursal_destino: { nombre: 'Sucursal destino' },
    cliente: { nombre: 'Cliente de prueba' }, estatus: { fase_ciclo: 'EN_CEDIS' }, numero_cajas: 2, documentos: [], cajas: [] };
const renderTarjeta = (extra = {}, props = {}) => act(() => root.render(<TarjetasCedis pedidos={{ data: [{ ...pedido, ...extra }] }} {...props} />));
it('distingue tipo, almacén, destino, recorrido y folios en un traslado sin guía comercial', async () => {
    await renderTarjeta({ tarea_preparacion_vigente: { modalidad: { nombre: 'Preparación desde tienda' } } });
    expect(container.querySelector('header').textContent).toContain('Tipo de pedidoTienda');
    for (const texto of ['Origen', 'Almacén salida', 'Destino', 'Sucursal destino', 'Traslado interno', 'Preparación desde tienda', 'PED-81', 'Folio Pedido: PED-81', 'GELIA-81', 'Bolsas a preparar']) expect(container.textContent).toContain(texto);
    expect(container.textContent).not.toContain('Envíos / Guía');
    expect(container.textContent).not.toContain('Paquetería');
});
it('mantiene la consulta y bloquea empaque y salida mientras hay actualización pendiente', async () => {
    const responder = vi.fn();
    await renderTarjeta({ estatus_envio: 'pendiente_pesaje', consulta_actualizacion_pendiente: true }, { onResponderPesaje: responder });
    expect(container.textContent).toContain('Actualización de consulta solicitada');
    expect(container.textContent).not.toContain('Pendiente de pesaje CEDIS');
    const botones = [...container.querySelectorAll('button')];
    expect(botones.some((b) => b.textContent.includes('Marcar empacado'))).toBe(false);
    await act(() => botones.find((b) => b.textContent.includes('Responder consulta')).click());
    expect(responder).toHaveBeenCalledWith(expect.objectContaining({ id: 81 }));
    await renderTarjeta({ estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' }, consulta_actualizacion_pendiente: true });
    expect([...container.querySelectorAll('button')].some((b) => b.textContent.includes('Marcar enviado'))).toBe(false);
});
it('mantiene paquetería, guía y pesaje del envío externo', async () => {
    await renderTarjeta({ origen: { nombre: 'Envío', requiere_logistica: true }, es_traslado_interno_pdv: false,
        estatus_envio: 'pendiente_pesaje', paqueteria: { nombre: 'Transporte comercial' } });
    for (const texto of ['Tipo de pedidoEnvío', 'Transporte comercial', 'Envíos / Guía', 'Responder pesaje']) expect(container.textContent).toContain(texto);
});
it('no presenta atributos de pieza activados por defecto y muestra ambos cuando están guardados', async () => {
    const revision = { id: 1, estado_fisico: 'danado', descripcion_producto: 'Pieza', comentario: 'Rayada' };
    await act(() => root.render(<SeccionRevisionFisicaPedido pedido={{ revisiones_producto: [revision] }} />));
    expect(container.textContent).not.toContain('Única pieza');
    expect(container.textContent).not.toContain('Mejor ejemplar');
    await act(() => root.render(<SeccionRevisionFisicaPedido pedido={{ revisiones_producto: [{ ...revision, unica_pieza: true, mejor_ejemplar: true }] }} />));
    const atributos = container.querySelector('[aria-label="Características de la pieza"]');
    expect(atributos.textContent).toContain('Única pieza'); expect(atributos.textContent).toContain('Mejor ejemplar');
});
it('mantiene metadatos accesibles y cierre de revisión con nombre', async () => {
    const cerrar = vi.fn();
    await act(() => root.render(<EncabezadoRevisionPedido pedido={pedido} badge={{ label: 'En revisión' }} avisoAprobacion="Valida el pago para aprobar el pedido." onClose={cerrar} />));
    const detalles = container.querySelector('details');
    expect(detalles.querySelector('summary').textContent).toBe('Datos de revisión');
    expect(detalles.textContent).toContain('GELIA-81');
    expect(detalles.textContent).toContain('Valida el pago');
    await act(() => container.querySelector('[aria-label="Cerrar revisión"]').click()); expect(cerrar).toHaveBeenCalledOnce();
});
it('conserva acciones secundarias autorizadas y explica el bloqueo de aprobación', async () => {
    const reportar = vi.fn();
    await act(() => root.render(<BarraAccionesRevision esPendiente muestraAprobar puedeAprobar={false} pagoValidado={false} onReportarError={reportar} />));
    expect(container.querySelector('summary').textContent).toBe('Más acciones');
    const botones = [...container.querySelectorAll('button')];
    expect(botones.find((b) => b.textContent.includes('Aprobar pedido')).disabled).toBe(true);
    expect(botones.find((b) => b.textContent.includes('Aprobar pedido')).title).toBe('Valide el pago antes de aprobar');
    await act(() => botones.find((b) => b.textContent.includes('Reportar error')).click()); expect(reportar).toHaveBeenCalledOnce();
});

it('una pieza retirada deja de contarse como actual y conserva acceso a su evidencia en historial', async () => {
    const verDoc = vi.fn();
    const foto = { id: 15, tipo: 'evidencia_condicion', relacion_tipo: 'revision_producto', relacion_id: 2, url: '/foto-prueba.jpg' };
    await act(() => root.render(<SeccionRevisionFisicaPedido pedido={{
        revisiones_producto: [{ id: 1, descripcion_producto: 'Actual', estado_fisico: 'bueno' },
            { id: 2, descripcion_producto: 'Anterior', estado_fisico: 'danado', retirada_at: '2026-10-15T12:00:00Z' }], documentos: [foto],
    }} onVerDoc={verDoc} />));
    expect(container.querySelector('.gelia-pedidos-revision-resumen').textContent).toContain('1 piezas revisadas');
    expect(container.querySelector('details').textContent).toContain('Historial de piezas retiradas (1)');
    await act(() => [...container.querySelectorAll('button')].find((b) => b.textContent.includes('Ver evidencia conservada')).click());
    expect(verDoc).toHaveBeenCalledWith(foto);
});
it('la consulta presenta cliente una vez, folios compactos, departamento arriba y omite piezas extra', async () => {
 await renderTarjeta({estatus_envio:'pendiente_pesaje',vendedor:{name:'Solicitante de ejemplo',departamento:{nombre:'Ventas'}},solicitud_preparacion:{area_revision:'control_pedidos',revision_disponibilidad:{estado:'sin_excepciones',actualizacion_pendiente:true}},documentos:[{tipo:'anexo_piezas',url:'/anexo.pdf'}]});
 expect(container.querySelector('.gelia-cedis-tipo-departamento').textContent).toContain('Ventas');
 expect(container.querySelector('.gelia-cedis-solicitante').textContent).toBe('Solicitante de ejemplo');
 expect(container.textContent.match(/Cliente de prueba/g)).toHaveLength(1);
 expect(container.textContent).toContain('Folio Pedido: PED-81');
 expect(container.textContent).not.toContain('Folio interno');
 expect(container.textContent).not.toContain('Piezas extra');
 expect(container.textContent).not.toContain('No pide peso');
 expect(container.querySelector('.gelia-cedis-aviso-consulta').textContent).toBe('Actualización de consulta solicitada');
});
it('reemplaza el aviso pendiente por una sola Consulta respondida', async () => {
 await renderTarjeta({estatus_envio:'pesaje_listo',estatus:{fase_ciclo:'PESAJE_RESPONDIDO'},solicitud_preparacion:{area_revision:'control_pedidos',revision_disponibilidad:{estado:'sin_excepciones'}}});
 expect(container.querySelector('.gelia-cedis-aviso-consulta').textContent).toBe('Consulta respondida');
 expect(container.textContent.match(/Consulta respondida/g)).toHaveLength(1);
 expect(container.querySelector('.gelia-pedidos-estados').textContent).not.toContain('Consulta respondida');
});
it('Bitácora necesita permiso explícito, incluso para el rol Gerente, y respeta la restricción del servidor', async () => {
 const abrir=vi.fn();
 await renderTarjeta({}, {onBitacora:abrir});
 expect([...container.querySelectorAll('button')].some(b=>b.textContent==='Bitácora')).toBe(false);
 perfil.roles=['Gerente'];await renderTarjeta({}, {onBitacora:abrir});
 expect([...container.querySelectorAll('button')].some(b=>b.textContent==='Bitácora')).toBe(false);
 perfil.permissions.push('control_pedidos.cedis.bitacora');await renderTarjeta({}, {onBitacora:abrir});
 await act(()=>[...container.querySelectorAll('button')].find(b=>b.textContent==='Bitácora').click());
 expect(abrir).toHaveBeenCalledOnce();
 await renderTarjeta({puede_ver_bitacora:false}, {onBitacora:abrir});
 expect([...container.querySelectorAll('button')].some(b=>b.textContent==='Bitácora')).toBe(false);
});
