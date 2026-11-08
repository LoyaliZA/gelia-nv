// Build real con datos y transporte ficticios; nunca guarda configuración operativa real.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9193';
const selenium = 'http://selenium:4444';
const destino = '/tmp/gelia-politicas-operativas-qa';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw Error('Selenium está ocupado.');
let pagina, guardado;
function reiniciar(tema) {
    guardado = null;
    pagina = structuredClone(paginaBase);
    pagina.component = 'Admin/ConfiguracionOperativa/Index'; pagina.url = '/admin/configuracion-operativa';
    Object.assign(pagina.props, {
        auth: { user: { id: 99999, name: 'Usuario de ejemplo', roles: [], permissions: ['configuracion_sistema.gestionar'] }, webpush: { enabled: false }, tema_visual: { modo: tema } },
        tienda: { md_dias: [1,2,3,4,5], md_corte: '13:00', apartado_dias: [1,2,3,4,5,6], apartado_cierre: '17:00', sabado_apertura: '09:00', sabado_cierre: '13:00', recoleccion_dias: 20, seguimiento_entrega_pdv: false },
        general: { zona_horaria: 'America/Mexico_City', recordatorio_hora: '11:00', cierre_operativo: '20:00', dias_resguardo: 3, dias_prorroga_vip: 1 },
        plazos: { activo: true, hora_corte: '18:00', dias_habiles: [1,2,3,4,5,6], temporada_alta: false, dias_extra_temporada_alta: 1, comercial: { dias_empaque: 1, dias_recoleccion: 1 }, local_regional: { dias_empaque: 1, dias_recoleccion: 1 } },
        bancos: [
            { id: 1, nombre: 'BBVA JOSE WELLSTER', activo: true, habilitado: true, compartido: false, departamento_ids: [3] },
            { id: 2, nombre: 'BBVA HERMANOS VILLAREAL', activo: true, habilitado: true, compartido: false, departamento_ids: [3,4] },
            { id: 3, nombre: 'BANORTE WELLSTER', activo: true, habilitado: true, compartido: false, departamento_ids: [4] },
            { id: 4, nombre: 'BANORTE SCA', activo: true, habilitado: false, compartido: false, departamento_ids: [] },
        ],
        departamentos: [{ id: 3, nombre: 'Bellaroma', activo: true }, { id: 4, nombre: 'Aromas', activo: true }],
        prioridades: ['mismo_dia', 'municipal', 'tienda', 'resguardo', 'nacional'],
        tipos_prioridad: { mismo_dia: 'Mismo día', municipal: 'Municipales', tienda: 'Recoge en tienda', resguardo: 'Resguardos', nacional: 'Envíos nacionales' },
    });
}
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const tema = new URL(req.url, proxy).searchParams.get('tema'); reiniciar(tema);
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');localStorage.setItem('theme_sidebar_mode','collapsed');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
            const html = htmlBase.replace(/(const Ziggy=\{"url":)"[^"]+"/, (_, a) => a + JSON.stringify(proxy)).replace('</head>', `${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.startsWith('/build/')) {
            const recurso = await fetch(`${origen}${req.url}`); res.writeHead(recurso.status, { 'Content-Type': recurso.headers.get('content-type') || 'application/octet-stream' }); res.end(Buffer.from(await recurso.arrayBuffer())); return;
        }
        if (req.method === 'PUT' && req.url === '/admin/configuracion-operativa') {
            let cuerpo = ''; for await (const chunk of req) cuerpo += chunk;
            guardado = JSON.parse(cuerpo);
            pagina.props.flash = { success: 'Configuración operativa guardada.' };
            res.writeHead(200, { 'Content-Type': 'application/json', 'X-Inertia': 'true' }); res.end(JSON.stringify(pagina)); return;
        }
        res.writeHead(200, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ data: [], conversaciones: [], notificaciones: [], unread_count: 0 }));
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(resolve => servidor.listen(9193, '0.0.0.0', resolve));
let sessionId;
async function comando(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const data = await r.json(); if (data.value?.error) throw Error(JSON.stringify(data.value)); return data.value;
}
const ejecutar = script => comando('/execute/sync', { script, args: [] });
async function esperar(condicion) {
    const ok = await comando('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function revisar(){if(${condicion})done(true);else if(++n>100)done(false);else setTimeout(revisar,100)}revisar();`, args: [] });
    if (!ok) throw Error(`No se cumplió: ${condicion}; ${JSON.stringify(await ejecutar('return {texto:document.body.innerText.slice(-400),errores:window.qaErrores}'))}`);
}
async function captura(nombre) {
    await esperar('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');
    fs.writeFileSync(`${destino}/${nombre}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
}
const reporte = [];
try {
    sessionId = (await comando('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } })).sessionId;
    for (const size of [{ nombre: 'escritorio', width: 1440, height: 1000 }, { nombre: 'movil', width: 390, height: 844 }]) {
        for (const tema of ['light', 'dark']) {
            await comando('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: size.width, height: size.height, deviceScaleFactor: 1, mobile: size.width < 600 } });
            await comando('/url', { url: `${proxy}/qa?tema=${tema}` });
            await esperar(`!!document.querySelector('[aria-label="Subir Municipales"]')`);
            await ejecutar(`document.querySelector('form section').scrollIntoView({block:'start'});window.scrollBy(0,-65)`);
            await captura(`${size.nombre}-${tema}-bancos`);
            await ejecutar(`document.querySelector('form section input').click()`);
            await esperar(`!document.querySelector('form section input').checked`);
            await ejecutar(`document.querySelector('[aria-label="Subir Municipales"]').click();document.querySelector('[aria-label="Orden de atención"]').closest('section').scrollIntoView({block:'center'})`);
            await esperar(`document.querySelector('[aria-label="Orden de atención"] li').textContent.includes('Municipales')`);
            await captura(`${size.nombre}-${tema}-prioridades`);
            await ejecutar(`document.querySelector('form').requestSubmit()`);
            await esperar(`document.body.innerText.includes('Configuración operativa guardada.')`);
            if (!guardado || guardado.bancos[0].habilitado !== false || guardado.prioridades[0] !== 'municipal' || guardado.bancos[1].departamento_ids.join(',') !== '3,4') throw Error('El payload no conserva bancos, departamentos y orden');
            const revision = await ejecutar(`return {errores:window.qaErrores,desborde:document.documentElement.scrollWidth>innerWidth+1,secciones:[...document.querySelectorAll('form section')].filter(el=>el.scrollWidth>el.clientWidth+1).length}`);
            if (revision.errores.length || revision.desborde || revision.secciones) throw Error(JSON.stringify(revision));
            reporte.push({ ...size, tema, ...revision, envio_ficticio_verificado: true });
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ destino, vistas: reporte.length, capturas: reporte.length * 2 }));
} finally {
    if (sessionId) await comando('', null, 'DELETE').catch(() => {});
    await new Promise(resolve => servidor.close(resolve));
}
