// Build real y Selenium con documentos, catálogo y transporte ficticios. No escribe pedidos.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const destino = '/tmp/gelia-bandejas-operativas-qa';
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

const tareas = fixtures.slice(0,3).map((p,i)=>({ id:p.id, version:1, estado:['PENDIENTE','EN_ATENCION','RESPONDIDA'][i], estado_label:['Pendiente','En atención','Respondida'][i], solicitada_at:'2026-11-08T10:00:00-06:00', almacen:{id:1,nombre:'Sucursal de ejemplo'}, modalidad:{codigo:'RECOGE_TIENDA',nombre:'Recoge en tienda'}, pedido:{id:p.id,folio:p.folio,folio_visible:p.folio_remision,cliente_nombre:p.cliente.nombre,vendedora_nombre:p.vendedor.name,es_t01_documental:true},productos:[] }));
const metricas={todos:4,tipos:{tienda:2,mismo_dia:0,municipal:1,nacional:1},pendientes:1,en_atencion_operativa:1,atendidos:1};
let solicitudes=[];
const servidor=http.createServer(async(req,res)=>{
 try{
  if(req.url.startsWith('/qa?')){
   const params=new URL(req.url,proxy).searchParams,tema=params.get('tema'),vista=params.get('vista');
   solicitudes=[];
   const pagina=structuredClone(paginaBase);pagina.component=`ControlPedidos/${vista}/Index`;pagina.url='/qa';
   pagina.props.auth.user={id:999999,name:'Operador ficticio',roles:[],permissions:['control_pedidos.cedis','control_pedidos.tienda.ver','control_pedidos.tienda.tomar']};
   pagina.props.auth.webpush={enabled:false};
   Object.assign(pagina.props,{fecha_hoy:'2026-11-08',can:{bitacora:false},filtros:{},metricas:{...metricas,todos:vista==='Tienda'?3:4},pedidos:{data:fixtures,links:[],total:4,current_page:1,last_page:1},tareas:{data:tareas,links:[],total:3,current_page:1,last_page:1},tipos_caja:[],almacenes_busqueda:[]});
   const init=`<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');document.documentElement.classList.add('glass-active');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
   const html=htmlBase.replace(/(const Ziggy=\{"url":)"[^"]+"/,(_,a)=>a+JSON.stringify(proxy)).replace('<html lang="es">',`<html lang="es" class="${tema==='dark'?'dark':''}">`).replace('</head>',`${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s,(_,a,b)=>a+JSON.stringify(pagina).replace(/</g,'\\u003c')+b).replaceAll('http://localhost:9080',proxy).replaceAll(`${origen}/`,`${proxy}/`);
   res.writeHead(200,{'Content-Type':'text/html'});res.end(html);return;
  }
  if(req.url.includes('/listado'))solicitudes.push(req.url);
  if(!req.url.startsWith('/build/')){res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify({pedidos:{data:fixtures,total:4,current_page:1,last_page:1},tareas:{data:tareas,total:3,current_page:1,last_page:1},metricas,data:[],conversaciones:[],notificaciones:[],unread_count:0}));return;}
  const recurso=await fetch(`${origen}${req.url}`);res.writeHead(recurso.status,{'Content-Type':recurso.headers.get('content-type')||'application/octet-stream'});res.end(Buffer.from(await recurso.arrayBuffer()));
 }catch(e){res.writeHead(500);res.end(String(e));}
});
await new Promise(resolve=>servidor.listen(9192,'0.0.0.0',resolve));
let sessionId;const reporte=[];
async function comando(path,body,method='POST'){
 const r=await fetch(`${selenium}/session${sessionId?`/${sessionId}`:''}${path}`,{method,...(body?{headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}:{})});
 const d=await r.json();if(d.value?.error)throw Error(JSON.stringify(d.value));return d.value;
}
const ejecutar=script=>comando('/execute/sync',{script,args:[]});
async function esperar(exp){const ok=await comando('/execute/async',{script:`const done=arguments[arguments.length-1];let n=0;function f(){if(${exp})done(true);else if(++n>100)done(false);else setTimeout(f,100)}f();`,args:[]});if(!ok)throw Error(exp);}
async function captura(nombre){await esperar('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');fs.writeFileSync(`${destino}/${nombre}.png`,Buffer.from(await comando('/screenshot',null,'GET'),'base64'));}
try{
 const sesion=await comando('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']}}}});sessionId=sesion.sessionId;
 for(const vista of ['Cedis','Tienda'])for(const size of [{nombre:'movil',width:390,height:844},{nombre:'escritorio',width:1440,height:1000}])for(const tema of ['light','dark']){
  await comando('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width:size.width,height:size.height,deviceScaleFactor:1,mobile:size.width<600}});
  await comando('/url',{url:`${proxy}/qa?vista=${vista}&tema=${tema}`});
  await esperar('document.querySelector("input[type=search]") && document.body.textContent.includes("Más antiguos")');
  const datos=await ejecutar(`return {desborde:document.documentElement.scrollWidth>innerWidth+1,indicadores:[...document.querySelectorAll('[aria-label="${vista==='Cedis'?'Tipo de pedido':'Estado de atención'}"] button')].map(b=>({texto:b.textContent,activo:b.getAttribute('aria-pressed')})),errores:window.qaErrores}`);
  if(datos.desborde||datos.errores.length||datos.indicadores.filter(i=>i.activo==='true').length!==1||datos.indicadores[0].activo!=='true')throw Error(JSON.stringify(datos));
  await captura(`${vista}-${size.nombre}-${tema}`);
  await ejecutar(`document.querySelector('details:has(summary)').open=true`);
  await captura(`${vista}-${size.nombre}-${tema}-filtros`);
  await ejecutar(`document.querySelector('details:has(summary)').open=false;const listado=document.querySelector('.gelia-pedidos-tarjeta')||document.querySelector('.gelia-tienda-op-workspace:last-child');listado?.scrollIntoView({block:'start',behavior:'instant'})`);
  await captura(`${vista}-${size.nombre}-${tema}-pedidos`);
  await ejecutar(`document.querySelector('details:has(summary)').open=false;[...document.querySelectorAll('button')].find(b=>b.textContent==='Hoy').click()`);
  await esperar('location.search.includes("desde=2026-11-08")');
  await ejecutar(`[...document.querySelectorAll('button')].find(b=>b.textContent==='Más antiguos').click()`);
  await esperar('!location.search.includes("desde=")');
  await ejecutar(`document.querySelector('[aria-label="${vista==='Cedis'?'Tipo de pedido':'Estado de atención'}"] button:nth-child(2)').click()`);
  await esperar(`location.search.includes('${vista==='Cedis'?'tipo=tienda':'tab=PENDIENTES'}')`);
  if(!solicitudes.length)throw Error('No se ejercitó el listado JSON');
  reporte.push({vista,...size,tema,...datos,solicitudes:[...solicitudes]});
 }
 fs.writeFileSync(`${destino}/verificacion.json`,JSON.stringify(reporte,null,2));console.log(JSON.stringify({destino,escenarios:reporte.length,capturas:reporte.length*3}));
}finally{if(sessionId)await comando('',null,'DELETE');await new Promise(r=>servidor.close(r));}
