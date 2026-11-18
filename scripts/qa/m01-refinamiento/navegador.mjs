import fs from 'node:fs';
const root = 'http://laravel.test:9193';
const output = '/tmp/gelia-m01-refinamiento';
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
    async shot(name, width, oscuro) {
        await this.cmd('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width, height: 1000, deviceScaleFactor: 1, mobile: width === 390 } });
        await this.js('document.documentElement.classList.toggle("dark",arguments[0]);localStorage.setItem("theme",arguments[0]?"dark":"light")', [oscuro]);
        await this.wait('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');
        fs.writeFileSync(`${output}/${name}-${width}-${oscuro ? 'dark' : 'light'}.png`, Buffer.from(await this.cmd('/screenshot', null, 'GET'), 'base64'));
        return this.js('return {ancho:innerWidth,desborde:document.documentElement.scrollWidth>innerWidth+1}');
    }
}
const b = new Browser();
const result = { pasos: [], medidas: [] };
try {
    const state = (await (await fetch('http://selenium:4444/status')).json()).value;
    assert(!state.nodes.some(n => n.slots.some(s => s.session)), 'Selenium ocupado');
    b.id = (await b.cmd('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'Refinamiento-M01', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] }, 'goog:loggingPrefs': { browser: 'ALL' } } } })).sessionId;
    await b.cmd('/timeouts', { script: 30000, pageLoad: 30000 });
    await b.go('/login');
    await b.type('input[type=text]', fixture.email);
    await b.type('input[type=password]', 'M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()');
    await b.wait('!location.pathname.includes("/login")');
    await b.go('/control-pedidos');
    await b.wait("[...document.querySelectorAll('tr,article')].some(e=>e.textContent.includes('QA-M01-NAVEGADOR'))");
    const editar = async () => {
        await b.wait("[...document.querySelectorAll('tr,article')].some(e=>e.textContent.includes('QA-M01-NAVEGADOR'))");
        await b.js(`const row=[...document.querySelectorAll('tr,article')].find(e=>e.textContent.includes('QA-M01-NAVEGADOR'));const buttons=[...row.querySelectorAll('button')];const edit=buttons.find(e=>e.textContent.trim()==='Editar');if(edit)edit.click();else buttons.find(e=>e.getAttribute('aria-haspopup')==='menu').click();`);
        if (!await b.js('return !!document.querySelector("#registro-municipal-titulo")')) await b.click('Editar');
        await b.wait('!!document.querySelector("#registro-municipal-titulo")');
    };
    await editar();
    await b.wait('document.body.textContent.includes("Agregar consignatario")');
    const cambiarSelect = async (label, value) => b.js(`const label=[...document.querySelectorAll('[role=dialog] label')].find(e=>e.textContent.trim().startsWith(arguments[0]));const el=label.querySelector('select');el.value=arguments[1];el.dispatchEvent(new Event('change',{bubbles:true}));`, [label, String(value)]);
    assert(!await b.js('return document.querySelector("[role=dialog]").textContent.includes("Municipio o destino")'), 'Pide municipio');
    await cambiarSelect('Terminal destino', fixture.transporte_id);
    await cambiarSelect('Costo de transporte', 'diferido');
    await b.click('Agregar consignatario');
    await b.type('[role=dialog] form input', 'Persona ficticia de registro');
    await b.type('[role=dialog] form label:nth-child(2) input', '3311111111');
    await b.click('Agregar consignatario');
    await b.wait("[...document.querySelectorAll('label')].find(e=>e.textContent.startsWith('Persona que recoge'))?.querySelector('select')?.value > 0");
    await b.click('Editar datos');
    await b.wait('document.body.textContent.includes("Cancelar edición")');
    await b.click('Cancelar edición');
    assert(await b.js('return document.querySelector("[role=dialog] select").value') === String(fixture.transporte_id), 'Perdió terminal al gestionar expediente');
    result.pasos.push('Alta de consignatario contextual y cancelar edición conserva terminal y costo diferido.');
    await b.js(`document.querySelector('button[aria-label="Cerrar registro"]').click()`);
    await editar();
    assert(await b.js('return document.querySelector("[role=dialog] select").value') === String(fixture.transporte_id), 'Perdió borrador');
    result.pasos.push('Cerrar y reabrir recupera borrador sin guardar.');
    for (const width of [1440, 1024, 390]) for (const oscuro of [false, true]) {
        result.medidas.push({ vista: 'registro', tema: oscuro ? 'dark' : 'light', ...await b.shot('registro', width, oscuro) });
        assert(await b.js(`const d=document.querySelector('[role=dialog]');const body=d.querySelector('.gelia-modal-body');return body.scrollWidth<=body.clientWidth+1 && d.scrollWidth<=d.clientWidth+1 && [...d.querySelectorAll('button')].filter(e=>/Guardar solicitud|Enviar consulta/.test(e.textContent)).every(e=>e.getBoundingClientRect().bottom<=innerHeight+1)`), 'Desborde o acciones fuera de pantalla');
    }
    await b.js(`const body=document.querySelector('.registrar-tienda-cuerpo');body.scrollTop=body.scrollHeight;`);
    result.medidas.push({ vista: 'documentos', tema: 'dark', ...await b.shot('documentos', 390, true) });
    await b.js(`const body=document.querySelector('.registrar-tienda-cuerpo');body.scrollTop=0;`);
    await cambiarSelect('Almacén de la mercancía', fixture.almacen_id);
    await b.click('Guardar solicitud');
    await b.wait(`document.body.textContent.includes('Solicitud guardada.')`);
    await b.js(`document.querySelector('button[aria-label="Cerrar registro"]').click()`);
    await b.go('/control-pedidos');
    await b.wait('document.body.textContent.includes("Transporte ficticio Jaguar")');
    assert(!await b.js('return document.body.textContent.includes("Guía generada por la empresa")'), 'Etiqueta municipal incorrecta');
    await editar();
    assert(await b.js('return document.querySelector("[role=dialog] select").value') === String(fixture.transporte_id), 'No reabrió terminal guardada');
    result.pasos.push('Guardar solicitud con botón, recargar listado y reabrir conserva terminal y consignatario; listado muestra Envío municipal.');
    assert(await b.js(`return [...document.querySelectorAll('label')].find(e=>e.textContent.startsWith('Almacén de la mercancía')).querySelector('select').value`) === String(fixture.almacen_id), 'No conservó almacén');
    await b.click('Enviar consulta');
    await b.wait('document.body.textContent.includes("La consulta ya fue enviada.")');
    result.pasos.push('Enviar consulta con botón al almacén seleccionado.');
    result.console = await b.cmd('/log', { type: 'browser' });
    assert(!result.console.some(e => e.level === 'SEVERE'), 'Errores de consola');
    assert(result.medidas.every(m => !m.desborde), 'Desborde horizontal');
    result.resultado = 'aprobado';
} catch(error) {
    result.resultado = 'fallo'; result.error = String(error.stack || error);
    if (b.id) result.console = await b.cmd('/log', { type: 'browser' });
    if (b.id) { fs.writeFileSync(`${output}/fallo.png`, Buffer.from(await b.cmd('/screenshot', null, 'GET'), 'base64')); result.pantalla = await b.js('return document.body.innerText'); }
    process.exitCode = 1;
} finally {
    fs.writeFileSync(`${output}/resultado.json`, JSON.stringify(result, null, 2));
    if (b.id) await b.cmd('', null, 'DELETE');
    console.log(JSON.stringify(result, null, 2));
}
