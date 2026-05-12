import React from 'react';
import './bootstrap';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
// Importamos el helper oficial de Laravel-Vite
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    // Utilizamos el estándar de la industria para resolver componentes de forma segura
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.jsx`, 
        import.meta.glob('./Pages/**/*.jsx')
    ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});