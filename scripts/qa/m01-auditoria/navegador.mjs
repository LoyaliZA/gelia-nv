import fs from 'node:fs';
const root = 'http://laravel.test:9193';
const output = '/tmp/gelia-m01-navegador';
const fixture = JSON.parse(fs.readFileSync('/tmp/m01-fixtures.json'));
fs.mkdirSync(output, { recursive: true });
const assert = (ok, message) => { if (!ok) throw Error(message); };
class Browser {
    async cmd(path, body, method = 'POST') {
        const response = await fetch(`http://selenium:4444/session${this.id ? '/' + this.id : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
        const data = (await response.json()).value;
        if (data?.error) throw Error(JSON.stringify(data));
        return data;
    }
    js(script, args = []) { return this.cmd('/execute/sync', { script, args }); }
    async wait(condition) {
        assert(await this.cmd('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function check(){try{if(${condition})return done(true)}catch{}if(++n>150)return done(false);setTimeout(check,100)}check()`, args: [] }), `No se cumplió: ${condition}`);
    }
    async go(path) { await this.cmd('/url', { url: root + path }); await this.wait('document.readyState === "complete" && !!window.axios'); }
    async click(text) { await this.js('const e=[...document.querySelectorAll("button,a")].find(e=>e.textContent.trim()===arguments[0]);if(!e)throw Error("No existe "+arguments[0]);e.click()', [text]); }
    async type(selector, text) {
        const element = (await this.cmd('/element', { using: 'css selector', value: selector }))['element-6066-11e4-a52e-4f735466cecf'];
        await this.cmd(`/element/${element}/clear`, {});
        await this.cmd(`/element/${element}/value`, { text });
    }
    async api(url, data) {
        return this.cmd('/execute/async', { script: 'const done=arguments[arguments.length-1];window.axios.post(arguments[0],arguments[1],{headers:{Accept:"application/json"}}).then(r=>done({status:r.status,data:r.data})).catch(e=>done({status:e.response?.status,data:e.response?.data}))', args: [url, data] });
    }
    async shot(name, width) {
        await this.cmd('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width, height: 1000, deviceScaleFactor: 1, mobile: width === 390 } });
        await this.js('document.documentElement.classList.toggle("dark",arguments[0]);localStorage.setItem("theme",arguments[0]?"dark":"light")', [width !== 390]);
        await this.wait('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');
        fs.writeFileSync(`${output}/${name}-${width}.png`, Buffer.from(await this.cmd('/screenshot', null, 'GET'), 'base64'));
        return this.js('return {ancho:innerWidth,desborde:document.documentElement.scrollWidth>innerWidth+1}');
    }
}
const b = new Browser();
const result = { fixture, pasos: [], medidas: [] };
try {
    const state = (await (await fetch('http://selenium:4444/status')).json()).value;
    assert(!state.nodes.some(n => n.slots.some(s => s.session)), 'Selenium ocupado; no se toca la sesión ajena.');
    b.id = (await b.cmd('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'Auditoria-M01', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] }, 'goog:loggingPrefs': { browser: 'ALL' } } } })).sessionId;
    await b.cmd('/timeouts', { script: 30000, pageLoad: 30000 });
    await b.go('/login');
    await b.type('input[type=text]', 'navegador-m01@example.test');
    await b.type('input[type=password]', 'M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()');
    await b.wait('!location.pathname.includes("/login")');
    result.pasos.push('Autenticación real con usuario ficticio y cookie exclusiva.');
    await b.go(`/admin/clientes/${fixture.cliente_id}/expediente`);
    await b.wait('document.body.textContent.includes("Agregar consignatario")');
    await b.type('section form input', 'Persona ficticia');
    await b.type('section form label:nth-child(2) input', '3311111111');
    await b.click('Agregar consignatario');
    await b.wait('document.querySelector("section ul")?.textContent.includes("Persona ficticia")');
    await b.click('Editar datos');
    await b.type('section form label:nth-child(2) input', '3322222222');
    await b.click('Guardar datos');
    await b.wait('document.querySelector("section ul")?.textContent.includes("3322222222")');
    result.pasos.push('Alta y edición de teléfono desde la interfaz de Clientes.');
    const subirIdentificacion = async () => b.js(`const c=document.createElement('canvas');c.width=500;c.height=300;const ctx=c.getContext('2d');ctx.fillStyle='white';ctx.fillRect(0,0,500,300);ctx.fillStyle='black';ctx.fillText('IDENTIFICACION FICTICIA DE AUDITORIA',20,130);const bytes=Uint8Array.from(atob(c.toDataURL('image/png').split(',')[1]),c=>c.charCodeAt(0));const dt=new DataTransfer();dt.items.add(new File([bytes],'identificacion-ficticia.png',{type:'image/png'}));const input=document.querySelector('section ul input[type=file]');input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));`);
    await subirIdentificacion();
    await b.wait('document.querySelector("section ul")?.textContent.includes("versión 1")');
    await subirIdentificacion();
    await b.wait('document.querySelector("section ul")?.textContent.includes("versión 2")');
    result.pasos.push('Carga y sustitución real de identificación ficticia desde el control de archivo.');
    for (const width of [1440, 1024, 390]) result.medidas.push({ vista: 'expediente', ...await b.shot('expediente', width) });
    const listado = await b.cmd('/execute/async', { script: 'const done=arguments[arguments.length-1];window.axios.get(arguments[0]).then(r=>done(r.data))', args: [`/control-pedidos/municipal/clientes/${fixture.cliente_id}/consignatarios?pedido_id=${fixture.pedido_id}`] });
    const consignatario = listado.consignatarios[0];
    assert(consignatario.version.telefono === '3322222222', 'No reutilizó el mismo expediente desde pedidos.');
    let response = await b.api(`/control-pedidos/municipal/${fixture.pedido_id}/solicitud`, { municipio_destino: 'Municipio ficticio', catalogo_paqueteria_id: fixture.transporte_id, consignatario_id: consignatario.id, leyenda_terminal: 'PAGADO', costo_transporte: 'diferido' });
    assert(response.status === 200 || (response.status === 422 && response.data?.errors?.consulta), 'Solicitud: ' + JSON.stringify(response));
    response = await b.api(`/control-pedidos/municipal/${fixture.pedido_id}/consulta`, { almacen_id: fixture.almacen_id });
    assert(response.status === 200, 'Consulta: ' + JSON.stringify(response));
    await b.go('/control-pedidos/municipal/consultas-tienda');
    await b.wait('[...document.querySelectorAll("button")].some(e=>e.textContent.trim()==="Revisar disponibilidad")');
    await b.click('Revisar disponibilidad');
    await b.wait('document.querySelector("input[name=estado-disponibilidad-cedis]")');
    await b.js('document.querySelector("input[name=estado-disponibilidad-cedis]").click()');
    await b.wait(`document.querySelector('input[aria-label="Adjuntar: Captura del pedido"]')`);
    await b.js(`const c=document.createElement('canvas');c.width=500;c.height=300;const ctx=c.getContext('2d');ctx.fillStyle='white';ctx.fillRect(0,0,500,300);ctx.fillStyle='black';ctx.font='22px sans-serif';ctx.fillText('MERCANCIA FICTICIA M01',20,130);const bytes=Uint8Array.from(atob(c.toDataURL('image/png').split(',')[1]),c=>c.charCodeAt(0));const dt=new DataTransfer();dt.items.add(new File([bytes],'conjunto-ficticio.png',{type:'image/png'}));const input=document.querySelector('input[aria-label="Adjuntar: Captura del pedido"]');input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));`);
    for (const width of [1440, 1024, 390]) result.medidas.push({ vista: 'respuesta-tienda', ...await b.shot('respuesta-tienda', width) });
    await b.click('Enviar respuesta');
    await b.wait('document.body.textContent.includes("No hay consultas pendientes")');
    result.pasos.push('Apertura de cola Tienda, selección de estado, carga de evidencia y envío real.');
    response = await b.api(`/control-pedidos/${fixture.pedido_id}/cerrar-consulta`, {});
    assert(response.status === 200, 'Cierre: ' + JSON.stringify(response));
    result.pasos.push('Cierre de Ventas mediante endpoint autenticado (no botón del modal).');
    await b.go(`/admin/clientes/${fixture.cliente_id}/expediente`);
    await b.wait('[...document.querySelectorAll("button")].some(e=>e.textContent.trim()==="Dar de baja identificación")');
    await b.click('Dar de baja identificación');
    await b.wait('document.querySelector("section ul")?.textContent.includes("Sin identificación vigente")');
    await b.click('Dar de baja consignatario');
    await b.wait('!document.querySelector("section ul li")');
    await b.js('document.querySelector("section input[type=checkbox]").click()');
    await b.wait('!!document.querySelector("section ul li details")');
    await b.js('document.querySelector("section ul li details").open=true');
    result.pasos.push('Baja lógica de identificación y consignatario, consulta de historial desde Clientes.');
    result.console = await b.cmd('/log', { type: 'browser' });
    result.resultado = 'aprobado';
    assert(result.medidas.every(m => !m.desborde), 'Se detectó desbordamiento horizontal.');
} catch (error) {
    result.resultado = 'fallo'; result.error = String(error);
    if (b.id) result.pantalla = await b.js('return document.body.textContent.slice(0,2500)');
    throw error;
} finally {
    fs.writeFileSync(`${output}/resultado.json`, JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result));
    if (b.id) await b.cmd('', null, 'DELETE');
}
