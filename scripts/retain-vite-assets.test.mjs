import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

function proyecto() {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'gelianv-build-test-'));
    for (const file of ['scripts/retain-vite-assets.mjs', 'resources/js/utils/retainViteAssets.js']) {
        fs.mkdirSync(path.dirname(path.join(dir, file)), { recursive: true });
        fs.copyFileSync(file, path.join(dir, file));
    }
    fs.mkdirSync(path.join(dir, 'public/build/assets'), { recursive: true });
    fs.mkdirSync(path.join(dir, 'node_modules/vite/bin'), { recursive: true });
    fs.writeFileSync(path.join(dir, 'package.json'), '{"type":"module"}');
    fs.writeFileSync(path.join(dir, 'public/build/manifest.json'), '{"publicado":"anterior"}');
    fs.writeFileSync(path.join(dir, 'public/build/assets/anterior.js'), 'anterior');
    fs.writeFileSync(path.join(dir, 'public/hot'), 'http://localhost:5179');
    return dir;
}
function ejecutar(dir, compilador) {
    fs.writeFileSync(path.join(dir, 'node_modules/vite/bin/vite.js'), compilador);
    return spawnSync(process.execPath, ['scripts/retain-vite-assets.mjs'], { cwd: dir, encoding: 'utf8' });
}
test('un fallo de compilación o un manifiesto incompleto conserva la publicación y hot', () => {
    const dir = proyecto();
    try {
        assert.equal(ejecutar(dir, 'process.exit(2);').status, 2);
        const incompleto = `import fs from 'node:fs'; const stage=process.argv[process.argv.indexOf('--outDir')+1]; fs.writeFileSync(stage+'/manifest.json', JSON.stringify({'resources/js/app.jsx':{file:'assets/falta.js'},'resources/css/app.css':{file:'assets/falta.css'}}));`;
        assert.equal(ejecutar(dir, incompleto).status, 1);
        assert.equal(fs.readFileSync(path.join(dir, 'public/build/manifest.json'), 'utf8'), '{"publicado":"anterior"}');
        assert.equal(fs.readFileSync(path.join(dir, 'public/build/assets/anterior.js'), 'utf8'), 'anterior');
        assert.ok(fs.existsSync(path.join(dir, 'public/hot')));
        assert.equal(fs.readdirSync(path.join(dir, 'storage/framework/deploy')).filter(f => f.startsWith('build-')).length, 0);
    } finally { fs.rmSync(dir, { recursive: true, force: true }); }
});
test('publica entradas, dependencias y recursos completos conservando archivos anteriores', () => {
    const dir = proyecto();
    try {
        const valido = `import fs from 'node:fs'; const stage=process.argv[process.argv.indexOf('--outDir')+1]; fs.mkdirSync(stage+'/assets'); for(const file of ['app.js','app.css','foto.svg','worker.js'])fs.writeFileSync(stage+'/assets/'+file,file); fs.writeFileSync(stage+'/manifest.json',JSON.stringify({'resources/js/app.jsx':{file:'assets/app.js',assets:['assets/foto.svg'],imports:['worker']},'resources/css/app.css':{file:'assets/app.css'},worker:{file:'assets/worker.js'}}));`;
        const result = ejecutar(dir, valido);
        assert.equal(result.status, 0, result.stderr);
        const manifest = JSON.parse(fs.readFileSync(path.join(dir, 'public/build/manifest.json')));
        assert.equal(manifest['resources/js/app.jsx'].file, 'assets/app.js');
        for (const file of ['app.js', 'app.css', 'foto.svg', 'worker.js', 'anterior.js']) assert.ok(fs.existsSync(path.join(dir, 'public/build/assets', file)));
        assert.ok(!fs.existsSync(path.join(dir, 'public/hot')));
        assert.ok(fs.existsSync(path.join(dir, 'storage/framework/deploy/hot.previous')));
        assert.deepEqual(JSON.parse(fs.readFileSync(path.join(dir, 'storage/framework/deploy/manifest.previous.json'))), { publicado: 'anterior' });
    } finally { fs.rmSync(dir, { recursive: true, force: true }); }
});
