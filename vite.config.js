import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
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
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.js'],
    },
    server: {
        host: '0.0.0.0', // Permite conexiones desde fuera del contenedor
        cors: true,      // Habilita las políticas de CORS
        hmr: {
            host: '100.75.11.59', // Fuerza a Vite a usar tu IP real en lugar de localhost
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});