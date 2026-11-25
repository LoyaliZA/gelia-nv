// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import AccionesRapidasDisponibilidad from './AccionesRapidasDisponibilidad';
import SeguimientoSolicitudTienda from './SeguimientoSolicitudTienda';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let container, root;
const faltante = { id: 8, descripcion_producto: 'Aroma agotado', sku: 'SKU-8', estado_fisico: 'sin_existencia', cantidad_solicitada: 1 };
const boton = texto => [...container.querySelectorAll('button')].find(b => b.textContent === texto);
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('libera la pausa al desaparecer la acción de una pieza resuelta', async () => {
    const onOcupado = vi.fn();
    await act(() => root.render(<AccionesRapidasDisponibilidad pedidoId={1} pieza={faltante} onOcupado={onOcupado} />));
    await act(() => boton('Retirar').click());
    expect(onOcupado).toHaveBeenLastCalledWith(true, 8);
    // El padre deja de renderizar acciones cuando la respuesta trae resolución.
    await act(() => root.render(<span>Retiro registrado</span>));
    expect(onOcupado).toHaveBeenLastCalledWith(false, 8);
});
it('un faltante ofrece solo retirar o sustituir, sin aceptar su inexistencia', async () => {
    await act(() => root.render(<AccionesRapidasDisponibilidad pedidoId={1} pieza={faltante} />));
    expect(boton('Retirar')).toBeDefined(); expect(boton('Sustituir')).toBeDefined(); expect(boton('Confirmar estado')).toBeUndefined();
    await act(() => boton('Retirar').click()); expect(axios.post).not.toHaveBeenCalled();
    axios.post.mockResolvedValue({ data: { id: 1 } }); await act(() => boton('Confirmar retiro').click());
    expect(axios.post.mock.calls[0][1].get('revision_id')).toBe('8'); expect(axios.post.mock.calls[0][1].get('accion')).toBe('retirar');
});
it('un detalle permite confirmar estado y una sustitución admite captura pegada', async () => {
    await act(() => root.render(<AccionesRapidasDisponibilidad pedidoId={1} pieza={{ ...faltante, estado_fisico: 'regular' }} />));
    expect(boton('Confirmar estado')).toBeDefined();
    await act(() => boton('Sustituir').click()); await act(() => boton('Por captura o PDF').click());
    expect(container.textContent).toContain('pega con Ctrl+V');
    await act(() => boton('Solicitar sustitución').click()); expect(axios.post).not.toHaveBeenCalled(); expect(container.textContent).toContain('Seleccione la alternativa');
});
it('la consulta cerrada con faltante muestra alerta, reabre y conserva el ajuste detallado plegado', async () => {
    const pedido = { id: 1, solicitud_preparacion: { modalidad_entrega: 'tienda', revision_disponibilidad: { estado: 'excepciones_pendientes' } }, revisiones_producto: [faltante], motivo_bloqueo_cierre: 'Faltante por resolver' };
    axios.post.mockResolvedValue({ data: {} }); axios.get.mockResolvedValue({ data: { ...pedido, consulta_cerrada: false } });
    await act(() => root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} revisiones={[faltante]} consultaCerrada area="control_pedidos" />));
    expect(container.querySelector('[role=alert]').className).toContain('tienda-alerta-disponibilidad');
    expect(container.textContent).toContain('Consulta cerrada con piezas sin existencias');
    await act(() => boton('Reabrir consulta').click());
    expect(axios.post.mock.calls[0][0]).toContain('reabrir-consulta'); expect(boton('Retirar')).toBeDefined();
    expect(boton('Modificar pedido').getAttribute('aria-expanded')).toBe('false');
    await act(() => boton('Modificar pedido').click()); expect(boton('Modificar pedido').getAttribute('aria-expanded')).toBe('true');
});
it('los ajustes guardados permanecen visibles cuando la captura avanzada está plegada', async () => {
    const pedido = { id: 1, solicitud_preparacion: { revision_disponibilidad: { estado: 'excepciones_pendientes' }, anexo_sin_remision: [{ descripcion: 'Alternativa enviada', entrada: 'documento' }] } };
    await act(() => root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} area="control_pedidos" />));
    const fila = container.querySelector('[data-origen="cambio"]');
    expect(fila.textContent).toContain('Alternativa enviada'); expect(fila.textContent).toContain('Por revisar'); expect(fila.getAttribute('data-tono')).toBe('aviso');
    expect(container.textContent).not.toContain('Cambios del pedido');
    expect(boton('Modificar pedido').getAttribute('aria-expanded')).toBe('false');
});

