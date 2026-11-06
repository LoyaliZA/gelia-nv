// Build real y Selenium con documentos, catálogo y transporte ficticios. No escribe pedidos.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const soloCatalogo = process.env.QA_SOLO_CATALOGO === '1';
const soloAtributos = process.env.QA_SOLO_ATRIBUTOS === '1';
const destino = soloAtributos ? '/tmp/gelia-cedis-atributos-envio-qa' : soloCatalogo ? '/tmp/gelia-cedis-catalogo-cristal-qa' : '/tmp/gelia-cedis-unidades-qa';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const productos = [1, 2, 3, 4].map(i => ({ id: i, sku: `SKU-${i}`, descripcion: `Perfume de ejemplo ${i} · presentación de catálogo`, codigo_barras: `75000000000${i}` }));
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="white"/><text x="35" y="60" font-size="26">PEDIDO DE EJEMPLO</text></svg>';
const documento = { id: 1, nombre_original: 'pedido-ejemplo.svg', mime_type: 'image/svg+xml', url: 'data:image/svg+xml,' + encodeURIComponent(svg) };
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const tema = new URL(req.url, proxy).searchParams.get('tema');
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Partials/PanelRespuestaDisponibilidad'; pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario ficticio', email: 'qa@example.test', roles: [], permissions: ['control_pedidos.cedis'] };
            pagina.props.auth.webpush = { enabled: false };
            Object.assign(pagina.props, { modo: 'dialogo', abierto: true, tomada: true, puedeResponder: true, pedido: { id: 99, folio_remision: '2', cliente: { nombre: 'Cliente ficticio de revisión' }, sucursal_destino: { id: 2, nombre: 'Carmen Cadena' } }, documentos: [documento] });
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');document.documentElement.classList.add('glass-active');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', `${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.includes('productos')) { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ data: productos, current_page: 1, last_page: 1 })); return; }
        if (!req.url.startsWith('/build/')) { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}'); return; }
        const recurso = await fetch(`${origen}${req.url}`); res.writeHead(recurso.status, { 'Content-Type': recurso.headers.get('content-type') || 'application/octet-stream' }); res.end(Buffer.from(await recurso.arrayBuffer()));
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(resolve => servidor.listen(9192, '0.0.0.0', resolve));
let sessionId;
const reporte = [];
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
    await esperar('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');
    fs.writeFileSync(`${destino}/${nombre}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
}
try {
    const s = await comando('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-cedis-captura-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }); sessionId = s.sessionId;
    for (const vista of [{ nombre: 'escritorio-dark', width: 1440, height: 1000, tema: 'dark' }, { nombre: 'modal-dark', width: 544, height: 932, tema: 'dark' }, { nombre: 'movil-light', width: 390, height: 844, tema: 'light' }, { nombre: 'movil-dark', width: 390, height: 844, tema: 'dark' }].filter(v => !soloAtributos || v.nombre === 'modal-dark' || v.nombre === 'movil-light')) {
        await comando('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: vista.width, height: vista.height, deviceScaleFactor: 1, mobile: vista.width < 600 } });
        await comando('/url', { url: `${proxy}/qa?tema=${vista.tema}` });
        await esperar('document.body.textContent.includes("Folio No. : 2")');
        await captura(`${vista.nombre}-inicio`);
        await ejecutar("[...document.querySelectorAll('input[type=radio]')][1].click()");
        await ejecutar("const original=window.route;window.route=(name,...args)=>name==='gestion_interna.productos.buscar'?location.origin+'/gestion-interna/productos/buscar':original(name,...args);document.querySelector('#cedis-buscar-producto').focus()");
        await esperar('document.querySelector(".selector-productos-lista")?.textContent.includes("SKU-1")');
        const fondoLista = await ejecutar(`const el=document.querySelector('.selector-productos-lista'),s=getComputedStyle(el),c=document.createElement('canvas');c.width=1;c.height=1;const ctx=c.getContext('2d');ctx.fillStyle=s.backgroundColor;ctx.fillRect(0,0,1,1);return {color:s.backgroundColor,alfa:ctx.getImageData(0,0,1,1).data[3],blur:s.backdropFilter,cristal:document.documentElement.classList.contains('glass-active')}`);
        if (fondoLista.alfa !== 255 || !fondoLista.cristal || fondoLista.blur !== 'none') throw Error(`Fondo no opaco con cristal: ${JSON.stringify(fondoLista)}`);
        await captura(`${vista.nombre}-catalogo`);
        if (soloCatalogo) { reporte.push({ ...vista, fondoLista }); continue; }
        await ejecutar("[...document.querySelectorAll('.selector-productos-lista button')][0].click()");
        await esperar('document.querySelectorAll(".gelia-cedis-producto").length===1');
        const inicial = await ejecutar('return {paddingIzquierdo:parseFloat(getComputedStyle(document.querySelector("#cedis-buscar-producto")).paddingLeft),textos:document.querySelectorAll("input[type=text]").length,antiguos:document.querySelectorAll(".gelia-cedis-producto select,.gelia-cedis-producto input[type=number]").length,activos:document.querySelectorAll(".gelia-cedis-producto [role=switch][aria-checked=true]").length,contraida:!document.querySelector(".gelia-cedis-producto details").open}');
        if (inicial.paddingIzquierdo < 44 || inicial.textos !== 1 || inicial.antiguos || inicial.activos || !inicial.contraida) throw Error(JSON.stringify(inicial));
        await ejecutar("document.querySelector('.gelia-cedis-producto summary').click()");
        await esperar('document.querySelector(".gelia-cedis-producto details").open');
        if (!soloAtributos) await ejecutar("const t=document.querySelector('textarea');Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype,'value').set.call(t,'Caja con defecto visible');t.dispatchEvent(new Event('input',{bubbles:true}));");
        await ejecutar("const btns=document.querySelectorAll('.gelia-cedis-producto [role=switch]');btns[0].click();btns[1].click();");
        await ejecutar("const input=document.querySelector('input[aria-label=\"Tomar foto: Fotos del producto\"]');input.click=()=>{window.qaCamaraActivada=true;};document.querySelector('[aria-label=\"Agregar evidencia del producto 1\"]').click()");
        if (!await ejecutar('return window.qaCamaraActivada')) throw Error('La cámara no activa la captura');
        await foto('Fotos del producto', 1, true);await foto('Fotos del producto', 1, true);await foto('Captura del pedido');
        await esperar('document.querySelectorAll(".gelia-pedidos-evidencia img").length===3');
        if (soloAtributos) {
            if (!await ejecutar("return document.querySelector('textarea').value === '' && document.body.textContent.includes('Observaciones (opcional)')")) throw Error('No quedó el escenario sin observaciones');
            await ejecutar("document.querySelector('.gelia-cedis-producto details').open=false");
            await esperar('!document.querySelector(".gelia-cedis-producto details").open');
            await click('Enviar respuesta');
            await esperar('document.body.textContent.includes("Consulta respondida")');
            await ejecutar("document.querySelector('.gelia-cedis-disponibilidad').scrollIntoView({block:'end'})");
            await captura(`${vista.nombre}-envio-atributos`);
            const errores = await ejecutar('return window.qaErrores');
            if (errores.length) throw Error(JSON.stringify(errores));
            reporte.push({ ...vista, observaciones: '', unicaPieza: true, mejorEjemplar: true, filaContraidaAlEnviar: true, envioSimuladoAceptado: true, errores });
            continue;
        }
        await ejecutar("document.querySelector('.gelia-cedis-producto').scrollIntoView({block:'start'})");
        await captura(`${vista.nombre}-producto`);
        await foto('Fotos del producto', 4, true);
        await esperar('document.querySelectorAll(".gelia-cedis-producto .gelia-pedidos-evidencia").length===5');
        if (!await ejecutar(`return document.querySelector('[aria-label="Agregar evidencia del producto 1"]').disabled`)) throw Error('No se limitó a cinco fotos');
        for (const codigo of ['SKU-1', 'SKU-2', 'SKU-3']) {
            await ejecutar(`const t=document.querySelector('#cedis-buscar-producto');Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(t,${JSON.stringify(codigo)});t.dispatchEvent(new Event('input',{bubbles:true}));`);
            await ejecutar("document.querySelector('#cedis-buscar-producto').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));");
        }
        await esperar('document.querySelectorAll(".gelia-cedis-producto").length===4');
        await ejecutar("document.querySelectorAll('.gelia-cedis-producto')[1].querySelector('[role=switch][aria-label=\"Sin existencias\"]').click()");
        await esperar('!document.querySelectorAll(".gelia-cedis-producto")[1].querySelector("textarea")');
        await ejecutar("document.querySelector('[aria-label=\"Quitar pieza 4\"]').click()");
        await esperar('document.querySelectorAll(".gelia-cedis-producto").length===3');
        await ejecutar("const t=document.querySelector('#cedis-buscar-producto');Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(t,'SKU-3');t.dispatchEvent(new Event('input',{bubbles:true}));");
        await ejecutar("document.querySelector('#cedis-buscar-producto').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));");
        await esperar('document.querySelectorAll(".gelia-cedis-producto").length===4');
        await ejecutar("document.querySelectorAll('.gelia-cedis-producto details').forEach(d=>{d.open=false});document.querySelector('.gelia-cedis-producto').scrollIntoView({block:'start'});document.querySelector('#cedis-buscar-producto').blur();");
        await esperar('[...document.querySelectorAll(".gelia-cedis-producto details")].every(d=>!d.open)');
        const etiquetas = await ejecutar('return [...document.querySelectorAll(".gelia-cedis-unidad")].map(e=>e.textContent)');
        if (etiquetas.join() !== '1/2,2/2') throw Error(JSON.stringify(etiquetas));
        await captura(`${vista.nombre}-lista`);
        await ejecutar("document.querySelector('.gelia-preparacion-footer button').scrollIntoView({block:'end'})");
        const pie = await ejecutar("const b=document.querySelector('.gelia-preparacion-footer button').getBoundingClientRect();return {visible:b.top>=0&&b.bottom<=innerHeight,altoAviso:document.querySelector('.gelia-cedis-alerta').getBoundingClientRect().height}");
        if(!pie.visible || pie.altoAviso > 120) throw Error(JSON.stringify(pie));
        await captura(`${vista.nombre}-captura-y-aviso`);
        await click('Ver pedido'); await esperar('Boolean(document.querySelector("[aria-label=\\"Visor de evidencia\\"]"))');
        await captura(`${vista.nombre}-pedido`);
        await ejecutar("[...document.querySelectorAll('[aria-label=\"Cerrar vista de la foto\"]')][0].click()");
        await esperar('!document.querySelector("[aria-label=\\"Visor de evidencia\\"]")');
        const revision = await ejecutar(`return {errores:window.qaErrores,desbordes:[...document.querySelectorAll('[role=dialog],.gelia-preparacion-captura,.gelia-cedis-panel')].filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.className),imagenesRotas:[...document.images].filter(i=>i.complete&&!i.naturalWidth).length,volver:document.body.textContent.includes('Volver a la revisión'),pedidoInline:Boolean(document.querySelector('#cedis-documento-referencia'))};`);
        if (revision.errores.length || revision.desbordes.length || revision.imagenesRotas || revision.volver || revision.pedidoInline) throw Error(JSON.stringify(revision));
        reporte.push({ ...vista, ...revision, fondoLista, productos: 4, fichasPlegadas: true, fotosSeparadas: true, unidadesIndependientes: true, limiteCincoFotos: true, retiroRapido: true, campoUnico: inicial.textos === 1, pie });
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({destino,capturas:reporte.length*(soloAtributos ? 3 : soloCatalogo ? 2 : 6),resultados:reporte}));
} catch (e) {
    console.error(await ejecutar('return {texto:document.body.innerText,errores:window.qaErrores}').catch(()=>({})));
    throw e;
} finally {
    if (sessionId) await comando('', null, 'DELETE').catch(()=>{});
    await new Promise(resolve => servidor.close(resolve));
}
