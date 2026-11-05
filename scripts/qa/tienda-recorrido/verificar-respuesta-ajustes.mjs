// Render aislado del build publicado con datos ficticios; no inicia sesión ni guarda cambios.
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-respuesta-ajustes-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = '/tmp/gelia-respuesta-ajustes-qa';
fs.mkdirSync(destino, { recursive: true });
const reporte = [];
try {
    for (const size of [{ nombre: 'escritorio', width: 1440, height: 1000 }, { nombre: 'movil', width: 390, height: 844 }]) {
        for (const tema of [size.nombre === 'escritorio' ? 'dark' : 'light']) {
            const page = await browser.newPage({ viewport: size });
            const errores = [];
            page.on('pageerror', e => errores.push(e.stack));
            await page.addInitScript(tema => { localStorage.setItem('theme', tema); localStorage.setItem('theme_sidebar_mode', 'collapsed'); }, tema);
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Partials/SeguimientoSolicitudTienda';
            pagina.url = '/admin/configuracion-operativa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: ['configuracion_sistema.gestionar'] };
            pagina.props.auth.webpush = { enabled: false };
            pagina.props.auth.tema_visual = { modo: tema };
            const revision = { estado: 'excepciones_pendientes', aceptacion_vigente: true };
            Object.assign(pagina.props, {
                pedidoId: 99, area: 'preparacion_tienda', pedido: { id: 99, puede_cerrar_consulta: true,
                    solicitud_preparacion: { revision_disponibilidad: revision },
                    tarea_preparacion: { estado: 'RESPONDIDA', respuesta: { respondida: true, cantidad: 1, piezas_afectadas: true, responsable: 'Auxiliar de ejemplo', revision,
                        observaciones: 'La respuesta incluye piezas con excepción. El pedido se cierra con Cerrar consulta. Para cambiar piezas, registre un anexo o un retiro.',
                        productos: [{ id: 1, descripcion: 'Perfume de ejemplo', sku: 'SKU-01', cantidad: 0, estado_fisico: 'sin_existencia', observacion: 'Sin existencias', mejor_ejemplar: true }] } },
                },
            });
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', '<style>#app{max-width:1100px;margin:auto;padding:16px}body{background:var(--theme-bg-base)}</style></head>').replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
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
            await page.getByRole('heading', { name: 'Ajuste de piezas', exact: true }).waitFor();
            const imagen = await page.evaluate(() => {
                const canvas = document.createElement('canvas'); canvas.width = 600; canvas.height = 400;
                const ctx = canvas.getContext('2d'); ctx.fillStyle = '#e8edf2'; ctx.fillRect(0, 0, 600, 400);
                ctx.fillStyle = '#34495e'; ctx.fillRect(180, 100, 90, 210); ctx.fillStyle = '#ba9d66'; ctx.fillRect(310, 160, 100, 150);
                ctx.fillStyle = '#243342'; ctx.font = '24px sans-serif'; ctx.fillText('Piezas de ejemplo', 180, 60);
                return canvas.toDataURL('image/png').split(',')[1];
            });
            await page.locator('#ajuste-evidencia').locator('..').locator('..').locator('input[type=file][multiple]').setInputFiles({ name: 'piezas-ejemplo.png', mimeType: 'image/png', buffer: Buffer.from(imagen, 'base64') });
            await page.locator('img[alt="piezas-ejemplo.png"]').waitFor();
            const desborde = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
            if (errores.length || desborde) throw new Error(JSON.stringify({ errores, desborde }));
            const resultado = await page.locator('[aria-label="Respuesta de preparación"]').innerText();
            if (resultado.includes('undefined') || resultado.includes('Mejor ejemplar') || (resultado.match(/Sin existencias/g) || []).length !== 1) throw new Error('Etiquetas mezcladas');
            await page.screenshot({ path: `${destino}/${size.nombre}-${tema}.png`, fullPage: true });
            await page.getByRole('button', { name: 'Ver evidencia 1: piezas-ejemplo.png' }).click();
            await page.getByRole('dialog').waitFor();
            await page.evaluate(() => Promise.all(document.getAnimations().filter(a => Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a => a.finished.catch(() => {}))));
            await page.screenshot({ path: `${destino}/visor-${size.nombre}-${tema}.png`, fullPage: true });
            reporte.push({ tamaño: size.nombre, tema, errores, desborde, miniatura: true, visor: true });
            await page.close();
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ capturas: reporte.length * 2, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${sesion.sessionId}`, { method: 'DELETE' });
}
