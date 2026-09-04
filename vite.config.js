import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
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
            }),
            react(),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
        test: {
            environment: 'node',
            include: ['resources/js/**/*.test.js'],
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