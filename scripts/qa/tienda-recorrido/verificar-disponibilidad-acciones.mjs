// Componentes del build publicado, datos ficticios y red interceptada: sin sesión ni escrituras reales.
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-disponibilidad-acciones-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = '/tmp/gelia-disponibilidad-acciones-qa';
fs.mkdirSync(destino, { recursive: true });
const reporte = [];
try {
    for (const size of [{ nombre: 'escritorio', width: 1440, height: 1000 }, { nombre: 'movil', width: 390, height: 844 }]) {
        const tema = size.nombre === 'escritorio' ? 'dark' : 'light';
        for (const caso of ['consulta', 'transferencia', 'cedis']) {
            const page = await browser.newPage({ viewport: size });
            const errores = [];
            page.on('pageerror', e => errores.push(e.stack));
            await page.addInitScript(tema => { localStorage.setItem('theme', tema); localStorage.setItem('theme_sidebar_mode', 'collapsed'); }, tema);
            const pagina = structuredClone(paginaBase);
            pagina.component = caso === 'transferencia' ? 'ControlPedidos/Partials/SeguimientoTransferenciaTienda' : caso === 'cedis' ? 'ControlPedidos/Partials/PanelRespuestaDisponibilidad' : 'ControlPedidos/Partials/SeguimientoSolicitudTienda';
            pagina.url = '/control-pedidos';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: [] };
            pagina.props.auth.webpush = { enabled: false };
            pagina.props.auth.tema_visual = { modo: tema };
            const filas = [{ id: 8, descripcion_producto: 'Aroma reportado sin existencias', sku: 'SKU-8', cantidad_solicitada: 1, estado_fisico: 'sin_existencia' }, { id: 9, descripcion_producto: 'Aroma con detalle de empaque', sku: 'SKU-9', cantidad_solicitada: 1, estado_fisico: 'regular' }];
            const solicitud = { modalidad_entrega: 'tienda', area_revision: 'control_pedidos', revision_disponibilidad: { estado: 'excepciones_pendientes' }, cobertura_tienda: { tipo: 'transferencia' } };
            const pedido = { id: 999, folio: 'EJEMPLO-999', cliente: { nombre: 'Cliente de ejemplo' }, puede_cerrar_consulta: false, motivo_bloqueo_cierre: 'Hay piezas sin existencias pendientes.', revisiones_producto: filas, solicitud_preparacion: solicitud, tarea_preparacion: { estado: 'CON_INCIDENCIA', modalidad: { codigo: 'RECOGE_TIENDA' }, respuesta: { piezas_afectadas: true, cantidad: 2, respondida: true, responsable: 'Auxiliar de ejemplo', productos: filas.map(p => ({ id: p.id, descripcion: p.descripcion_producto, sku: p.sku, estado_fisico: p.estado_fisico, cantidad: p.estado_fisico === 'sin_existencia' ? 0 : 1 })), documentos: [], revision: { estado: 'excepciones_pendientes' } } } };
            Object.assign(pagina.props, { pedidoId: 999, pedido, area: 'control_pedidos', revisiones: filas, consultaCerrada: true });
            const datos = { aplica: true, version: 1, estado: 'pendiente_preparacion', sucursal: 'Principal', origen: 'CEDIS', consulta_cerrada: true, pago_validado: false, numero_remision: null, puede_gestionar: true, puede_continuar_posterior: true, historial: {}, almacenes_destino: [], atencion_md: { fecha_objetivo: '2026-11-05', primer_pago_at: '2026-11-05T10:40:00-06:00', motivo: 'Reporte oportuno. Complete la validación del pago y la remisión.' } };
            if (caso === 'cedis') {
                solicitud.acciones_disponibilidad = { 8: { revision_id: 8, accion: 'sustituir', descripcion: 'Aroma sin existencias', sku: 'SKU-8', por: 'Vendedora de ejemplo', alternativa: { descripcion: 'Alternativa en captura' } }, 9: { revision_id: 9, accion: 'confirmar', descripcion: 'Aroma con detalle', por: 'Vendedora de ejemplo' } };
                solicitud.anexo_sin_remision = [{ entrada: 'documento', descripcion: 'Alternativa en captura', sustituye_revision_id: 8, cantidad: 1 }];
                Object.assign(pagina.props, { modo: 'seccion', abierto: true, revision: solicitud.revision_disponibilidad, tomada: true, puedeResponder: true });
            }
            const html = htmlBase.replace('</head>', '<style>body{background:var(--bg-app);color:var(--theme-text-main)}#app{max-width:960px;margin:auto;padding:20px}</style></head>').replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
            let enviados = 0;
            await page.route('**/*', async ruta => {
                const req = ruta.request();
                if (req.url() === `${origen}/__traslado_qa__`) return ruta.fulfill({ contentType: 'text/html', body: html });
                if (req.url().includes('/build/')) {
                    const recurso = await fetch(req.url());
                    return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                }
                if (req.url().includes('/reabrir-consulta')) return ruta.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true }) });
                if (req.url().includes('/disponibilidad-tienda')) return ruta.fulfill({ contentType: 'application/json', body: JSON.stringify({ ...pedido, consulta_cerrada: false }) });
                if (req.url().includes('/transferencia-tienda')) return ruta.fulfill({ contentType: 'application/json', body: JSON.stringify(datos) });
                if (['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                return ruta.continue();
            });
            await page.goto(`${origen}/__traslado_qa__`, { waitUntil: 'networkidle' });
            if (errores.length) throw new Error(JSON.stringify(errores));
            if (caso === 'consulta') {
                await page.getByText('Consulta cerrada con piezas sin existencias', { exact: true }).waitFor();
                const contraste = await page.locator('.tienda-alerta-disponibilidad').first().evaluate(el => {
                    const ctx = document.createElement('canvas').getContext('2d');
                    const luminancia = color => { ctx.fillStyle = color; ctx.fillRect(0,0,1,1); const d = [...ctx.getImageData(0,0,1,1).data].slice(0,3).map(x => {x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4;}); return d[0]*.2126+d[1]*.7152+d[2]*.0722; };
                    const c = getComputedStyle(el); const x = luminancia(c.color), y = luminancia(c.backgroundColor); return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);
                });
                if (contraste < 4.5) throw new Error(`Contraste insuficiente: ${contraste}, ${tema}, ${await page.locator('.tienda-alerta-disponibilidad').first().evaluate(el => JSON.stringify({ color: getComputedStyle(el).color, fondo: getComputedStyle(el).backgroundColor, clase: document.documentElement.className, datos: document.documentElement.dataset }))}`);
                await page.screenshot({ path: `${destino}/alerta-${size.nombre}-${tema}.png`, fullPage: true });
                await page.getByRole('button', { name: 'Reabrir consulta', exact: true }).click();
                await page.getByRole('button', { name: 'Modificar pedido', exact: true }).waitFor();
                const pieza = page.getByLabel('Acciones rápidas de Aroma reportado sin existencias', { exact: true });
                if (await pieza.getByRole('button', { name: 'Confirmar estado', exact: true }).count()) throw new Error('Un faltante ofrece aceptar');
                if (await page.locator('[aria-label="Ajuste de piezas"]').count()) throw new Error('El ajuste detallado debe iniciar plegado');
                await pieza.getByRole('button', { name: 'Sustituir', exact: true }).click();
                await pieza.getByRole('button', { name: 'Por captura o PDF', exact: true }).click();
                await pieza.getByRole('button', { name: 'Solicitar sustitución', exact: true }).scrollIntoViewIfNeeded();
            } else if (caso === 'transferencia') {
                await page.getByText('El auxiliar debe verificar el ingreso', { exact: true }).waitFor();
                if (await page.locator('textarea').count()) throw new Error('El seguimiento debe iniciar plegado');
                const botones = await page.locator('button').evaluateAll(items => items.filter(b => !b.disabled).every(b => b.querySelector('svg') && parseFloat(getComputedStyle(b).borderTopWidth) >= 1));
                if (!botones) throw new Error('Hay acciones sin icono o sin borde visible');
            } else {
                await page.getByRole('heading', { name: 'Cambios guardados del pedido', exact: true }).waitFor();
                await page.getByRole('button', { name: 'Confirmar disponibilidad', exact: true }).scrollIntoViewIfNeeded();
            }
            await page.evaluate(() => Promise.all(document.getAnimations().filter(a => Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a => a.finished.catch(() => {}))));
            const desborde = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1 || [...document.querySelectorAll('[role=dialog]')].some(el => el.scrollWidth > el.clientWidth + 1));
            if (errores.length || desborde || enviados) throw new Error(JSON.stringify({ errores, desborde, enviados }));
            await page.screenshot({ path: `${destino}/${caso}-${size.nombre}-${tema}.png`, fullPage: true });
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
