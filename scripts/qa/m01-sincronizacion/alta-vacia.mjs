import fs from 'node:fs';
import { Browser, fixture, output, assert } from '../m01-continuidad/navegadorComun.mjs';
const b = new Browser();
const resultado = { pasos: [], medidas: [] };
try {
    const estado=(await(await fetch('http://selenium:4444/status')).json()).value;
    assert(!estado.nodes.some(n=>n.slots.some(s=>s.session)), 'Selenium ocupado');
    b.id=(await b.cmd('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']},'goog:loggingPrefs':{browser:'ALL'}}}})).sessionId;
    await b.cmd('/timeouts',{script:30000,pageLoad:30000});
    await b.go('/login');await b.type('input[type=text]',fixture.email);await b.type('input[type=password]','M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()');await b.wait('!location.pathname.includes("/login")');
    await b.go('/control-pedidos');
    await b.js(`window.Echo?.disconnect();window.__qaPosts=[];window.axios.interceptors.response.use(r=>{if(r.config.method==='post'&&r.config.url.includes('control-pedidos'))window.__qaPosts.push(r.config.url);return r})`);
    await b.click('Nuevo pedido');await b.wait('!!document.getElementById("modalidad-tienda")');
    for (const modalidad of ['municipal','tienda','nacional']) {
        await b.js(`const e=document.getElementById('modalidad-tienda');e.value=arguments[0];e.dispatchEvent(new Event('change',{bubbles:true}));`,[modalidad]);
        await new Promise(resolve=>setTimeout(resolve,1100));
        assert((await b.js('return window.__qaPosts')).length===0, 'Elegir '+modalidad+' persistió sin captura');
        resultado.pasos.push('Selección aislada de '+modalidad+' sin POST.');
    }
    await b.wait('!document.getElementById("modalidad-tienda") && !!document.querySelector("[role=dialog]")');
    await new Promise(resolve=>setTimeout(resolve,17000));
    resultado.posts=await b.js('return window.__qaPosts');
    assert(resultado.posts.length===0,'El formulario legado autoguardó el Nacional vacío');
    for (const [ancho, oscuro] of [[1440,true],[1024,true],[390,false]]) resultado.medidas.push(await b.shot('alta-nacional-vacia',ancho,oscuro));
    resultado.console=await b.cmd('/log',{type:'browser'});
    assert(!resultado.console.some(e=>e.level==='SEVERE'),'Errores de navegador');
    assert(resultado.medidas.every(m=>!m.desborde),'Desbordamiento de página');
    resultado.resultado='aprobado';
} catch(error) {
    resultado.resultado='fallo';resultado.error=String(error.stack||error);process.exitCode=1;
    if(b.id){resultado.pantalla=await b.js('return document.body.innerText').catch(()=>null);resultado.console=[...(resultado.console||[]),...await b.cmd('/log',{type:'browser'}).catch(()=>[])];}
} finally {
    fs.writeFileSync(`${output}/alta-vacia.json`,JSON.stringify(resultado,null,2));if(b.id)await b.cmd('',null,'DELETE');console.log(JSON.stringify(resultado,null,2));
}
