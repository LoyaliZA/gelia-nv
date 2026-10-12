import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { archivosDeManifiesto } from '../resources/js/utils/retainViteAssets.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const buildDir = path.join(root, 'public/build');
const deployDir = path.join(root, 'storage/framework/deploy');
fs.mkdirSync(deployDir, { recursive: true });
const stageDir = fs.mkdtempSync(path.join(deployDir, 'build-'));
const manifestPath = path.join(buildDir, 'manifest.json');
const readJson = (file) => fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : null;
const validate = (directory, manifest) => {
    if (!manifest?.['resources/js/app.jsx'] || !manifest?.['resources/css/app.css']) throw new Error('El manifiesto no contiene las entradas de la aplicación.');
    for (const relative of archivosDeManifiesto(manifest)) {
        const target = path.resolve(directory, relative);
        if (!target.startsWith(path.resolve(directory) + path.sep) || !fs.existsSync(target)) throw new Error(`Falta el archivo compilado: ${relative}`);
    }
};

try {
    const previous = readJson(manifestPath);
    const result = spawnSync(process.execPath, [path.join(root, 'node_modules/vite/bin/vite.js'), 'build', '--outDir', stageDir, '--emptyOutDir'], { cwd: root, stdio: 'inherit' });
    if (result.status !== 0) process.exitCode = result.status || 1;
    else {
        const manifest = readJson(path.join(stageDir, 'manifest.json'));
        validate(stageDir, manifest);
        fs.mkdirSync(buildDir, { recursive: true });
        fs.cpSync(path.join(stageDir, 'assets'), path.join(buildDir, 'assets'), { recursive: true });
        // Publish assets first. Existing assets stay available to already-open pages.
        for (const relative of archivosDeManifiesto(manifest)) {
            const target = path.join(buildDir, relative);
            fs.mkdirSync(path.dirname(target), { recursive: true });
            fs.copyFileSync(path.join(stageDir, relative), target);
        }
        validate(buildDir, manifest);
        if (previous) fs.writeFileSync(path.join(deployDir, 'manifest.previous.json'), JSON.stringify(previous));
        const pending = path.join(buildDir, 'manifest.next.json');
        fs.writeFileSync(pending, JSON.stringify(manifest, null, 2));
        fs.renameSync(pending, manifestPath);
        // Switch from the development server only after the build is complete and verified.
        const hotFile = path.join(root, 'public/hot');
        if (fs.existsSync(hotFile)) fs.renameSync(hotFile, path.join(deployDir, 'hot.previous'));
        process.stdout.write('Compilación publicada: manifiesto completo y archivos verificados.\n');
    }
} catch (error) {
    process.stderr.write(`No se publicó la compilación: ${error.message}\n`);
    process.exitCode = 1;
} finally {
    fs.rmSync(stageDir, { recursive: true, force: true });
}
