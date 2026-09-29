import React from 'react';
import { HeartHandshake } from 'lucide-react';

export default function DisplayFooter() {
    return (
        <footer
            className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border theme-border theme-surface px-3 py-2 sm:gap-4 sm:rounded-[1.5rem] sm:px-5 sm:py-3"
            style={{ boxShadow: 'var(--theme-shadow-card)' }}
        >
            <div className="flex min-w-0 items-center gap-3">
                <HeartHandshake className="h-5 w-5 shrink-0 theme-text-primario" aria-hidden />
                <p className="min-w-0 text-sm theme-text-muted">
                    Gracias por tu paciencia. Estamos para ayudarte.
                </p>
            </div>
            <p className="text-xs font-black uppercase tracking-[0.12em] theme-text-main sm:tracking-[0.18em]">
                La mejor atención, es posible.
                <span
                    className="ml-3 inline-block h-2 w-2 rounded-full align-middle"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                    aria-hidden
                />
            </p>
        </footer>
    );
}
