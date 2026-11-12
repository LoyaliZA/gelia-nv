import {Browser,save,assert} from './browser.mjs';
const a=new Browser(),b=new Browser();const reporte=[];const id=2;
const paso=(nombre,r)=>{reporte.push({paso:nombre,...r});save('preparacion-transferencia',reporte);assert(r.status===200,nombre,r.status);return r;};
try{
 await a.start('auxiliar');await b.start('cedis');
 const numero=await a.api('/control-pedidos/auditar/2/detalle');assert(numero.data.pedido.numero_remision==='QA34-REMISION-FICTICIA','No se guardó remisión',numero.status);reporte.push({paso:'número de remisión persistido',numero:numero.data.pedido.numero_remision});
 paso('documento remisión ficticia',await a.api(`/control-pedidos/auditar/${id}/remision`,'POST',{fecha_emision:'2026-11-12'},[{field:'remision',name:'QA34-remision-ficticia.pdf',size:1024}]));
 paso('aprobar pedido',await a.api(`/control-pedidos/auditar/${id}/aprobar`,'POST',{}));
 const previo=await b.api(`/control-pedidos/${id}/transferencia-tienda`);save('preparacion-transferencia-previo',previo);assert(previo.data.puede_preparar,'No habilitó preparación tras aprobación',previo);
 const png=await b.js("const c=document.createElement('canvas');c.width=300;c.height=160;const x=c.getContext('2d');x.fillStyle='#eeeeee';x.fillRect(0,0,300,160);x.fillStyle='#111111';x.font='16px sans-serif';x.fillText('BOLSA FICTICIA QA PR34',20,80);return c.toDataURL('image/png').split(',')[1]");
 const preparado=paso('preparar una bolsa',await b.api(`/control-pedidos/${id}/transferencia-tienda/preparar`,'POST',{version:previo.data.version,observaciones:'Preparación ficticia de aceptación; sin salida física.'},[{field:'bultos[0][foto_bulto]',name:'QA34-bolsa.png',mime:'image/png',base64:png}]));
 save('preparacion-transferencia-final',preparado);assert(preparado.data.estado==='preparada','No quedó preparada',preparado.data.estado);
 await b.go('/control-pedidos/cedis?q=QA34-TRANSFERENCIA');await b.wait("document.body.innerText.includes('QA34-TRANSFERENCIA')");await b.shot('transferencia-preparada-1440');
 console.log(JSON.stringify({resultado:'aprobado',estado:preparado.data.estado,salida_registrada:false}));
}finally{await a.close();await b.close();}
