import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [react(), tailwindcss()],
    resolve: { alias: { '@': path.resolve('resources/js') } },
    server: {
        host: '0.0.0.0',
        port: 5179,
        strictPort: true,
        allowedHosts: ['laravel.test', 'localhost', '127.0.0.1'],
        cors: true,
    },
});
