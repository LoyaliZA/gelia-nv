import {Browser,fixtures,save,assert} from './browser.mjs';
const ventas=new Browser(),tienda=new Browser();let reporte=[];
try {
 await ventas.start('ventas');await tienda.start('tienda');
 const p=fixtures.pedidos.consulta;const actual=await ventas.api(`/control-pedidos/${p.id}/disponibilidad-tienda`);save('consulta-lectura',actual);Object.assign(p,{revision_esperada:actual.data.revision_registro,base_captura:actual.data.base_captura});
 const envio=await ventas.api(`/control-pedidos/${p.id}/solicitud-tienda`,'POST',{area_revision:'preparacion_tienda',almacen_id:fixtures.tienda_id,revision_esperada:Number(p.revision_esperada||0),base_captura:p.base_captura});
 reporte.push({paso:'crear consulta',...envio});save('consulta-progreso',reporte);assert(envio.status===200,'No creó consulta',envio);
 const listado=await tienda.api('/control-pedidos/tienda/listado?tab=TODOS');
 save('tienda-listado',listado);
 const tarea=listado.data.tareas.data.find(t=>t.pedido?.id===p.id);assert(tarea,'No está en bandeja',listado);
 fixtures.pedidos.consulta.tarea_id=tarea.id;save('consulta-tarea',tarea);
 await tienda.go('/control-pedidos/tienda');
 await tienda.wait("document.body.innerText.includes('QA34-CONSULTA') || document.body.innerText.includes('QA-PR34-CONSULTA')");
 console.log(JSON.stringify({tarea_id:tarea.id,botones:await tienda.js('return [...document.querySelectorAll("button")].map(b=>b.textContent.trim()).filter(Boolean)')}));
 await tienda.shot('consulta-bandeja-1440');
 const tomar=await tienda.api(`/control-pedidos/tienda/tareas/${tarea.id}/tomar`,'POST',{version:tarea.version});reporte.push({paso:'tomar',...tomar});save('consulta-progreso',reporte);assert(tomar.status===200,'No tomó consulta',tomar);
 await tienda.go(`/control-pedidos/tienda/tareas/${tarea.id}`);
 console.log(JSON.stringify({vista:await tienda.js('return document.body.innerText.slice(-4500)'),inputs:await tienda.js('return [...document.querySelectorAll("input")].map(e=>({type:e.type,id:e.id,name:e.name}))')}));
 await tienda.shot('consulta-inicial-1440');
 save('consulta-progreso',reporte);
} finally {await ventas.close();await tienda.close();}
