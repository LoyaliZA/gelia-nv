// Render aislado del build publicado con datos ficticios; no inicia sesión ni guarda cambios.
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-configuracion-operativa-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = '/tmp/gelia-configuracion-operativa-qa';
fs.mkdirSync(destino, { recursive: true });
const reporte = [];
try {
    for (const size of [{ nombre: 'escritorio', width: 1440, height: 1000 }, { nombre: 'movil', width: 390, height: 844 }]) {
        for (const tema of ['light', 'dark']) {
            const page = await browser.newPage({ viewport: size });
            const errores = [];
            page.on('pageerror', e => errores.push(e.stack));
            await page.addInitScript(tema => { localStorage.setItem('theme', tema); localStorage.setItem('theme_sidebar_mode', 'collapsed'); }, tema);
            const pagina = structuredClone(paginaBase);
            pagina.component = 'Admin/ConfiguracionOperativa/Index';
            pagina.url = '/admin/configuracion-operativa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: ['configuracion_sistema.gestionar'] };
            pagina.props.auth.webpush = { enabled: false };
            pagina.props.auth.tema_visual = { modo: tema };
            Object.assign(pagina.props, {
                tienda: { md_dias: [1, 2, 3, 4, 5], md_corte: '13:00', apartado_dias: [1, 2, 3, 4, 5, 6], apartado_cierre: '17:00', sabado_apertura: '09:00', sabado_cierre: '13:00', recoleccion_dias: 20 },
                general: { zona_horaria: 'America/Mexico_City', recordatorio_hora: '11:00', cierre_operativo: '20:00', dias_resguardo: 3, dias_prorroga_vip: 1 },
                plazos: { activo: true, hora_corte: '18:00', dias_habiles: [1, 2, 3, 4, 5, 6], temporada_alta: false, dias_extra_temporada_alta: 1, comercial: { dias_empaque: 1, dias_recoleccion: 1 }, local_regional: { dias_empaque: 1, dias_recoleccion: 1 } },
            });
            const html = htmlBase.replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
            await page.route('**/*', async ruta => {
                const req = ruta.request();
                if (req.url() === `${origen}/__operativa_qa__`) return ruta.fulfill({ contentType: 'text/html', body: html });
                if (req.url().includes('/build/')) {
                    const recurso = await fetch(req.url());
                    return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                }
                if (req.method() !== 'GET') return ruta.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
                if (!req.url().includes('/build/') && ['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                return ruta.continue();
            });
            await page.goto(`${origen}/__operativa_qa__`, { waitUntil: 'networkidle' });
            await page.getByRole('heading', { name: 'Configuración operativa', exact: true }).waitFor({ timeout: 10000 }).catch(async e => { throw new Error(JSON.stringify({ errores, contenido: (await page.locator('body').innerText()).slice(0, 600) })); });
            const texto = await page.locator('body').innerText();
            const desborde = await page.evaluate(() => ({ pagina: document.documentElement.scrollWidth > innerWidth + 1, secciones: [...document.querySelectorAll('form section')].filter(el => el.scrollWidth > el.clientWidth + 1).length }));
            if (errores.length || desborde.pagina || desborde.secciones || !/guardar configuración/i.test(texto)) throw new Error(JSON.stringify({ tema, size, errores, desborde }));
            await page.screenshot({ path: `${destino}/${size.nombre}-${tema}.png`, fullPage: true });
            reporte.push({ tamaño: size.nombre, tema, errores, desborde });
            await page.close();
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ capturas: reporte.length, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${sesion.sessionId}`, { method: 'DELETE' });
}
