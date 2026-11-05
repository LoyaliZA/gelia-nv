// Pago al recoger: guardar y confirmar con respuestas ficticias del servidor. Sin sesión ni escrituras reales.
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-detalle-retiro-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = '/tmp/gelia-detalle-retiro-qa';
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
            pagina.component = 'ControlPedidos/Partials/ModalDetallePedido';
            pagina.url = '/admin/configuracion-operativa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: ['configuracion_sistema.gestionar'] };
            pagina.props.auth.webpush = { enabled: false };
            pagina.props.auth.tema_visual = { modo: tema };
            const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="white"/><text x="35" y="60" font-size="26">PEDIDO DE EJEMPLO</text><text x="35" y="125" font-size="20">SKU-01 · Perfume de ejemplo · 2 pzas.</text><path d="M35 155H565" stroke="gray"/></svg>';
            const documento = { id: 1, nombre_original: 'pedido-ejemplo.svg', mime_type: 'image/svg+xml', url: 'data:image/svg+xml,' + encodeURIComponent(svg) };
            const solicitud = { modalidad_entrega: 'tienda', area_revision: 'control_pedidos', revision_disponibilidad: { estado: 'excepciones_pendientes', vigente: true } };
            const pedido = { id: 99, folio: 'EJEMPLO-99', folio_remision: '12345', puede_cerrar_consulta: true,
                cliente: { nombre: 'Cliente de ejemplo', numero_cliente: '123' }, vendedor: { name: 'Vendedora de ejemplo' },
                sucursal_destino: { nombre: 'Carmen Cadena' }, almacen: { id: 1, nombre: 'CEDIS' },
                solicitud_preparacion: solicitud, documentos: [{...documento, tipo: 'pdf_pedido'}],
                revisiones_producto: [{ id: 9, descripcion_producto: 'Perfume agotado', cantidad_solicitada: 2, estado_fisico: 'sin_existencia' }],
                estatus: { nombre: 'Consulta respondida' }, pesaje_respondido_at: '2026-11-05T10:00:00',
            };
            Object.assign(pagina.props, { abierto: true, pedido, pedidoId: 99, area: 'control_pedidos' });
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', '<style>#app{max-width:1100px;margin:auto;padding:16px}body{background:var(--theme-bg-base)}</style></head>').replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
            await page.route('**/*', async ruta => {
                const req = ruta.request();
                if (req.url() === `${origen}/__operativa_qa__`) return ruta.fulfill({ contentType: 'text/html', body: html });
                if (req.url().includes('/build/')) {
                    const recurso = await fetch(req.url());
                    return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                }
                if (req.url().endsWith('/cobertura-tienda')) return ruta.fulfill({ contentType: 'application/json', body: '{}' });
                if (req.url().endsWith('/confirmar-pedido-tienda')) return ruta.fulfill({ contentType: 'application/json', body: '{}' });
                if (req.method() !== 'GET') return ruta.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
                if (!req.url().includes('/build/') && ['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                return ruta.continue();
            });
            await page.goto(`${origen}/__operativa_qa__`, { waitUntil: 'networkidle' });
            await page.getByRole('heading', { name: 'Recolección en tienda', exact: true }).waitFor();
            const contenido = await page.locator('[role=dialog]').innerText();
            for (const etiqueta of ['Peso real', 'Paquetería', 'Entrega y dirección', 'Reexpedición']) {
                if (contenido.includes(etiqueta)) throw new Error(`Campo ajeno a tienda: ${etiqueta}`);
            }
            await page.evaluate(() => Promise.all(document.getAnimations().filter(a => Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a => a.finished.catch(() => {}))));
            const desborde = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1 || [...document.querySelectorAll('[role=dialog]')].some(el => el.scrollWidth > el.clientWidth + 1));
            if (errores.length || desborde) throw new Error(JSON.stringify({ errores, desborde }));
            await page.screenshot({ path: `${destino}/detalle-${size.nombre}-${tema}.png`, fullPage: true });
            pagina.component = 'ControlPedidos/Partials/SeguimientoSolicitudTienda';
            const htmlRetiro = html.replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b);
            await page.unroute('**/*');
            await page.route('**/*', async ruta => {
                const req = ruta.request();
                if (req.url() === `${origen}/__retiro_qa__`) return ruta.fulfill({ contentType: 'text/html', body: htmlRetiro });
                if (req.url().includes('/build/')) {
                    const recurso = await fetch(req.url());
                    return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                }
                if (['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                return ruta.continue();
            });
            await page.goto(`${origen}/__retiro_qa__`, { waitUntil: 'networkidle' });
            if (errores.length) throw new Error(JSON.stringify(errores));
            
            await page.getByRole('tab', { name: 'Retirar piezas', exact: true }).click();
            await page.getByLabel('Perfume agotado', { exact: true }).check();
            if (await page.locator('#retiro-buscar-producto').count()) throw new Error('La búsqueda debe abrirse solo al solicitarla');
            await page.getByLabel('Motivo del retiro', { exact: true }).selectOption('El cliente ya no lo quiere');
            await page.getByRole('button', { name: /Solicitar retiro/i }).scrollIntoViewIfNeeded();
            if (errores.length) throw new Error(JSON.stringify(errores));
            await page.screenshot({ path: `${destino}/retiro-${size.nombre}-${tema}.png`, fullPage: true });
            reporte.push({ tamaño: size.nombre, tema, errores, desborde, detalle: true, faltanteSinSkuSeleccionable: true });
            await page.close();
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ capturas: reporte.length * 2, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${sesion.sessionId}`, { method: 'DELETE' });
}
