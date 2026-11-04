import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import path from 'node:path';
import os from 'node:os';
const root = path.resolve(import.meta.dirname, '../../..');
export default defineConfig({
    root,
    plugins: [laravel({ input: ['resources/css/app.css'], hotFile: path.join(os.tmpdir(), 'gelia-tienda-qa.hot'), refresh: false }), react(), tailwindcss()],
    server: { host: '127.0.0.1', port: 5185, strictPort: true, allowedHosts: ['laravel.test'] },
});
