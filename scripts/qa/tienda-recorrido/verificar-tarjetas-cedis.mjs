// Build real y Selenium con documentos, catálogo y transporte ficticios. No escribe pedidos.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const destino = '/tmp/gelia-tarjetas-cedis-qa';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const productos = [1, 2, 3, 4].map(i => ({ id: i, sku: `SKU-${i}`, descripcion: `Perfume de ejemplo ${i} · presentación de catálogo`, codigo_barras: `75000000000${i}` }));
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="white"/><rect x="50" y="160" width="500" height="100" fill="lightgray"/><rect x="50" y="290" width="500" height="100" fill="lightgray"/><rect x="50" y="420" width="500" height="100" fill="lightgray"/><text x="35" y="70" font-size="30">CAPTURA DE EJEMPLO</text></svg>';
const documento = { id: 1, nombre_original: 'pedido-ejemplo.svg', mime_type: 'image/svg+xml', url: 'data:image/svg+xml,' + encodeURIComponent(svg) };
const solicitudBase={area_revision:'control_pedidos',modalidad_entrega:'tienda'};
const vendedor={id:12,name:'Solicitante de ejemplo',departamento:{nombre:'TI'}};
const comun={fecha:'2026-11-06',cliente:{nombre:'CLIENTE FICTICIO CON APELLIDOS DE EJEMPLO'},vendedor,origen:{nombre:'TIENDA',requiere_logistica:false},almacen:null,sucursal_destino:{codigo:'PDV-01',nombre:'CARMEN CADENA'},es_consulta_mercancia:true,cajas:[],documentos:[{...documento,tipo:'pdf_pedido'},{...documento,id:2,tipo:'anexo_piezas'}]};
const fixtures=[
 {...comun,id:101,folio:'PBMA-2026-00003',folio_remision:'3',estatus:{fase_ciclo:'PESAJE_PENDIENTE'},estatus_envio:'pendiente_pesaje',solicitud_preparacion:{...solicitudBase,revision_disponibilidad:{estado:'sin_excepciones',actualizacion_pendiente:true}}},
 {...comun,id:102,folio:'PBMA-2026-00004',folio_remision:'4',estatus:{fase_ciclo:'PESAJE_RESPONDIDO'},estatus_envio:'pesaje_listo',solicitud_preparacion:{...solicitudBase,revision_disponibilidad:{estado:'sin_excepciones'}}},
 {...comun,id:103,folio:'PBMA-2026-00005',folio_remision:'5',estatus:{fase_ciclo:'PESAJE_PENDIENTE'},estatus_envio:'pendiente_pesaje',solicitud_preparacion:solicitudBase},
 {...comun,id:104,folio:'PBMA-2026-00006',folio_remision:'6',origen:{nombre:'NACIONAL',requiere_logistica:true},vendedor:{...vendedor,departamento:{nombre:'Ventas y atención comercial'}},almacen:{nombre:'Almacén de origen'},paqueteria:{nombre:'Transporte de ejemplo'},estatus:{fase_ciclo:'EN_CEDIS'},estatus_envio:'pendiente_pesaje',es_consulta_mercancia:false,solicitud_preparacion:null},
];
const servidor=http.createServer(async(req,res)=>{
 try{
  if(req.url.startsWith('/qa?')){
   const params=new URL(req.url,proxy).searchParams,tema=params.get('tema'),gerencia=params.get('gerencia')==='1';
   const pagina=structuredClone(paginaBase);pagina.component='ControlPedidos/Cedis/Index';pagina.url='/qa';
   pagina.props.auth.user={id:999999,name:'Operador ficticio',email:'qa@example.test',roles:gerencia?['Gerente']:[],permissions:['control_pedidos.cedis',...(gerencia?['control_pedidos.cedis.bitacora']:[])]};
   pagina.props.auth.webpush={enabled:false};
   Object.assign(pagina.props,{can:{bitacora:gerencia},filtros:{tab:'TODOS'},metricas:{},pedidos:{data:fixtures.map(p=>({...p,puede_ver_bitacora:gerencia,historial:gerencia?[{id:1,accion:'solicitud_pesaje',accion_etiqueta:'Consulta solicitada',comentario:'Movimiento ficticio'}]:undefined})),links:[],total:4,current_page:1,last_page:1},tipos_caja:[],almacenes_busqueda:[]});
   const init=`<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');document.documentElement.classList.add('glass-active');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
   const html=htmlBase.replace('<html lang="es">',`<html lang="es" class="${tema==='dark'?'dark':''}">`).replace('</head>',`${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s,(_,a,b)=>a+JSON.stringify(pagina).replace(/</g,'\\u003c')+b).replaceAll('http://localhost:9080',proxy).replaceAll(`${origen}/`,`${proxy}/`);
   res.writeHead(200,{'Content-Type':'text/html'});res.end(html);return;
  }
  if(!req.url.startsWith('/build/')){res.writeHead(200,{'Content-Type':'application/json'});res.end('{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}');return;}
  const recurso=await fetch(`${origen}${req.url}`);res.writeHead(recurso.status,{'Content-Type':recurso.headers.get('content-type')||'application/octet-stream'});res.end(Buffer.from(await recurso.arrayBuffer()));
 }catch(e){res.writeHead(500);res.end(String(e));}
});
await new Promise(resolve => servidor.listen(9192, '0.0.0.0', resolve));
let sessionId;
const reporte = [];
let capturasTotales = 0;
async function comando(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const respuesta = await r.json(); if (respuesta.value?.error) throw new Error(JSON.stringify(respuesta.value)); return respuesta.value;
}
const ejecutar = script => comando('/execute/sync', { script, args: [] });
async function esperar(script) {
    const ok = await comando('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function revisar(){if(${script})done(true);else if(++n>100)done(false);else setTimeout(revisar,100)}revisar();`, args: [] });
    if (!ok) throw new Error(`No se cumplió: ${script}`);
}
async function click(texto) { await ejecutar(`const b=[...document.querySelectorAll('button')].find(b=>b.textContent.trim()===${JSON.stringify(texto)});if(!b)throw Error('Botón ausente');b.click();`); }
async function foto(label, cantidad = 1, camara = false) {
    await comando('/execute/async', { script: `const done=arguments[arguments.length-1];const c=document.createElement('canvas');c.width=320;c.height=220;const ctx=c.getContext('2d');ctx.fillStyle='lightgray';ctx.fillRect(0,0,320,220);ctx.fillStyle='navy';ctx.fillRect(120,40,80,160);c.toBlob(blob=>{const input=document.querySelector('input[aria-label="${camara ? 'Tomar foto' : 'Adjuntar'}: ${label}"]');const dt=new DataTransfer();for(let i=0;i<${cantidad};i++)dt.items.add(new File([blob],'${label === 'Captura del pedido' ? 'captura' : 'defecto'}-'+i+'.png',{type:'image/png'}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));done(true);});`, args: [] });
}
async function captura(nombre) {
    capturasTotales += 1;
    await esperar('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');
    fs.writeFileSync(`${destino}/${nombre}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
}
try{
 const sesion=await comando('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']}}}});sessionId=sesion.sessionId;
 for(const tamaño of [{nombre:'escritorio',width:1440,height:1000,tema:'dark'},{nombre:'usuario',width:475,height:932,tema:'dark'},{nombre:'movil',width:390,height:844,tema:'light'}]){
  await comando('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width:tamaño.width,height:tamaño.height,deviceScaleFactor:1,mobile:tamaño.width<600}});
  for(const gerencia of [false,true]){
   await comando('/url',{url:`${proxy}/qa?tema=${tamaño.tema}&gerencia=${gerencia?1:0}`});
   await esperar('document.body.textContent.includes("Folio Pedido: 3")');
   const datos=await ejecutar(`const cards=[...document.querySelectorAll('.gelia-pedidos-tarjeta')];return cards.map(c=>{const h=c.querySelector('.gelia-cedis-tipo').getBoundingClientRect(),t=c.querySelector('.gelia-cedis-tipo-identidad').getBoundingClientRect();return {centrado:Math.abs((h.left+h.width/2)-(t.left+t.width/2))<2,bitacora:[...c.querySelectorAll('button')].some(b=>b.textContent==='Bitácora'),aviso:c.querySelector('.gelia-cedis-aviso-consulta')?.textContent,desborde:c.scrollWidth>c.clientWidth+1}})`);
   if(datos.length!==4||datos.some(d=>!d.centrado||d.bitacora!==gerencia||d.desborde))throw Error(JSON.stringify(datos));
   if(datos[0].aviso!=='Actualización de consulta solicitada'||datos[1].aviso!=='Consulta respondida'||datos[2].aviso!=='Nueva consulta solicitada')throw Error(JSON.stringify(datos));
   if(await ejecutar(`return document.body.textContent.includes('Folio interno GELIA')||document.body.textContent.includes('Piezas extra')`))throw Error('Texto redundante');
   for(const folio of ['3','4','5','6']){
    await ejecutar(`document.querySelector('article[aria-label="Pedido ${folio}"]').scrollIntoView({block:'center',behavior:'instant'})`);
    await esperar('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');
    const clip=await ejecutar(`const r=document.querySelector('article[aria-label="Pedido ${folio}"]').getBoundingClientRect();return {x:r.left+scrollX,y:r.top+scrollY,width:r.width,height:r.height,scale:1}`);
    if(clip.height<200)throw Error('Tarjeta recortada');
    const png=await comando('/goog/cdp/execute',{cmd:'Page.captureScreenshot',params:{format:'png',captureBeyondViewport:true,clip}});
    fs.writeFileSync(`${destino}/${tamaño.nombre}-${gerencia?'gerencia':'cedis'}-${folio}.png`,Buffer.from(png.data,'base64'));capturasTotales++;
   }
   const errores=await ejecutar('return window.qaErrores');if(errores.length)throw Error(JSON.stringify(errores));
   reporte.push({...tamaño,gerencia,datos,errores});
  }
 }
 fs.writeFileSync(`${destino}/verificacion.json`,JSON.stringify(reporte,null,2));console.log(JSON.stringify({destino,capturas:capturasTotales}));
}finally{if(sessionId)await comando('',null,'DELETE');await new Promise(r=>servidor.close(r));}