it('en Ventas despliega la respuesta del almacén y contrae anexos y retiros pedidos', async () => {
 const pedido={id:1,solicitud_preparacion:{
  revision_disponibilidad:{estado:'sin_excepciones',respondida_at:'2026-11-06T10:00:00Z'},
  anexo_sin_remision:[
   {entrada:'catalogo',sku:'ANT',descripcion:'Pieza respondida',solicitado_at:'2026-11-06T09:00:00Z',respondida_at:'2026-11-06T10:00:00Z',respuesta_cambio:'correcto'},
   {entrada:'catalogo',sku:'NUE',descripcion:'Anexo pedido',solicitado_at:'2026-11-06T11:00:00Z'},
  ],
  retiro_piezas:[{estado:'pendiente',solicitado_at:'2026-11-06T11:05:00Z',piezas:[{descripcion:'Retiro pedido',sku:'R',cantidad:1}]}],
 }};
 await act(()=>root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} area="control_pedidos"/>));
 const tabla=container.querySelector('[aria-label="Productos de la respuesta"]');
 const abiertos=[...tabla.querySelectorAll('[data-origen="cambio"]')].map(f=>[f.textContent,f.getAttribute('data-tono')]);
 expect(abiertos).toHaveLength(2);
 expect(abiertos[0][0]).toContain('Anexo pedido'); expect(abiertos[0][1]).toBe('aviso');
 expect(abiertos[1][0]).toContain('Retiro pedido'); expect(abiertos[1][1]).toBe('peligro');
 const minis=[...container.querySelectorAll('.tienda-respuesta-oks .consulta-cambio')].map(m=>m.textContent);
 expect(minis).toHaveLength(1);expect(minis[0]).toContain('Pieza respondida');expect(minis[0]).toContain('Todo OK');
 expect(container.querySelector('.tienda-respuesta-movimientos')).toBeNull();
});

it('genera la clave contra duplicados cuando el acceso HTTP no ofrece randomUUID', () => {
    const original = globalThis.crypto;
    vi.stubGlobal('crypto', { getRandomValues: original.getRandomValues.bind(original) });
    try { expect(uuidSolicitud()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/); }
    finally { vi.unstubAllGlobals(); }
});

it('el menú compacto conserva el ID de la segunda unidad con SKU repetido y adopta el bloqueo actualizado', async () => {
 const primera={...faltante,estado_fisico:'regular',id:8};const segunda={...faltante,id:9};
 const pedido={id:1,solicitud_preparacion:{area_revision:'control_pedidos',revision_disponibilidad:{estado:'excepciones_pendientes'}},revisiones_producto:[primera,segunda],motivo_bloqueo_cierre:'Resolver el faltante original'};
 await act(()=>root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} revisiones={[primera,segunda]} area="control_pedidos"/>));
 const filas=container.querySelectorAll('.tienda-respuesta-fila');expect(filas).toHaveLength(2);
 await act(()=>filas[1].querySelector('summary').click());
 expect(filas[1].querySelector('details').open).toBe(true);
 expect(filas[1].textContent).not.toContain('Confirmar estado');
 await act(()=>[...filas[1].querySelectorAll('button')].find(b=>b.textContent==='Retirar').click());
 expect(axios.post).not.toHaveBeenCalled();
 axios.post.mockResolvedValue({data:{...pedido,revisiones_producto:[primera,{...segunda,resolucion:'retirar'}],motivo_bloqueo_cierre:'Confirma el estado de la primera unidad'}});
 await act(()=>boton('Confirmar retiro').click());
 expect(axios.post.mock.lastCall[1].get('revision_id')).toBe('9');
 expect(container.textContent).toContain('Retiro registrado');
 expect(container.textContent).toContain('Confirma el estado de la primera unidad');
 expect(container.textContent).not.toContain('Resolver el faltante original');
});

