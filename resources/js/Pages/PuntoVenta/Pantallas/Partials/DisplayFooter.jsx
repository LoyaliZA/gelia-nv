import React from 'react';
import { HeartHandshake } from 'lucide-react';

export default function DisplayFooter() {
    return (
        <footer
            className="flex items-center justify-between gap-4 rounded-[1.5rem] border theme-border theme-surface px-5 py-3"
            style={{ boxShadow: 'var(--theme-shadow-card)' }}
        >
            <div className="flex min-w-0 items-center gap-3">
                <HeartHandshake className="h-5 w-5 shrink-0 theme-text-primario" aria-hidden />
                <p className="truncate text-sm theme-text-muted">
                    Gracias por tu paciencia. Estamos para ayudarte.
                </p>
            </div>
            <p className="shrink-0 whitespace-nowrap text-xs font-black uppercase tracking-[0.18em] theme-text-main">
                Una atención más humana, es posible.
                <span
                    className="ml-3 inline-block h-2 w-2 rounded-full align-middle"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                    aria-hidden
                />
            </p>
        </footer>
    );
}
