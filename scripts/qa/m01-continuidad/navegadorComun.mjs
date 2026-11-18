import fs from 'node:fs';
const root = 'http://laravel.test:9193';
const output = process.env.GELIA_QA_OUTPUT || '/tmp/gelia-m01-continuidad';
const fixture = JSON.parse(fs.readFileSync(process.env.GELIA_QA_FIXTURE || '/tmp/m01-fixtures.json'));
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
    async click(text) { await this.wait(`[...document.querySelectorAll('button,a')].some(e=>!e.disabled&&e.textContent.trim()===${JSON.stringify(text)})`); await this.js('const e=[...document.querySelectorAll("button,a")].find(e=>!e.disabled&&e.textContent.trim()===arguments[0]);if(!e)throw Error("No existe "+arguments[0]);e.click()', [text]); }
    async type(selector, text) {
        const element = (await this.cmd('/element', { using: 'css selector', value: selector }))['element-6066-11e4-a52e-4f735466cecf'];
        await this.cmd(`/element/${element}/clear`, {});
        await this.cmd(`/element/${element}/value`, { text });
    }
    async shot(name, width, oscuro) {
        await this.cmd('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width, height: 1000, deviceScaleFactor: 1, mobile: width === 390 } });
        await this.js('document.documentElement.classList.toggle("dark",arguments[0]);localStorage.setItem("theme",arguments[0]?"dark":"light")', [oscuro]);
        await this.wait('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');
        fs.writeFileSync(`${output}/${name}-${width}-${oscuro ? 'dark' : 'light'}.png`, Buffer.from(await this.cmd('/screenshot', null, 'GET'), 'base64'));
        return this.js('return {ancho:innerWidth,desborde:document.documentElement.scrollWidth>innerWidth+1}');
    }
}

export { Browser, fixture, output, assert };
export async function crearArchivoQA(b) {
    const png = Buffer.from(await b.js(`const c=document.createElement('canvas');c.width=800;c.height=1000;const x=c.getContext('2d');x.fillStyle='white';x.fillRect(0,0,800,1000);x.fillStyle='black';x.font='22px sans-serif';['PEDIDO WIZERP - FICTICIO PARA QA','WIZ-CONTINUIDAD','Cliente ficticio de continuidad','SIN PAGO - DOCUMENTO SINTETICO','Mercancia:','Pieza ficticia A - 1 unidad','Pieza ficticia B - 1 unidad','CEDIS: revisar conjunto y registrar excepciones'].forEach((t,i)=>x.fillText(t,40,70+i*65));return c.toDataURL('image/png').split(',')[1]`),'base64');
    // ZIP sin compresión para el endpoint estándar de carga de Selenium.
    let crc=0xffffffff;for(const byte of png){crc^=byte;for(let j=0;j<8;j++)crc=(crc>>>1)^((crc&1)?0xedb88320:0);}crc=(crc^0xffffffff)>>>0;
    const name=Buffer.from('m01-qa.png'),local=Buffer.alloc(30),central=Buffer.alloc(46),end=Buffer.alloc(22);
    local.writeUInt32LE(0x04034b50);local.writeUInt16LE(20,4);local.writeUInt32LE(crc,14);local.writeUInt32LE(png.length,18);local.writeUInt32LE(png.length,22);local.writeUInt16LE(name.length,26);
    central.writeUInt32LE(0x02014b50);central.writeUInt16LE(20,4);central.writeUInt16LE(20,6);central.writeUInt32LE(crc,16);central.writeUInt32LE(png.length,20);central.writeUInt32LE(png.length,24);central.writeUInt16LE(name.length,28);
    end.writeUInt32LE(0x06054b50);end.writeUInt16LE(1,8);end.writeUInt16LE(1,10);end.writeUInt32LE(central.length+name.length,12);end.writeUInt32LE(local.length+name.length+png.length,16);
    return await b.cmd('/se/file',{file:Buffer.concat([local,name,png,central,name,end]).toString('base64')});

}
