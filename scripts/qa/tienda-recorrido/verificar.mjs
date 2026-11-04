// Ejecutar en el contenedor de aplicación, con Selenium y Vite de QA activos.
// Solo se publican capturas después de validar todas las vistas.
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
import fs from 'node:fs';
import path from 'node:path';
const destino=path.resolve('resources/design-references/tienda-recorrido');
const temporal='/tmp/tienda-capturas-validas';
fs.mkdirSync(temporal,{recursive:true});
const status=(await (await fetch('http://selenium:4444/status')).json()).value;
let selenium=status.nodes.flatMap(n=>n.slots).map(s=>s.session).find(Boolean);
if(!selenium){
 const response=await fetch('http://selenium:4444/session',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({capabilities:{alwaysMatch:{browserName:'chrome','se:name':'gelia-tienda-qa','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']}}}})});
 selenium=(await response.json()).value;
}
const browser=await chromium.connectOverCDP(selenium.capabilities['se:cdp']);
const report=[];
try {
 for(const size of [{name:'escritorio',width:1440,height:1000},{name:'movil',width:390,height:844}]) {
  for(const theme of ['claro','oscuro']) {
   for(const view of ['solicitud','importes','revision','espera','anexo','retiro','entrega','cedis','auditar']) {
    const page=await browser.newPage({viewport:{width:size.width,height:size.height}});
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto(`http://laravel.test:5186/scripts/qa/tienda-recorrido/preview.html?vista=${view}&tema=${theme}${theme==='claro'?'&personalizado=1':''}`,{waitUntil:'networkidle'});
    await page.waitForSelector('.gelia-pedidos-bma',{timeout:20000});
    const text=await page.locator('body').innerText();
    if(/Blocked request|host.*not allowed|Internal Server Error|Failed to resolve import/i.test(text)||errors.length) throw new Error('Vista inválida '+view+': '+text.slice(0,160)+' '+errors.join(';'));
    const expected={solicitud:'Datos del pedido',importes:'Importes del pedido',revision:'Indique el estado',espera:'Respuesta enviada',anexo:'Anexo por atender',retiro:'Retiro por atender',cedis:'Respuesta de disponibilidad',auditar:'Cliente',entrega:'Pago y entrega en tienda'}[view];
    if(!text.includes(expected))throw new Error('No se renderizó '+view+': '+text.slice(0,220));
    if(view==='importes')await page.locator('.registrar-tienda-importes-pendiente').scrollIntoViewIfNeeded();
    if(view==='anexo')await page.getByRole('heading',{name:'Anexo por atender',exact:true}).scrollIntoViewIfNeeded();
    if(view==='entrega')await page.getByRole('heading',{name:'Pago y entrega en tienda',exact:true}).scrollIntoViewIfNeeded();
    if(view==='retiro')await page.getByRole('heading',{name:'Retiro por atender',exact:true}).scrollIntoViewIfNeeded();
    const result=await page.evaluate(()=>({scroll:document.documentElement.scrollWidth,width:innerWidth,text:document.body.innerText.slice(0,150),buttons:[...document.querySelectorAll('button')].filter(b=>b.getBoundingClientRect().width>0).map(b=>b.textContent)}));
    const rotas=await page.locator('img').evaluateAll(imgs=>imgs.filter(img=>img.getBoundingClientRect().width>0 && (!img.complete || img.naturalWidth===0)).map(img=>img.src));
    if(rotas.length) throw new Error('Documento sin renderizar '+view+': '+rotas.join(','));
    if(result.scroll>result.width)throw new Error('Desbordamiento horizontal: '+view+' '+size.name);
    const desbordados=await page.locator('.gelia-tienda-embebida, .gelia-preparacion-ajustes, .gelia-tienda-salida').evaluateAll(elements=>elements.filter(el=>el.clientWidth>0 && el.scrollWidth>el.clientWidth+1).map(el=>({clase:el.className,ancho:el.clientWidth,contenido:el.scrollWidth})));
    if(desbordados.length)throw new Error('Desbordamiento dentro del modal '+view+' '+size.name+': '+JSON.stringify(desbordados));

    const controlesFuera=await page.locator('.gelia-preparacion-ajustes').evaluateAll(groups=>groups.flatMap(group=>[...group.querySelectorAll('input,textarea,select,button')].filter(el=>{
        const rect=el.getBoundingClientRect();const panel=el.closest('.registrar-tienda-panel')?.getBoundingClientRect();
        return panel && rect.width>0 && (rect.right>panel.right+1 || rect.left<panel.left-1);
    }).map(el=>el.id || el.textContent.slice(0,50))));
    if(controlesFuera.length)throw new Error('Controles recortados '+view+' '+size.name+': '+controlesFuera.join(','));
    await page.screenshot({path:`${temporal}/${view}-${size.name}-${theme}.png`,fullPage:true});
    report.push({view,size:size.name,theme,errors,...result});
    await page.close();
   }
  }
 }
 fs.mkdirSync(destino,{recursive:true});
 for(const row of report){const file=`${row.view}-${row.size}-${row.theme}.png`;fs.copyFileSync(`${temporal}/${file}`,`${destino}/${file}`);}
 fs.writeFileSync(`${destino}/verificacion.json`,JSON.stringify(report,null,2));
 console.log(JSON.stringify({capturas:report.length,errores:report.flatMap(r=>r.errors),salida:destino}));
} finally {
 await browser.close().catch(()=>{});
 await fetch('http://selenium:4444/session/'+selenium.sessionId,{method:'DELETE'});
}
