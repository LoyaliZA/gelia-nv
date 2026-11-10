import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../../..');
export default defineConfig({
    root,
    plugins: [react(), tailwindcss()],
    build: { outDir: '/tmp/gelia-wizerp-preview', emptyOutDir: true, rolldownOptions: { input: path.resolve(import.meta.dirname, 'preview.html') } },
});
