// Página y assets reales; usuario, listado y transporte ficticios. No escribe en la base operativa.
import fs from 'node:fs';
import http from 'node:http';

const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9194';
const selenium = 'http://selenium:4444';
const salida = '/tmp/gelia-nuevo-pedido-qa';
fs.mkdirSync(salida, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const borrador = { usuario_id: 999999, pedido_id: 900001, cliente_id: 123, folio_remision: 'BORRADOR-FICTICIO' };
const pedidos = { data: [{ id: 900001, folio: 'PBMA-QA', folio_remision: 'BORRADOR-FICTICIO', cliente_id: 123, cliente: { nombre: 'Cliente ficticio', numero_cliente: 'QA-123' }, estatus: { fase_ciclo: 'BORRADOR', nombre: 'Borrador' }, solicitud_preparacion: { modalidad_entrega: 'tienda' }, total: 0 }], current_page: 1, last_page: 1, total: 1, per_page: 20 };
const escrituras = [];
const assert = (ok, mensaje) => { if (!ok) throw Error(mensaje); };
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const tema = new URL(req.url, proxy).searchParams.get('tema');
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Index'; pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de prueba', email: 'qa@example.test', departamento_id: 1, roles: [], permissions: ['control_pedidos.crear', 'control_pedidos.editar'] };
            Object.assign(pagina.props, { pedidos, filtros: {}, metricas: {}, flash: {}, catalogos: { departamentos: [{ id: 1, nombre: 'Ventas' }], almacenes: [], paqueterias: [] }, registrar_tienda: { almacenes_tienda: [] } });
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('control_pedidos.borrador_pedido_v2',${JSON.stringify(JSON.stringify(borrador))});window.qaErrores=[];addEventListener('error',e=>qaErrores.push(e.message));addEventListener('unhandledrejection',e=>qaErrores.push(String(e.reason)));const original=window.route;window.route=(...args)=>{const v=original(...args);if(typeof v==='string'&&v.startsWith('http')){const u=new URL(v);return location.origin+u.pathname+u.search}return v};</script>`;
            const html = htmlBase.replace('</head>', init + '</head>').replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.startsWith('/build/')) {
            const r = await fetch(`${origen}${req.url}`);
            res.writeHead(r.status, { 'Content-Type': r.headers.get('content-type') || 'application/octet-stream' });
            res.end(Buffer.from(await r.arrayBuffer())); return;
        }
        if (req.method !== 'GET') escrituras.push({ metodo: req.method, url: req.url });
        res.writeHead(200, { 'Content-Type': 'application/json' });
        if (req.url.includes('/listado')) res.end(JSON.stringify({ pedidos, metricas: {} }));
        else if (req.url.endsWith('/disponibilidad-tienda')) res.end(JSON.stringify(pedidos.data[0]));
        else res.end('{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}');
    } catch (error) { res.writeHead(500); res.end(String(error)); }
});
await new Promise(resolve => servidor.listen(9194, '0.0.0.0', resolve));
let id;
async function cmd(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${id ? '/' + id : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const value = (await r.json()).value;
    if (value?.error) throw Error(JSON.stringify(value));
    return value;
}
const js = (script, args = []) => cmd('/execute/sync', { script, args });
async function esperar(condicion) {
    assert(await cmd('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function ver(){try{if(${condicion})return done(true)}catch{}if(++n>100)return done(false);setTimeout(ver,100)}ver()`, args: [] }), `No se cumplió: ${condicion}`);
}
const click = texto => js(`const b=[...document.querySelectorAll('button')].find(b=>b.textContent.trim()===arguments[0]);if(!b)throw Error('Botón ausente: '+arguments[0]);b.focus();b.click()`, [texto]);
const reporte = [];
try {
    const estado = (await (await fetch(`${selenium}/status`)).json()).value;
    assert(!estado.nodes.some(n => n.slots.some(s => s.session)), 'Selenium ocupado');
    id = (await cmd('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] }, 'goog:loggingPrefs': { browser: 'ALL' } } } })).sessionId;
    for (const ancho of [1440, 1280, 1024, 390, 375]) {
        for (const tema of ['light', 'dark']) {
            await cmd('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: ancho, height: ancho < 600 ? 844 : 1000, deviceScaleFactor: 1, mobile: ancho < 600 } });
            await cmd('/url', { url: `${proxy}/qa?tema=${tema}` });
            await esperar(`document.body.innerText.includes('Gestión de pedidos') && [...document.querySelectorAll('button')].some(b=>b.textContent.trim()==='Nuevo pedido')`);
            await click('Nuevo pedido');
            await esperar(`!!document.querySelector('[aria-label="Cerrar selección de modalidad"]')`);
            assert(await js(`return !document.body.innerText.includes('Borrador en curso') && document.querySelectorAll('[role=dialog]').length===1 && ![...document.querySelector('[role=dialog]').querySelectorAll('button')].some(b=>/^(Continuar|Siguiente)$/.test(b.textContent.trim()))`), 'Entrada con aviso o paso adicional');
            await esperar('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');
            const medidas = await js(`const d=document.querySelector('[role=dialog]');return {pagina:document.documentElement.scrollWidth<=innerWidth,modal:d.scrollWidth<=d.clientWidth,tarjetas:[...d.querySelectorAll('button')].map(b=>({ancho:b.getBoundingClientRect().width,alto:b.getBoundingClientRect().height}))}`);
            assert(medidas.pagina && medidas.modal && medidas.tarjetas.every(b => b.ancho >= 44 && b.alto >= 44), 'Desbordamiento u objetivos pequeños');
            fs.writeFileSync(`${salida}/selector-${ancho}-${tema}.png`, Buffer.from(await cmd('/screenshot', null, 'GET'), 'base64'));
            await cmd('/actions', { actions: [{ type: 'key', id: 'teclado', actions: [{ type: 'keyDown', value: '\uE004' }, { type: 'keyUp', value: '\uE004' }] }] });
            assert(await js(`return document.querySelector('[role=dialog]').contains(document.activeElement)`), 'Foco fuera del selector');
            await cmd('/actions', { actions: [{ type: 'key', id: 'teclado', actions: [{ type: 'keyDown', value: '\uE00C' }, { type: 'keyUp', value: '\uE00C' }] }] });
            await esperar('!document.querySelector("[role=dialog]")');
            assert(await js(`return document.activeElement.textContent.trim()==='Nuevo pedido'`), 'Foco sin restaurar');
            for (const [valor, titulo] of [['tienda', 'Tienda'], ['municipal', 'Envío municipal'], ['nacional', 'Envío nacional']]) {
                await click('Nuevo pedido');
                await esperar(`!!document.querySelector('[aria-label="Cerrar selección de modalidad"]')`);
                await js(`[...document.querySelectorAll('[role=dialog] button')].find(b=>b.textContent.trim().startsWith(arguments[0])).click()`, [titulo]);
                await esperar(`document.querySelector('#modalidad-tienda')?.value===${JSON.stringify(valor)}`);
                assert(await js(`return document.querySelectorAll('[role=dialog]').length===1 && !document.querySelector('[aria-label="Cerrar selección de modalidad"]') && !document.querySelector('#folio-tienda').value`), 'Modal apilado o captura anterior mezclada');
                if (ancho === 1280 || ancho === 375) fs.writeFileSync(`${salida}/formulario-${valor}-${ancho}-${tema}.png`, Buffer.from(await cmd('/screenshot', null, 'GET'), 'base64'));
                await js(`document.querySelector('[aria-label="Cerrar registro"]').click()`);
                await esperar('!document.querySelector("#modalidad-tienda")');
            }
            assert(await js(`return localStorage.getItem('control_pedidos.borrador_pedido_v2')===arguments[0]`, [JSON.stringify(borrador)]), 'Borrador local modificado');
            const errores = await js('return window.qaErrores');
            assert(!errores.length, JSON.stringify(errores));
            reporte.push({ ancho, tema, medidas, errores, modalidades: ['tienda', 'municipal', 'nacional'], borradorConservado: true, teclado: true });
        }
    }
    assert(!escrituras.some(e => e.url.startsWith('/control-pedidos') && !e.url.includes('/listado')), JSON.stringify(escrituras));
    console.log(JSON.stringify({ salida, variantes: reporte.length, escriturasPedidos: 0 }));
} finally {
    fs.writeFileSync(`${salida}/verificacion.json`, JSON.stringify({ reporte, escrituras }, null, 2));
    if (id) await cmd('', null, 'DELETE');
    await new Promise(resolve => servidor.close(resolve));
}
