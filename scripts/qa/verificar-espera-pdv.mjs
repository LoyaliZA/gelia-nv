// Pantalla real del build con datos ficticios; ninguna petición modifica la operación.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test', proxy = 'http://laravel.test:9194', selenium = 'http://selenium:4444';
const destino = process.env.QA_PDV_DIR || '/tmp/gelia-pdv-espera-qa';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
let fase = 0;
function estado() {
    const apertura = '2026-11-07T15:00:00Z';
    const personas = [
        { id: 3, nombre: 'Zeta Ejemplo', estado_vendedor: fase ? 'atendiendo' : 'en_retencion', jornada: { id: 1, apertura_at: apertura } },
        { id: 1, nombre: 'Ana Ejemplo', estado_vendedor: fase ? 'en_espera' : 'atendiendo', jornada: { id: 2, apertura_at: apertura } },
        { id: 2, nombre: 'Bruno Ejemplo', estado_vendedor: fase ? 'en_retencion' : 'disponible', jornada: { id: 3, apertura_at: '2026-11-07T15:01:00Z' } },
    ].map(p => ({ ...p, acciones: [], recibe_turnos: p.estado_vendedor === 'disponible',
        cronometro: p.estado_vendedor === 'en_espera' ? { etiqueta: 'Espera para el siguiente turno', modo: 'restante', referencia_at: new Date(Date.now() + 20000).toISOString() } : null }));
    return { servidor_at: new Date().toISOString(), equipo: personas.reverse(), resumen: { configurados: 3, activos_hoy: 3, atendiendo: 1, pre_disponibles: fase ? 1 : 0, disponibles: fase ? 0 : 1, en_pausa: 1 }, clientes_en_fila: 2, reatencion: [] };
}
const peticiones = [];
const servidor = http.createServer(async (req, res) => {
    if (!req.url.startsWith('/build/')) peticiones.push(req.url);
    try {
        if (req.url.startsWith('/qa?')) {
            const tema = new URL(req.url, proxy).searchParams.get('tema');
            const pagina = structuredClone(paginaBase);
            pagina.component = 'PuntoVenta/Operacion/OperacionGeneral';
            pagina.url = '/punto-venta/operacion';
            Object.assign(pagina.props, { estado: estado(), permisos: { equipo_ver: true, equipo_gestionar: true }, reatencion: [], motivos_pausa: [], sucursales_asignadas: [] });
            Object.assign(pagina.props.auth, { user: { id: 999, name: 'Revisión ficticia', email: 'qa@example.test', roles: [], permissions: [] }, webpush: { enabled: false }, tema_visual: { modo: tema, color_nombre: 'azul', fuente_principal: 'inter', efecto_cristal: false, layout_sidebar: 'professional_left', layout_sidebar_mobile: 'mobile_topbar' } });
            const init = `<script>localStorage.clear();localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','false');localStorage.setItem('theme_layout','professional_left');localStorage.setItem('theme_layout_mobile','mobile_topbar');window.qaErrores=[];window.addEventListener('error',e=>qaErrores.push(e.error?.stack || e.message));window.addEventListener('unhandledrejection',e=>qaErrores.push(String(e.reason)));const qaRoute=window.route;window.route=(...args)=>{const v=qaRoute(...args);if(typeof v==='string'&&v.startsWith('http')){const u=new URL(v);return location.origin+u.pathname+u.search}return v};</script>`;
            const html = htmlBase.replace('</head>', init + '</head>').replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.startsWith('/build/')) {
            const r = await fetch(origen + req.url); res.writeHead(r.status, { 'Content-Type': r.headers.get('content-type') }); res.end(Buffer.from(await r.arrayBuffer())); return;
        }
        res.setHeader('Content-Type', 'application/json');
        if (req.url === '/qa/fase') { fase = 1; res.end('{}'); return; }
        if (req.url.includes('/operacion/datos')) { res.end(JSON.stringify(estado())); return; }
        res.end('{"data":[],"conversaciones":[],"usuarios":[],"notificaciones":[],"unread_count":0}');
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(r => servidor.listen(9194, '0.0.0.0', r));
let sessionId;
async function cmd(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const d = await r.json(); if (d.value?.error) throw Error(JSON.stringify(d.value)); return d.value;
}
const js = script => cmd('/execute/sync', { script, args: [] });
async function esperar(condicion) {
    const ok = await cmd('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function f(){if(${condicion})done(true);else if(++n>80)done(false);else setTimeout(f,100)}f()`, args: [] });
    if (!ok) throw Error(condicion);
}
const orden = `return [...document.querySelectorAll('section[aria-labelledby="equipo-activos-hoy"] article')].map(e=>e.querySelector('p').textContent)`;
const resultados = [];
try {
    sessionId = (await cmd('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } })).sessionId;
    for (const vista of [{ nombre: 'escritorio', width: 1440, height: 1000, tema: 'dark' }, { nombre: 'movil', width: 390, height: 844, tema: 'light' }]) {
        fase = 0;
        await cmd('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: vista.width, height: vista.height, deviceScaleFactor: 1, mobile: vista.width < 600 } });
        await cmd('/url', { url: `${proxy}/qa?tema=${vista.tema}` });
        await esperar(`document.querySelectorAll('section[aria-labelledby="equipo-activos-hoy"] article').length===3`);
        const antes = await js(orden);
        await js(`fetch('/qa/fase').then(()=>[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Actualizar')).click())`);
        await esperar(`document.querySelector('[aria-label="Estado: Pre-disponible"]')`);
        const despues = await js(orden);
        if (JSON.stringify(antes) !== JSON.stringify(['Zeta Ejemplo', 'Ana Ejemplo', 'Bruno Ejemplo']) || JSON.stringify(antes) !== JSON.stringify(despues)) throw Error(JSON.stringify({ antes, despues }));
        const revision = await js(`return {errores:window.qaErrores, desborde:document.documentElement.scrollWidth>innerWidth+1, cuenta:document.body.innerText.includes('Volverá a la cola')}`);
        if (revision.errores.length || revision.desborde || !revision.cuenta) throw Error(JSON.stringify(revision));
        if (vista.width < 600) await js(`document.querySelector('section[aria-labelledby="equipo-activos-hoy"]').scrollIntoView()`);
        await new Promise(r => setTimeout(r, 1100));
        fs.writeFileSync(`${destino}/${vista.nombre}.png`, Buffer.from(await cmd('/screenshot', null, 'GET'), 'base64'));
        resultados.push({ ...vista, antes, despues, ...revision });
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(resultados, null, 2));
    console.log(JSON.stringify({ destino, resultados }));
} catch (e) { console.error(peticiones); console.error(await js('return {texto:document.body.innerText.slice(-1600),errores:window.qaErrores}').catch(() => ({}))); throw e; }
finally { if (sessionId) await cmd('', null, 'DELETE').catch(() => {}); await new Promise(r => servidor.close(r)); }
