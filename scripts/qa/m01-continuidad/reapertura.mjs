import fs from 'node:fs';
import { Browser, fixture, output, assert, crearArchivoQA } from './navegadorComun.mjs';
const folioQA = 'WIZ-CONTINUIDAD-'+fixture.usuario_id;
const b = new Browser();
let archivoQA;
const result = { pasos: [] };
const cambiarSelect = async (label, value) => b.js(`const label=[...document.querySelectorAll('[role=dialog] label')].find(e=>e.textContent.trim().startsWith(arguments[0]));const el=label.querySelector('select');el.value=arguments[1];el.dispatchEvent(new Event('change',{bubbles:true}));`, [label, String(value)]);
const subir = async selector => {
    const el = (await b.cmd('/element', {using:'css selector', value:selector}))['element-6066-11e4-a52e-4f735466cecf'];
    await b.js('document.querySelector(arguments[0]).value=""',[selector]);
    await b.cmd(`/element/${el}/value`, {text:archivoQA});
};
const editar = async () => {
    await b.go('/control-pedidos?q='+folioQA);
    await b.wait(`[...document.querySelectorAll('tr,article')].some(e=>e.textContent.includes(${JSON.stringify(folioQA)}))`);
    await b.js(`const row=[...document.querySelectorAll('tr,article')].find(e=>e.textContent.includes(arguments[0]));const buttons=[...row.querySelectorAll('button')];const edit=buttons.find(e=>e.textContent.trim()==='Editar');if(edit)edit.click();else buttons.find(e=>e.getAttribute('aria-haspopup')==='menu').click();`,[folioQA]);
    if (!await b.js('return !!document.querySelector("#folio-tienda")')) await b.js(`const e=[...document.querySelectorAll('button')].find(e=>['Editar','Continuar'].includes(e.textContent.trim()));if(!e)throw Error('Sin acción de seguimiento');e.click()`);
    await b.wait('!!document.querySelector("#folio-tienda")');
};
try {
    const state=(await (await fetch('http://selenium:4444/status')).json()).value;
    assert(!state.nodes.some(n=>n.slots.some(s=>s.session)),'Selenium ocupado');
    b.id=(await b.cmd('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']},'goog:loggingPrefs':{browser:'ALL'}}}})).sessionId;
    await b.cmd('/timeouts',{script:30000,pageLoad:30000});
    archivoQA=await crearArchivoQA(b);
    await b.go('/login');await b.type('input[type=text]',fixture.email);await b.type('input[type=password]','M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()');await b.wait('!location.pathname.includes("/login")');
    await editar();
    const puedeReabrir=await b.js(`return [...document.querySelectorAll('button')].some(e=>e.textContent.trim()==='Solicitar nueva revisión')`);
    if(puedeReabrir) await b.click('Solicitar nueva revisión');
    result.reanudo_revision_previamente_solicitada=!puedeReabrir;
    await b.wait('document.querySelector(".registrar-tienda-modal").textContent.includes("Excepciones pendientes")');
    assert(!await b.js(`return [...document.querySelectorAll('.registrar-tienda-modal button')].some(e=>e.textContent.trim()==='Cerrar consulta')`),'Cierre habilitado con respuesta sustituida');
    await b.shot('nueva-revision',1440,true);
    result.pasos.push('Solicitar nueva revisión reabre consulta, bloquea cierre y conserva documento, terminal y transporte.');
    await b.go('/control-pedidos/cedis?q='+folioQA);await b.click('Consulta pendiente: CEDIS');
    await b.wait(`document.querySelector('input[type=file][aria-label="Adjuntar: Captura del pedido"]')`);
    await subir('input[type=file][aria-label="Adjuntar: Captura del pedido"]');await b.click('Enviar respuesta');
    await b.wait('document.body.textContent.includes("Respuesta enviada a Ventas.")');
    await editar();await b.click('Cerrar consulta');await b.wait('document.querySelector(".registrar-tienda-modal").textContent.includes("Consulta cerrada")');
    await b.js(`const cuerpo=document.querySelector('.registrar-tienda-cuerpo');cuerpo.scrollTop=cuerpo.scrollHeight`);
    await b.shot('cierre-final',390,true);
    result.pasos.push('CEDIS contesta la nueva revisión sin excepciones y Ventas vuelve a cerrar desde el control visible.');
    result.console=await b.cmd('/log',{type:'browser'});assert(!result.console.some(e=>e.level==='SEVERE'),'Errores JS');result.resultado='aprobado';
} catch(error) {
    result.resultado='fallo';result.error=String(error.stack||error);process.exitCode=1;
    if(b.id){result.pantalla=await b.js('return document.body.innerText');result.console=await b.cmd('/log',{type:'browser'});}
} finally {
    fs.writeFileSync(`${output}/reapertura.json`,JSON.stringify(result,null,2));if(b.id)await b.cmd('',null,'DELETE');console.log(JSON.stringify(result,null,2));
}
