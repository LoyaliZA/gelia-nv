// Formulario real del build, datos ficticios y servidor de transporte aislado. No escribe pedidos reales.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const destino = '/tmp/gelia-respuestas-retroalimentacion-qa';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw Error('Selenium está ocupado.');
const imagen = titulo => 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="600" height="600" fill="white"/><rect x="180" y="120" width="220" height="350" rx="25" fill="slategray"/><text x="30" y="60" font-size="26">${titulo}</text></svg>`);
const filas = [
    { id: 901, sku: 'SKU-1', descripcion_producto: 'Perfume de ejemplo · presentación de catálogo', cantidad_solicitada: 1, cantidad_encontrada: 1, estado_fisico: 'regular', unica_pieza: true, mejor_ejemplar: false, comentario: '' },
    { id: 902, sku: 'SKU-1', descripcion_producto: 'Perfume de ejemplo · presentación de catálogo', cantidad_solicitada: 1, cantidad_encontrada: 0, estado_fisico: 'sin_existencia', unica_pieza: false, mejor_ejemplar: false, comentario: 'Sin existencias' },
];
let pedido, caso, envios = [];
const solicitudBase = { modalidad_entrega: 'tienda', area_revision: 'control_pedidos', caso_tienda: 'consultar_cedis', revision_disponibilidad: { estado: 'excepciones_pendientes', respondida_at: '2026-11-06T10:30:00-06:00', respondida_por_id: 777 } };
function reiniciar(tipo) {
    caso = tipo;
    pedido = { id: 999, folio: 'PBMA-EJEMPLO', folio_remision: '20328', cliente_id: 1, departamento_operacion_id: 1, cliente: { nombre: 'Cliente ficticio de revisión', numero_cliente: '9999' }, sucursal_destino_id: 2, sucursal_destino: { id: 2, nombre: 'Carmen Cadena' }, almacen: { id: 1, nombre: 'CEDIS' }, estatus_envio: 'pendiente_pesaje', estatus: { fase_ciclo: 'PESAJE_RESPONDIDO', nombre: 'Consulta respondida' }, solicitud_preparacion: structuredClone(solicitudBase), consulta_cerrada: false, puede_cerrar_consulta: false, motivo_bloqueo_cierre: 'Confirma el estado o retira y sustituye las piezas reportadas antes de cerrar la consulta.', total_mercancia: 1000, saldo_a_favor: 0, costo_envio: 0, revision_registro: 1, revisiones_producto: structuredClone(filas), pagos_exhibicion: [], historial: [{ usuario: { id: 777, name: 'Auxiliar de ejemplo' } }], documentos: [
        { id: 1, tipo: 'pdf_pedido', nombre_original: 'pedido-ejemplo.svg', mime_type: 'image/svg+xml', url: imagen('Pedido de ejemplo'), activo: true },
        { id: 10, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', relacion_id: null, nombre_original: 'captura-conjunto.svg', mime_type: 'image/svg+xml', url: imagen('Captura del pedido'), activo: true },
        { id: 11, tipo: 'evidencia_condicion', relacion_tipo: 'revision_producto', relacion_id: 901, nombre_original: 'foto-pieza.svg', mime_type: 'image/svg+xml', url: imagen('Unidad revisada'), activo: true },
    ] };
    if (tipo === 'tienda') {
        pedido.almacen = { id: 2, nombre: 'Almacén PDV' };
        pedido.solicitud_preparacion.area_revision = 'preparacion_tienda'; pedido.solicitud_preparacion.caso_tienda = 'revisar_apartar';
        pedido.estatus_envio = null;
        pedido.tarea_preparacion = { id: 50, estado: 'RESPONDIDA', almacen: { id: 2, nombre: 'Almacén PDV' }, modalidad: { codigo: 'RECOGE_TIENDA' }, productos: [], respuesta: { respondida: true, respondida_at: solicitudBase.revision_disponibilidad.respondida_at, responsable: 'Auxiliar de Tienda', piezas_afectadas: true, cantidad: 2, revision: { estado: 'excepciones_pendientes' }, productos: filas.map(p => ({ ...p, descripcion: p.descripcion_producto, cantidad: p.cantidad_encontrada, observacion: p.comentario })), documentos: [{ id: 11, producto_id: 901, nombre_original: 'unidad.svg', mime_type: 'image/svg+xml', url: imagen('Unidad revisada') }, { id: 10, producto_id: null, nombre_original: 'conjunto.svg', mime_type: 'image/svg+xml', url: imagen('Captura del pedido') }] } };
    }
    envios = [];
}
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const params = new URL(req.url, proxy).searchParams; const tema = params.get('tema'); reiniciar(params.get('caso') || 'cedis');
            const pagina = structuredClone(paginaBase); pagina.component = 'ControlPedidos/Partials/FormularioRegistrarTienda'; pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario ficticio', email: 'qa@example.test', departamento_id: 1, roles: [], permissions: ['control_pedidos.crear'] };
            pagina.props.auth.webpush = { enabled: false };
            Object.assign(pagina.props, { abierto: true, pedido, catalogos: { departamentos: [{ id: 1, nombre: 'Ventas' }], sucursales: [{ id: 2, nombre: 'Carmen Cadena' }, { id: 3, nombre: 'City Center' }] }, registrarTienda: { almacenes_tienda: [{ id: 2, nombre: 'Almacén PDV' }] } });
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');document.documentElement.classList.add('glass-active');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));const routeOriginal=window.route;window.route=(...args)=>{const valor=routeOriginal(...args);if(typeof valor==='string'&&valor.startsWith('http')){const u=new URL(valor);return location.origin+u.pathname+u.search}return valor};</script>`;
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', `${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.startsWith('/build/')) { const r=await fetch(`${origen}${req.url}`);res.writeHead(r.status,{'Content-Type':r.headers.get('content-type')||'application/octet-stream'});res.end(Buffer.from(await r.arrayBuffer()));return; }
        if (req.method !== 'GET') {
            const chunks=[];for await(const c of req)chunks.push(c);const body=Buffer.concat(chunks);const ct=req.headers['content-type']||'';
            const datos = ct.includes('application/json') ? JSON.parse(body.toString()||'{}') : Object.fromEntries(await new Response(body,{headers:{'Content-Type':ct}}).formData());
            envios.push({url:req.url,datos});
            if(req.url.endsWith('/sucursal-destino')) {pedido.sucursal_destino_id=Number(datos.sucursal_destino_id);pedido.sucursal_destino={id:pedido.sucursal_destino_id,nombre:pedido.sucursal_destino_id===3?'City Center':'Carmen Cadena'};}
            if(req.url.endsWith('/disponibilidad-tienda')) {const p=pedido.revisiones_producto.find(p=>p.id===Number(datos.revision_id));if(p)p.resolucion=datos.accion==='confirmar'?'aceptar_condicion':datos.accion==='retirar'?'retirar':'sustituir';pedido.motivo_bloqueo_cierre='Respuesta actualizada: falta resolver la otra unidad.';}
            res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify(pedido));return;
        }
        res.writeHead(200,{'Content-Type':'application/json'});
        if(req.url.includes('/productos/buscar'))res.end(JSON.stringify({data:[{id:100,sku:'NUEVO-SKU',descripcion:'Alternativa de catálogo'}],current_page:1,last_page:1}));
        else if(req.url.endsWith('/disponibilidad-tienda'))res.end(JSON.stringify(pedido));
        else if(req.url==='/qa-envios')res.end(JSON.stringify(envios));
        else res.end('{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}');
    } catch(e){res.writeHead(500);res.end(String(e));}
});
await new Promise(resolve=>servidor.listen(9192,'0.0.0.0',resolve));
let sessionId;const reporte=[];
async function comando(path,body,method='POST'){const r=await fetch(`${selenium}/session${sessionId?`/${sessionId}`:''}${path}`,{method,...(body?{headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}:{})});const d=await r.json();if(d.value?.error)throw Error(JSON.stringify(d.value));return d.value;}
const ejecutar=script=>comando('/execute/sync',{script,args:[]});
async function esperar(condicion){const ok=await comando('/execute/async',{script:`const done=arguments[arguments.length-1];let n=0;function ver(){if(${condicion})done(true);else if(++n>120)done(false);else setTimeout(ver,100)}ver();`,args:[]});if(!ok)throw Error(`No se cumplió ${condicion}`);}
async function click(texto,scope='document'){await ejecutar(`const b=[...${scope}.querySelectorAll('button')].find(b=>b.textContent.trim()===${JSON.stringify(texto)});if(!b)throw Error('Botón ausente: '+${JSON.stringify(texto)});b.click()`);}
async function captura(nombre,selector){if(selector)await ejecutar(`document.querySelector(${JSON.stringify(selector)}).scrollIntoView({block:'start'})`);await esperar('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');const revision=await ejecutar(`return {errores:window.qaErrores,desbordes:[...document.querySelectorAll('[role=dialog],.tienda-respuesta,.tienda-ajustes,.tienda-ajustes-captura')].filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.className),rotas:[...document.images].filter(i=>i.complete&&!i.naturalWidth).length}`);if(revision.errores.length||revision.desbordes.length||revision.rotas)throw Error(JSON.stringify(revision));fs.writeFileSync(`${destino}/${nombre}.png`,Buffer.from(await comando('/screenshot',null,'GET'),'base64'));return revision;}
try {
    const sesion=await comando('',{capabilities:{alwaysMatch:{browserName:'chrome','se:name':'gelia-respuestas-retroalimentacion-qa','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']}}}});sessionId=sesion.sessionId;
    for(const vista of [{nombre:'escritorio-dark',width:1440,height:1000,tema:'dark'},{nombre:'modal-dark',width:544,height:932,tema:'dark'},{nombre:'movil-light',width:390,height:844,tema:'light'}]) {
        await comando('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width:vista.width,height:vista.height,deviceScaleFactor:1,mobile:vista.width<600}});
        await comando('/url',{url:`${proxy}/qa?tema=${vista.tema}&caso=cedis`});
        await esperar('Boolean(document.querySelector(".tienda-respuesta"))');
        await esperar('document.body.textContent.includes("Respondió Auxiliar de ejemplo")');
        await captura(`${vista.nombre}-respuesta`,'.tienda-respuesta');
        await ejecutar("document.querySelectorAll('.tienda-respuesta-fila')[1].querySelector('summary').scrollIntoView({block:'center'})");
        const abrirMenu = await comando('/element', {using:'css selector',value:'.tienda-respuesta-fila:nth-child(3) summary'});
        await comando(`/element/${abrirMenu['element-6066-11e4-a52e-4f735466cecf']}/click`, {});
        if(await ejecutar("return document.querySelectorAll('.tienda-respuesta-fila')[1].textContent.includes('Confirmar estado')"))throw Error('El faltante ofrece confirmar');
        await captura(`${vista.nombre}-menu`);
        const menuVisible = await ejecutar("const m=document.querySelectorAll('.tienda-respuesta-fila')[1].querySelector('.tienda-respuesta-menu-lista').getBoundingClientRect(),b=document.querySelector('.registrar-tienda-cuerpo').getBoundingClientRect();return {top:m.top,bottom:m.bottom,limiteSuperior:b.top,limiteInferior:b.bottom}");
        if(menuVisible.top < menuVisible.limiteSuperior || menuVisible.bottom > menuVisible.limiteInferior) throw Error(JSON.stringify(menuVisible));
        const sustituir = await comando('/element', {using:'css selector',value:'.tienda-respuesta-fila:nth-child(3) .tienda-respuesta-menu-lista button:last-child'});
        await comando(`/element/${sustituir['element-6066-11e4-a52e-4f735466cecf']}/click`, {});
        await click('Por captura o PDF');
        await captura(`${vista.nombre}-sustitucion`,'.tienda-accion-formulario');
        await click('Volver');
        await click('Modificar pedido');
        await esperar('Boolean(document.querySelector(".tienda-ajustes"))');
        await captura(`${vista.nombre}-agregar-documento`,'.tienda-ajustes');
        await click('SKU / nombre',"document.querySelector('.tienda-ajustes')");
        await captura(`${vista.nombre}-agregar-catalogo`,'.tienda-ajustes');
        await click('Retirar piezas',"document.querySelector('.tienda-ajustes')");
        await click('PDF / imágenes',"document.querySelector('.tienda-ajustes')");
        await captura(`${vista.nombre}-retirar-documento`,'.tienda-ajustes');
        await click('SKU / nombre',"document.querySelector('.tienda-ajustes')");
        await captura(`${vista.nombre}-retirar-catalogo`,'.tienda-ajustes');
        await captura(`${vista.nombre}-destino`,'.tienda-destino-confirmacion');
        if(await ejecutar("return Boolean(document.querySelector('.tienda-destino-confirmacion select'))"))throw Error('Destino repetido en selector abierto');
        await click('Cambiar destino');
        await ejecutar("const s=document.querySelector('.tienda-destino-confirmacion select');s.value='3';s.dispatchEvent(new Event('change',{bubbles:true}))");
        await click('Guardar sucursal de destino');
        await esperar('document.querySelector(".tienda-destino-confirmacion").textContent.includes("City Center")');
        if(envios.filter(e=>e.url.endsWith('/sucursal-destino')).length!==1)throw Error('Destino no guardado una sola vez');
        await ejecutar("document.querySelectorAll('.tienda-respuesta-fila')[1].querySelector('summary').click()");
        await click('Retirar',"document.querySelectorAll('.tienda-respuesta-fila')[1]");
        await click('Confirmar retiro');
        await esperar('document.body.textContent.includes("Retiro registrado")');
        const accion=envios.find(e=>e.url.endsWith('/disponibilidad-tienda'));
        if(accion?.datos.revision_id!=='902')throw Error(JSON.stringify({accion}));
        reporte.push({...vista,...await captura(`${vista.nombre}-respuesta-actualizada`,'.tienda-respuesta'),cedis:true,idUnidad:'902',destinoGuardado:true,formularios:4});
    }
    for (const vista of [{nombre:'escritorio-dark',width:1440,height:1000,tema:'dark'},{nombre:'movil-light',width:390,height:844,tema:'light'}]) {
        await comando('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width:vista.width,height:vista.height,deviceScaleFactor:1,mobile:vista.width<600}});
        await comando('/url',{url:`${proxy}/qa?tema=${vista.tema}&caso=tienda`});
        await esperar('document.body.textContent.includes("Respondió Auxiliar de Tienda")');
        if(await ejecutar('return Boolean(document.querySelector(".tienda-destino-confirmacion .gelia-cedis-ruta,.tienda-destino-confirmacion select"))'))throw Error('Tienda muestra ruta de CEDIS');
        reporte.push({...vista,tienda:true,...await captura(`tienda-${vista.nombre}`,'.tienda-respuesta'),rutaCedis:false});
    }
    fs.writeFileSync(`${destino}/verificacion.json`,JSON.stringify(reporte,null,2));console.log(JSON.stringify({destino,resultados:reporte}));
} catch(e){console.error(await ejecutar('return {texto:document.body.innerText.slice(-9000),errores:window.qaErrores}').catch(()=>({})));throw e;}
finally{if(sessionId)await comando('',null,'DELETE').catch(()=>{});await new Promise(resolve=>servidor.close(resolve));}
