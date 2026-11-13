import {Browser,save,assert} from './browser.mjs';
const b=new Browser();const reporte=[];
try{
 for(const actor of ['cedis','tienda']){
  await b.start(actor);
  const url=actor==='cedis'?'/control-pedidos/cedis/listado?tab=PENDIENTES_PESAJE&q=QA-PR34-CEDORDEN':'/control-pedidos/tienda/listado?tab=TODOS&q=QA-PR34-ORDEN';
  const uno=await b.api(url+'&page=1'),dos=await b.api(url+'&page=2'),otra=await b.api(url+'&page=1');
  const clave=actor==='cedis'?'pedidos':'tareas';
  assert(uno.status===200&&dos.status===200&&otra.status===200,'Falló listado',{uno,dos});
  const folios=r=>r.data[clave].data.map(e=>actor==='cedis'?(e.folio||e.folio_remision):e.pedido.folio);
  save('jerarquia-'+actor+'-paginas',{uno,dos});
  const a=folios(uno),c=folios(dos),repetida=folios(otra);
  assert(a[0].endsWith('-MD')&&a[1].endsWith('-ACTUALIZADA')&&a[2].endsWith('-NUEVA'),'Prioridad incorrecta',a);
  assert(JSON.stringify(a)===JSON.stringify(repetida),'Orden inestable',{a,repetida});
  assert(a.length===15&&c.length===8&&new Set([...a,...c]).size===23,'Paginación duplica o pierde',{a,c});
  const restantes=[...a,...c].slice(3);const primerNacional=restantes.findIndex(f=>f.endsWith('NACIONAL'));assert(restantes.slice(0,primerNacional).every(f=>f.endsWith('MUNICIPAL'))&&restantes.slice(primerNacional).every(f=>f.endsWith('NACIONAL')),'No respeta configuración',restantes);
  await b.go(actor==='cedis'?'/control-pedidos/cedis?tab=PENDIENTES_PESAJE&q=QA-PR34-CEDORDEN':'/control-pedidos/tienda?tab=TODOS&q=QA-PR34-ORDEN');await b.wait("document.body.innerText.includes('ORDEN')");
  const vistas=[];
  for(const width of [1440,1024,390]){await b.shot(`jerarquia-${actor}-${width}`,width);vistas.push(await b.js('return {width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1}'));}
  reporte.push({actor,total:uno.data[clave].total,pagina1:a,pagina2:c,estable:true,vistas});save('jerarquia',reporte);
  await b.close();
 }
 console.log(JSON.stringify({resultado:'aprobado',bandejas:2,pedidos_por_bandeja:23}));
}finally{await b.close();}
