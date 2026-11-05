// Componentes del build publicado, datos ficticios y red interceptada: sin sesión ni escrituras reales.
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-traslado-sucursal-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = '/tmp/gelia-traslado-sucursal-qa';
fs.mkdirSync(destino, { recursive: true });
const reporte = [];
try {
    for (const size of [{ nombre: 'escritorio', width: 1440, height: 1000 }, { nombre: 'movil', width: 390, height: 844 }]) {
        const tema = size.nombre === 'escritorio' ? 'dark' : 'light';
        for (const caso of ['solicitud', 'modalidad', 'recepcion']) {
            const page = await browser.newPage({ viewport: size });
            const errores = [];
            page.on('pageerror', e => errores.push(e.stack));
            await page.addInitScript(tema => { localStorage.setItem('theme', tema); localStorage.setItem('theme_sidebar_mode', 'collapsed'); }, tema);
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Partials/ModalMovimientosTienda';
            pagina.url = '/control-pedidos';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: [] };
            pagina.props.auth.webpush = { enabled: false };
            pagina.props.auth.tema_visual = { modo: tema };
            Object.assign(pagina.props, { abierto: true, pedido: { id: 999, folio: 'EJEMPLO-999', cliente: { nombre: 'Cliente de ejemplo para revisión de sucursal' } }, modo: caso === 'modalidad' ? 'modalidad' : 'traslado' });
            const datos = { aplica: true, version: 0, sucursal_actual: 'Principal', sucursal_destino: 'City Center', puede_solicitar: caso !== 'recepcion', puede_recibir: caso === 'recepcion', sucursales: [{ id: 2, nombre: 'City Center' }], almacenes: [{ id: 9, nombre: 'City Center · Custodia' }], evidencias: [], traslado: caso === 'recepcion' ? { estado: 'autorizada', movimiento_estado: 'en_camino', motivo: 'El cliente solicita recoger en City Center.', recorrido: 'Ruta acordada por gerencia con origen y destino.', anotacion: 'Se notificó un error de captura' } : null };
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
            let enviados = 0;
            await page.route('**/*', async ruta => {
                const req = ruta.request();
                if (req.url() === `${origen}/__traslado_qa__`) return ruta.fulfill({ contentType: 'text/html', body: html });
                if (req.url().includes('/build/')) {
                    const recurso = await fetch(req.url());
                    return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                }
                if (req.url().includes('/movimientos-tienda')) {
                    if (req.method() !== 'GET') enviados++;
                    return ruta.fulfill({ contentType: 'application/json', body: JSON.stringify(datos) });
                }
                if (['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                return ruta.continue();
            });
            await page.goto(`${origen}/__traslado_qa__`, { waitUntil: 'networkidle' });
            await page.getByRole('heading', { name: caso === 'modalidad' ? 'Cambiar modalidad' : 'Trasladar a otra sucursal', exact: true }).waitFor();
            if (caso === 'solicitud' || caso === 'modalidad') {
                await page.getByRole('combobox').first().selectOption(caso === 'solicitud' ? '2' : 'municipal');
                await page.getByLabel('Motivo de la solicitud').fill('El cliente solicita cambiar su lugar de entrega.');
                if (caso === 'solicitud') await page.getByText('Notificar un error de captura', { exact: true }).click();
                await page.getByRole('button', { name: caso === 'solicitud' ? 'Solicitar traslado' : 'Registrar solicitud de cambio', exact: true }).scrollIntoViewIfNeeded();
            } else {
                await page.getByLabel('Almacén de custodia').selectOption('9');
                await page.getByLabel('Ubicación de custodia').fill('Recepción, anaquel B, bolsa EJEMPLO-999');
                await page.getByText('Verifiqué todas las bolsas y sus observaciones.', { exact: true }).click();
                await page.getByRole('button', { name: 'Confirmar recepción y custodia', exact: true }).scrollIntoViewIfNeeded();
            }
            await page.evaluate(() => Promise.all(document.getAnimations().filter(a => Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a => a.finished.catch(() => {}))));
            const desborde = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1 || [...document.querySelectorAll('[role=dialog]')].some(el => el.scrollWidth > el.clientWidth + 1));
            if (errores.length || desborde || enviados) throw new Error(JSON.stringify({ errores, desborde, enviados }));
            await page.screenshot({ path: `${destino}/${caso}-${size.nombre}-${tema}.png`, fullPage: true });
            if (caso === 'solicitud') {
                await page.getByRole('button', { name: 'Solicitar traslado', exact: true }).click();
                await page.getByRole('heading', { name: 'Confirmar operación' }).waitFor();
                if (enviados) throw new Error('Se envió antes de confirmar');
                await page.screenshot({ path: `${destino}/confirmacion-${size.nombre}-${tema}.png`, fullPage: true });
            }
            reporte.push({ caso, tamaño: size.nombre, tema, errores, desborde, enviosAntesDeConfirmar: enviados });
            await page.close();
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ capturas: 8, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${sesion.sessionId}`, { method: 'DELETE' });
}
