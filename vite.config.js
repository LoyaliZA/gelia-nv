import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import os from 'node:os';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const vitePort = Number(env.VITE_PORT) || 5173;
    const hmrHost = env.VITE_DEV_HOST || new URL(env.APP_URL || 'http://localhost').hostname;

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.jsx',
                ],
                refresh: true,
                // Vitest arranca otro Vite con este mismo archivo. El plugin borra hotFile al salir;
                // si apunta a public/hot, apaga la recarga en vivo del servidor de desarrollo.
                ...(process.env.VITEST ? { hotFile: path.join(os.tmpdir(), 'gelianv-vitest.hot') } : {}),
            }),
            react(),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
        build: {
            outDir: process.env.VITE_OUT_DIR || 'public/build',
            emptyOutDir: true,
            manifest: 'manifest.json',
        },
        test: {
            environment: 'node',
            include: ['resources/js/**/*.test.{js,jsx}'],
        },
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
            cors: true,
            hmr: {
                host: hmrHost,
                port: vitePort,
                clientPort: vitePort,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
