import fs from 'node:fs';
export const root='http://qa-pr34';
export const output='/var/www/html/storage/app/qa-pr34-aceptacion';
fs.mkdirSync(output,{recursive:true});
export const fixtures=JSON.parse(fs.readFileSync('/var/www/html/storage/app/qa-pr34-fixtures.json','utf8'));
export class Browser {
 async cmd(path,body,method='POST') {
  const r=await fetch('http://selenium:4444/session'+(this.id?'/'+this.id:'')+path,{method,...(body?{headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}:{})});
  const d=await r.json();if(d.value?.error)throw Error(JSON.stringify(d.value));return d.value;
 }
 async start(actor) {
  this.id=(await this.cmd('',{capabilities:{alwaysMatch:{browserName:'chrome','se:name':'PR34-'+actor,'goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']},'goog:loggingPrefs':{browser:'ALL',performance:'ALL'}}}})).sessionId;
  await this.cmd('/timeouts',{script:60000,pageLoad:60000,implicit:0});
  await this.cmd('/url',{url:root+'/login'});await this.wait("document.querySelector('input[type=password]')");
  for(const [selector,value] of [['input[type=text]',fixtures.usuarios[actor].email],['input[type=password]',process.env.QA_LOGIN_PASSWORD]]) {
   const e=await this.cmd('/element',{using:'css selector',value:selector});await this.cmd('/element/'+e['element-6066-11e4-a52e-4f735466cecf']+'/value',{text:value});
  }
  await this.js("document.querySelector('button[type=submit]').click()");
  await this.wait("!location.pathname.includes('/login') && !!window.Echo");
  return this;
 }
 js(script,args=[]) {return this.cmd('/execute/sync',{script,args});}
 async wait(condition) {
  const ok=await this.cmd('/execute/async',{script:`const done=arguments[arguments.length-1];let n=0;function ver(){try{if(${condition})return done(true)}catch{}if(++n>150)return done(false);setTimeout(ver,100)}ver()`,args:[]});
  if(!ok)throw Error('No se cumplió: '+condition+' '+await this.js('return document.body.innerText.slice(-1200)'));
 }
 async api(url,method='GET',data=null,files=[]) {
  return this.cmd('/execute/async',{script:`const [url,method,data,files]=arguments;const done=arguments[arguments.length-1];let payload=data;
   if(files.length){payload=new FormData();for(const [k,v] of Object.entries(data||{}))payload.append(k,typeof v==='object'?JSON.stringify(v):String(v));for(const f of files){const b=f.base64?Uint8Array.from(atob(f.base64),c=>c.charCodeAt(0)):new Uint8Array(f.size);if(!f.base64)b.fill(f.fill||32);const header=new TextEncoder().encode('%PDF-1.4\\n% QA PR34 '+f.name+'\\n%%EOF\\n');if(!f.base64)b.set(header.slice(0,b.length));payload.append(f.field||'evidencias[]',new File([b],f.name,{type:f.mime||'application/pdf'}));}}
   window.axios({url,method,data:payload,headers:{Accept:'application/json'}}).then(r=>done({status:r.status,data:r.data})).catch(e=>done({status:e.response?.status||0,data:e.response?.data||String(e)}));`,args:[url,method,data,files]});
 }
 async go(path){await this.cmd('/url',{url:root+path});await this.wait('document.readyState === "complete" && !!window.axios');}
 async click(text){await this.js(`const b=[...document.querySelectorAll('button,a')].find(e=>e.textContent.trim()===arguments[0]);if(!b)throw Error('No existe '+arguments[0]);b.click()`,[text]);}
 async shot(name,width=1440,theme='light') {
  await this.cmd('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width,height:width===390?844:1000,deviceScaleFactor:1,mobile:width===390}});
  await this.js("localStorage.setItem('theme',arguments[0]);document.documentElement.classList.toggle('dark',arguments[0]==='dark')",[theme]);
  await this.wait('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');
  fs.writeFileSync(output+'/'+name+'.png',Buffer.from(await this.cmd('/screenshot',null,'GET'),'base64'));
 }
 async close(){if(this.id){await this.cmd('',null,'DELETE');this.id=null;}}
}
export function assert(ok,message,detail){if(!ok)throw Error(message+' '+JSON.stringify(detail??''));}
export function save(name,data){fs.writeFileSync(output+'/'+name+'.json',JSON.stringify(data,null,2));}
