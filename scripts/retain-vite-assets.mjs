import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { archivosDeManifiesto } from '../resources/js/utils/retainViteAssets.js';
import { servidorHotActivo } from './servidor-hot.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const buildDir = path.join(root, 'public/build');
const deployDir = path.join(root, 'storage/framework/deploy');
fs.mkdirSync(deployDir, { recursive: true });
const stageDir = fs.mkdtempSync(path.join(deployDir, 'build-'));
const manifestPath = path.join(buildDir, 'manifest.json');
const readJson = (file) => fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : null;
// Replace directory entries instead of overwriting files left by another build user.
// In particular, cpSync may chmod an existing root-owned asset and fail with EACCES.
const publishFile = (source, target) => {
    fs.mkdirSync(path.dirname(target), { recursive: true });
    const temporary = `${target}.${process.pid}.next`;
    try {
        fs.copyFileSync(source, temporary);
        fs.renameSync(temporary, target);
    } finally {
        fs.rmSync(temporary, { force: true });
    }
};
const validate = (directory, manifest) => {
    if (!manifest?.['resources/js/app.jsx'] || !manifest?.['resources/css/app.css']) throw new Error('El manifiesto no contiene las entradas de la aplicación.');
    for (const relative of archivosDeManifiesto(manifest)) {
        const target = path.resolve(directory, relative);
        if (!target.startsWith(path.resolve(directory) + path.sep) || !fs.existsSync(target)) throw new Error(`Falta el archivo compilado: ${relative}`);
    }
};
await (async () => { try {
    const previous = readJson(manifestPath);
    const result = spawnSync(process.execPath, [path.join(root, 'node_modules/vite/bin/vite.js'), 'build', '--outDir', stageDir, '--emptyOutDir'], { cwd: root, stdio: 'inherit' });
    if (result.status !== 0) process.exitCode = result.status || 1;
    else {
        const manifest = readJson(path.join(stageDir, 'manifest.json'));
        validate(stageDir, manifest);
        fs.mkdirSync(buildDir, { recursive: true });
        // Publish assets first. Existing assets stay available to already-open pages.
        for (const relative of archivosDeManifiesto(manifest)) {
            const target = path.join(buildDir, relative);
            publishFile(path.join(stageDir, relative), target);
        }
        validate(buildDir, manifest);
        if (previous) publishFile(manifestPath, path.join(deployDir, 'manifest.previous.json'));
        publishFile(path.join(stageDir, 'manifest.json'), manifestPath);
        const manifestsDir = path.join(buildDir, 'manifests');
        fs.mkdirSync(manifestsDir, { recursive: true });
        const sha7 = (process.env.DEPLOY_SHA || 'local00').replace(/[^A-Za-z0-9]/g, '').slice(0, 7) || 'local00';
        const sello = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
        publishFile(manifestPath, path.join(manifestsDir, `${sello}-${sha7}.json`));
        // Solo desconectar Vite si ya no acepta conexiones. Si sigue en marcha, public/hot
        // mantiene la recarga en vivo; quitarlo obliga a Laravel a usar el manifiesto.
        const hotFile = path.join(root, 'public/hot');
        if (fs.existsSync(hotFile) && !await servidorHotActivo(fs.readFileSync(hotFile, 'utf8'))) {
            fs.renameSync(hotFile, path.join(deployDir, 'hot.previous'));
        }
        process.stdout.write('Compilación publicada: manifiesto completo y archivos verificados.\n');
    }
} catch (error) {
    process.stderr.write(`No se publicó la compilación: ${error.message}\n`);
    process.exitCode = 1;
} finally {
    fs.rmSync(stageDir, { recursive: true, force: true });
} })();
