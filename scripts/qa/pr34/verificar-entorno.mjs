// Lectura del login real y de Selenium. No autentica ni envía formularios.
import fs from 'node:fs';
const grid = 'http://selenium:4444';
const destino = 'resources/design-references/pr34-validacion-local/entorno.json';
const estado = (await (await fetch(`${grid}/status`)).json()).value;
if (estado.nodes.some(n => n.slots.some(s => s.session))) throw Error('Selenium ocupado');
let sessionId;
async function cmd(path, body, method = 'POST') {
    const r = await fetch(`${grid}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const d = await r.json(); if (d.value?.error) throw Error(JSON.stringify(d.value)); return d.value;
}
try {
    sessionId = (await cmd('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } })).sessionId;
    await cmd('/url', { url: 'http://laravel.test/login' });
    const listo = await cmd('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function ver(){if(document.querySelector('input[type="password"]'))done(true);else if(++n>100)done(false);else setTimeout(ver,100)}ver()`, args: [] });
    const datos = await cmd('/execute/sync', { script: `return {url:location.href,login_con_password:!!document.querySelector('input[type="password"]'),assets_build:performance.getEntriesByType('resource').filter(r=>r.name.includes('/build/')).map(r=>({ruta:new URL(r.name).pathname,status:r.responseStatus})),autenticado:false}`, args: [] });
    if (!listo || !datos.assets_build.length || datos.assets_build.some(r=>r.status>=400)) throw Error(JSON.stringify(datos));
    fs.writeFileSync(destino, JSON.stringify(datos, null, 2)); console.log(JSON.stringify(datos));
} finally { if (sessionId) await cmd('', null, 'DELETE'); }
