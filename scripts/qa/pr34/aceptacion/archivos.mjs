import {Browser,fixtures,save,assert} from './browser.mjs';
const b=new Browser(),MiB=1024*1024;const reporte=[];
async function snapshot(id){const r=await b.api(`/control-pedidos/${id}/disponibilidad-tienda`);assert(r.status===200,'No leyó pedido',r);const docs=r.data.documentos.filter(d=>d.tipo==='anexo_piezas');return {ids:docs.map(d=>d.id),bytes:docs.reduce((n,d)=>n+d.tamano_bytes,0),documentos:docs.map(d=>({id:d.id,bytes:d.tamano_bytes,url:d.url}))};}
async function enviar(id,clave,lote,bytes,name){return b.api(`/control-pedidos/${id}/anexo-tienda`,'POST',{entrada:'documento',solicitud_id:clave,lote},[{name,size:bytes}]);}
try{
 await b.start('ventas');
 for(const caso of ['archivos','archivos-exceso']){
  const id=fixtures.pedidos[caso].id;let actual=await b.api(`/control-pedidos/${id}/disponibilidad-tienda`);
  const consulta=await b.api(`/control-pedidos/${id}/solicitud-tienda`,'POST',{area_revision:'control_pedidos',revision_esperada:actual.data.revision_registro,base_captura:actual.data.base_captura});assert(consulta.status===200,'No creó consulta de archivos',consulta);
  const previo=await enviar(id,crypto.randomUUID(),0,MiB,`QA34-${caso}-previo.pdf`);assert(previo.status===200,'No guardó previo',previo);
  const clave=crypto.randomUUID();
  for(let i=0;i<6;i++){const r=await enviar(id,clave,i,8*MiB,`QA34-${caso}-${i}.pdf`);assert(r.status===200,'Falló lote '+i,r);reporte.push({caso,lote:i,bytes:8*MiB,status:r.status});save('archivos-progreso',reporte);}
  const antes=await snapshot(id);assert(antes.bytes===49*MiB,'No acumuló 49 MiB',antes);
  const final=await enviar(id,clave,6,MiB+(caso==='archivos-exceso'?1:0),`QA34-${caso}-final.pdf`);
  if(caso==='archivos-exceso'){
   assert(final.status===422,'Aceptó 50 MiB + 1',final);const despues=await snapshot(id);assert(JSON.stringify(antes.ids)===JSON.stringify(despues.ids)&&antes.bytes===despues.bytes,'Perdió documentos al fallar',{antes,despues});
   reporte.push({caso,paso:'50 MiB + 1 byte rechazado',status:final.status,antes,despues});
   const corregido=await enviar(id,clave,6,MiB,'QA34-exceso-corregido.pdf');assert(corregido.status===200,'No recuperó lote fallido',corregido);
  }else assert(final.status===200,'No aceptó exactamente 50 MiB',final);
  const exacto=await snapshot(id);assert(exacto.bytes===50*MiB&&exacto.ids.length===8,'Cupo o cantidad incorrectos',exacto);
  const retry=await enviar(id,clave,6,MiB,'QA34-reintento-diferente.pdf');assert(retry.status===200,'Falló reintento',retry);
  const luego=await snapshot(id);assert(JSON.stringify(exacto.ids)===JSON.stringify(luego.ids),'Duplicó reintento',{exacto,luego});
  const fallo=await enviar(id,crypto.randomUUID(),0,1024,'QA34-nuevo-excedente.pdf');assert(fallo.status===422,'Aceptó excedente',fallo);
  const preservado=await snapshot(id);assert(JSON.stringify(exacto.ids)===JSON.stringify(preservado.ids),'Alteró documentos anteriores',preservado);
  const lectura=await b.api(exacto.documentos[0].url);assert(lectura.status===200,'Sin permiso de lectura',lectura.status);
  reporte.push({caso,paso:'exactamente 50 MiB, reintento y lectura',exacto,reintento:retry.status,excedente:fallo.status,lectura:lectura.status});save('archivos-resultados',reporte);
 }
 console.log(JSON.stringify({resultado:'aprobado',casos:2,limite_bytes:50*MiB}));
}finally{await b.close();}
