import {Browser,save,assert} from './browser.mjs';
const ventas=new Browser(),tienda=new Browser();const reporte=[];
try {
 await ventas.start('ventas');await tienda.start('tienda');
 const actual=await ventas.api('/control-pedidos/1/disponibilidad-tienda');
 const revision=actual.data.solicitud_preparacion.revision_disponibilidad;
 const respuesta=await tienda.api('/control-pedidos/tienda/tareas/1/disponibilidad','POST',{confirmacion_explicita:1,actualizacion_id:revision.actualizacion_id,'cambios_revisados[0][tipo]':'anexo','cambios_revisados[0][indice]':0,'cambios_revisados[0][resultado]':'correcto'},[{name:'QA-PR34-conjunto-actualizado.png',mime:'image/png',base64:'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jL1sAAAAASUVORK5CYII='}]);
 reporte.push({paso:'respuesta actualizada con foto',...respuesta});save('consulta-finalizacion',reporte);assert(respuesta.status===200,'No respondió',respuesta);
 const cierre=await ventas.api('/control-pedidos/1/cerrar-consulta','POST',{});reporte.push({paso:'cierre',...cierre});save('consulta-finalizacion',reporte);assert(cierre.status===200,'No cerró',cierre);
 const final=await ventas.api('/control-pedidos/1/disponibilidad-tienda');save('consulta-estado-final',final);assert(final.data.consulta_cerrada,'Consulta no cerrada',final.data.consulta_cerrada);
 await tienda.go('/control-pedidos/tienda');await tienda.wait("document.body.innerText.includes('QA34-CONSULTA')");await tienda.click('Continuar preparación');await tienda.wait("document.body.innerText.includes('Respuesta enviada')");
 for(const width of [1440,1024,390])await tienda.shot('consulta-actualizada-cerrada-'+width,width);
 console.log(JSON.stringify({consulta_cerrada:final.data.consulta_cerrada,documentos:final.data.documentos.length,console:await tienda.cmd('/log',{type:'browser'})}));
}finally{await ventas.close();await tienda.close();}
