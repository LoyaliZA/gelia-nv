// Modal real del build publicado; datos y transporte ficticios, sin pedidos operativos.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const opcional = process.env.QA_PASO_OPCIONAL === '1';
const destino = opcional ? '/tmp/gelia-paso-opcional-tienda-qa' : '/tmp/gelia-preparacion-tienda-qa';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw Error('Selenium está ocupado.');
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="white"/><text x="30" y="60" font-size="28">PEDIDO FICTICIO</text><rect x="30" y="100" width="540" height="500" fill="lightgray"/></svg>';
const doc = { id: 1, tipo: 'pdf_pedido', nombre_original: 'pedido-ejemplo.svg', mime_type: 'image/svg+xml', url: 'data:image/svg+xml,' + encodeURIComponent(svg) };
const auth = { user: { id: 99, name: 'Auxiliar de ejemplo', roles: [], permissions: ['control_pedidos.tienda.ver', 'control_pedidos.tienda.apartado.separar', 'control_pedidos.tienda.reportar_error'] }, webpush: { enabled: false } };
let etapa = 'inicial';
let habilitado = false;
let envios = [];
function datos() {
    const revision = { estado: 'sin_excepciones', aceptacion_vigente: true };
    const pedido = { id: 99999, folio: 'PBMA-EJEMPLO', folio_visible: 'Pedido de ejemplo', area_revision: 'preparacion_tienda', cliente_nombre: 'Cliente ficticio para revisión de Tienda', documentos: [doc], anexos_pendientes: [], retiros: [], es_t01_documental: true, seguimiento_entrega_pdv_habilitado: habilitado };
    const tarea = { id: 77, version: 1, estado: 'EN_ATENCION', estado_label: 'En atención', puede_preparar: true, puede_reportar_incidencia: true, responsable: { id: 99, name: 'Auxiliar de ejemplo' }, modalidad: { codigo: 'RECOGE_TIENDA', nombre: 'Recoge en tienda' }, almacen: { id: 1, nombre: 'Sucursal de ejemplo' }, apartado: { version: 1, estado: 'POR_SEPARAR' }, productos: [], pedido };
    if (etapa !== 'inicial') {
        pedido.revision_disponibilidad = revision;
        tarea.respuesta = { respondida: true, responsable: 'Auxiliar de ejemplo', respondida_at: '2026-11-08T16:00:00Z', revision, productos: [], excepciones: [], documentos: [{ ...doc, id: 3, tipo: 'evidencia_general', producto_id: null }] };
    }
    if (['modalidad', 'aprobada', 'confirmada'].includes(etapa)) { pedido.consulta_cerrada = true; pedido.cobertura_tienda = { requiere_confirmacion_pedido: true }; }
    if (['aprobada', 'confirmada'].includes(etapa)) {
        pedido.cobertura_tienda = { ...pedido.cobertura_tienda, tipo: 'apartado', confirmacion_enviada_at: '2026-11-08T16:00:00Z' };
        pedido.anexos_por_separar = etapa === 'aprobada' ? [{ indice: 0, estado: 'revisado_conjunto' }] : [];
    }
    if (etapa === 'cambios') {
        revision.actualizacion_pendiente = true; revision.actualizacion_id = '123e4567-e89b-42d3-a456-426614174000';
        pedido.anexo_sin_remision = [{ entrada: 'documento', documento_id: 1, descripcion: 'Anexo solicitado en captura', pendiente_revision: true }];
        pedido.anexos_pendientes = [{ indice: 0, entrada: 'documento' }];
    }
    if (etapa === 'confirmada') {
        tarea.estado = 'RESPONDIDA'; tarea.estado_label = 'Apartado confirmado'; tarea.apartado.estado = 'APARTADO_CONFIRMADO';
        tarea.respuesta.apartado_confirmado = true; tarea.respuesta.documental = true; tarea.respuesta.seguimiento_entrega_pdv_habilitado = habilitado;
    }
    return { tarea, documentos_solicitud: [doc], documentos: [], requisitos: {}, config: {}, historial: [], eventos_apartado: [] };
}
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const params = new URL(req.url, proxy).searchParams; etapa = params.get('etapa'); habilitado = params.get('habilitado') === '1'; envios = [];
            const tema = params.get('tema'); const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Tienda/Partials/ModalPreparacionTienda'; pagina.url = '/qa';
            Object.assign(pagina.props, { auth, tareaId: 77, atender: false });
            if (params.get('vista') === 'detalle') {
                pagina.component = 'ControlPedidos/Tienda/Show'; Object.assign(pagina.props, datos(), { embedded: false });
            }
            if (params.get('vista') === 'config') {
                pagina.component = 'Admin/ConfiguracionOperativa/Index';
                Object.assign(pagina.props, {
                    tienda: { md_dias:[1,2,3,4,5],md_corte:'13:00',apartado_dias:[1,2,3,4,5,6],apartado_cierre:'17:00',sabado_apertura:'09:00',sabado_cierre:'13:00',recoleccion_dias:20,seguimiento_entrega_pdv:false },
                    general: { zona_horaria:'America/Mexico_City',recordatorio_hora:'11:00',cierre_operativo:'20:00',dias_resguardo:3,dias_prorroga_vip:1 },
                    plazos: { activo:true,hora_corte:'18:00',dias_habiles:[1,2,3,4,5,6],temporada_alta:false,dias_extra_temporada_alta:1,comercial:{dias_empaque:1,dias_recoleccion:1},local_regional:{dias_empaque:1,dias_recoleccion:1} },
                });
            }
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');document.documentElement.classList.add('glass-active');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
            const html = htmlBase.replace(/(const Ziggy=\{"url":)"[^"]+"/, (_, inicio) => inicio + JSON.stringify(proxy)).replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', `${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.startsWith('/build/')) { const recurso = await fetch(`${origen}${req.url}`); res.writeHead(recurso.status, { 'Content-Type': recurso.headers.get('content-type') || 'application/octet-stream' }); res.end(Buffer.from(await recurso.arrayBuffer())); return; }
        res.writeHead(200, { 'Content-Type': 'application/json' });
        if (req.url.includes('/tienda/tareas/77')) {
            if (req.method === 'POST') { envios.push(req.url); etapa = 'confirmada'; res.end(JSON.stringify({ message: 'Apartado confirmado.' })); }
            else res.end(JSON.stringify(datos()));
            return;
        }
        res.end(JSON.stringify({ data: [], conversaciones: [], notificaciones: [], unread_count: 0 }));
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(resolve => servidor.listen(9192, '0.0.0.0', resolve));
let sessionId;
const reporte = [];
async function comando(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const data = await r.json(); if (data.value?.error) throw Error(JSON.stringify(data.value)); return data.value;
}
const ejecutar = script => comando('/execute/sync', { script, args: [] });
async function esperar(condicion) {
    const ok = await comando('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function revisar(){if(${condicion})done(true);else if(++n>100)done(false);else setTimeout(revisar,100)}revisar();`, args: [] });
    if (!ok) { console.log(await ejecutar('return {texto:document.body.innerText.slice(-600),errores:window.qaErrores}')); await captura('fallo'); throw Error(`No se cumplió: ${condicion}`); }
}
async function captura(nombre) {
    await esperar('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');
    fs.writeFileSync(`${destino}/${nombre}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
}
try {
    sessionId = (await comando('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } })).sessionId;
    for (const tam of [{ nombre: 'escritorio', width: 1440, height: 1000, tema: 'dark' }, { nombre: 'movil', width: 390, height: 844, tema: 'light' }]) {
        await comando('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: tam.width, height: tam.height, deviceScaleFactor: 1, mobile: tam.width < 600 } });
        if (opcional) {
            for (const caso of [{vista:'config',habilitado:false},{vista:'modal',habilitado:true},{vista:'detalle',habilitado:false},{vista:'detalle',habilitado:true}]) {
                await comando('/url', {url:`${proxy}/qa?tema=${tam.tema}&etapa=confirmada&vista=${caso.vista}&habilitado=${caso.habilitado ? 1 : 0}`});
                await esperar(caso.vista === 'config' ? `!!document.querySelector('[role="switch"]')` : `!!document.querySelector('[aria-label="Preparación en tienda"]')`);
                if (caso.vista === 'config') {
                    await ejecutar(`document.querySelector('[role="switch"]').scrollIntoView({block:'center'})`);
                    if (await ejecutar(`return document.querySelector('[role="switch"]').getAttribute('aria-checked')`) !== 'false') throw Error('El switch no inicia apagado');
                    await captura(`${tam.nombre}-config-apagado`);
                    await ejecutar(`document.querySelector('[role="switch"]').click()`);
                    await esperar(`document.querySelector('[role="switch"]').getAttribute('aria-checked') === 'true'`);
                } else {
                    const panel = await ejecutar(`return !!document.querySelector('[aria-label="Pago y entrega en tienda"]')`);
                    if (panel !== (caso.vista === 'detalle' && caso.habilitado)) throw Error('Paso opcional visible fuera de contexto');
                    if (caso.vista === 'modal' && await ejecutar(`return /Pago presencial pendiente|Entrega pendiente/.test(document.body.innerText)`)) throw Error('El modal conserva pendientes de pago');
                    if (panel) await ejecutar(`document.querySelector('[aria-label="Pago y entrega en tienda"]').scrollIntoView({block:'start'})`);
                }
                await captura(`${tam.nombre}-${caso.vista}-${caso.vista === 'config' || caso.habilitado ? 'activo' : 'apagado'}`);
                const errores = await ejecutar('return window.qaErrores');
                const desborde = await ejecutar('return document.documentElement.scrollWidth > innerWidth + 1');
                if (errores.length || desborde) throw Error(JSON.stringify({caso,errores,desborde}));
                reporte.push({...tam,...caso,errores,desborde});
            }
            continue;
        }
        for (const estado of ['inicial', 'respuesta', 'modalidad', 'cambios', 'aprobada', 'confirmada']) {
            await comando('/url', { url: `${proxy}/qa?tema=${tam.tema}&etapa=${estado}` });
            await esperar(`!!document.querySelector('[aria-label="Preparación en tienda"]')`);
            const confirmar = await ejecutar('return [...document.querySelectorAll("button")].filter(b=>b.textContent.trim()==="Confirmar pedido separado").length');
            if (confirmar !== (estado === 'aprobada' ? 1 : 0)) throw Error(`Confirmación fuera de etapa ${estado}`);
            if (await ejecutar('return document.body.textContent.includes("Confirmar separación del anexo") || document.body.textContent.includes("Guardar y continuar después")')) throw Error('Confirmaciones redundantes');
            if (estado === 'aprobada') await ejecutar(`document.querySelector('[aria-label="Confirmación del apartado"]').scrollIntoView({block:'center'})`);
            await captura(`${tam.nombre}-${estado}`);
            if (estado === 'aprobada') {
                await ejecutar('[...document.querySelectorAll("button")].find(b=>b.textContent.trim()==="Confirmar pedido separado").click()');
                await esperar('document.body.textContent.includes("Apartado confirmado") && ![...document.querySelectorAll("button")].some(b=>b.textContent.trim()==="Confirmar pedido separado")');
                if (envios.length !== 1 || !envios[0].endsWith('/apartado/confirmar')) throw Error('Envío duplicado o incorrecto');
            }
            const errores = await ejecutar('return window.qaErrores');
            const desborde = await ejecutar('return document.documentElement.scrollWidth > innerWidth + 1 || [...document.querySelectorAll(".gelia-preparacion")].some(e=>e.scrollWidth>e.clientWidth+1)');
            if (errores.length || desborde) throw Error(JSON.stringify({ estado, errores, desborde }));
            reporte.push({ ...tam, estado, confirmar, errores, desborde });
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ destino, capturas: reporte.length + (opcional ? 2 : 0) }));
} finally { if (sessionId) await comando('', null, 'DELETE'); await new Promise(resolve => servidor.close(resolve)); }