it('al confirmar el retiro deja Cerrar consulta disponible y Modificar pedido, sin cerrar sola', async () => {
 const pedido={id:1,puede_cerrar_consulta:false,solicitud_preparacion:{area_revision:'control_pedidos',revision_disponibilidad:{estado:'excepciones_pendientes'}},revisiones_producto:[faltante],motivo_bloqueo_cierre:'Hay piezas sin existencias: retírelas o solicite su sustitución antes de cerrar la consulta.'};
 await act(()=>root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} revisiones={[faltante]} area="control_pedidos"/>));
 expect(boton('Cerrar consulta')).toBeUndefined();
 expect(boton('Modificar pedido')).toBeDefined();
 axios.post.mockResolvedValue({data:{id:1,puede_cerrar_consulta:true,motivo_bloqueo_cierre:null,consulta_cerrada:false,revisiones_producto:[{...faltante,resolucion:'retirar'}],solicitud_preparacion:{area_revision:'control_pedidos',revision_disponibilidad:{estado:'decision_registrada',aceptacion_vigente:true},retiro_piezas:[{estado:'aplicada',piezas:[{descripcion:'Aroma agotado',sku:'SKU-8',cantidad:1}]}]}}});
 await act(()=>boton('Retirar').click());
 await act(()=>boton('Confirmar retiro').click());
 expect(axios.post.mock.calls.every(c => !String(c[0]).includes('cerrar-consulta'))).toBe(true);
 expect(boton('Cerrar consulta')).toBeDefined();
 expect(boton('Modificar pedido')).toBeDefined();
 expect(container.textContent).not.toContain('Consulta cerrada');
});

it('un agregado nuevo conserva el historial y bloquea el cierre anterior hasta recibir respuesta', async () => {
 const pedido={id:1,puede_cerrar_consulta:true,solicitud_preparacion:{area_revision:'control_pedidos',revision_disponibilidad:{estado:'sin_excepciones'}}};
 vi.spyOn(URL,'createObjectURL').mockReturnValue('blob:ajuste');vi.spyOn(URL,'revokeObjectURL').mockImplementation(()=>{});
 await act(()=>root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} area="control_pedidos"/>));
 expect(boton('Cerrar consulta')).toBeDefined();await act(()=>boton('Modificar pedido').click());
 const input=container.querySelector('input[aria-label="Adjuntar: Piezas a agregar"]');
 Object.defineProperty(input,'files',{configurable:true,value:[new File(['foto'],'anexo.png',{type:'image/png'})]});
 await act(()=>input.dispatchEvent(new Event('change',{bubbles:true})));
 axios.post.mockResolvedValue({data:{solicitud_preparacion:{...pedido.solicitud_preparacion,revision_disponibilidad:{estado:'sin_excepciones',aceptacion_vigente:false},anexo_sin_remision:[{descripcion:'Agregado nuevo',entrada:'documento'}]}}});
 await act(()=>boton('Solicitar agregado').click());
 expect(boton('Cerrar consulta')).toBeUndefined();expect(container.textContent).toContain('Agregado nuevo');
 expect(container.textContent).toContain('Hay un anexo pendiente de respuesta');
 expect(boton('Modificar pedido')).toBeDefined();
 vi.restoreAllMocks();
});

it('Escape cierra el menú compacto y devuelve foco sin propagar el cierre al modal', async () => {
 await act(()=>root.render(<AccionesRapidasDisponibilidad pedidoId={1} pieza={faltante} compacta/>));
 const summary=container.querySelector('summary');await act(()=>summary.click());expect(container.querySelector('details').open).toBe(true);
 const modalEscape=vi.fn();document.addEventListener('keydown',modalEscape,true);
 try {await act(()=>summary.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true})));expect(container.querySelector('details').open).toBe(false);expect(document.activeElement).toBe(summary);expect(modalEscape).not.toHaveBeenCalled();}
 finally {document.removeEventListener('keydown',modalEscape,true);}
});
