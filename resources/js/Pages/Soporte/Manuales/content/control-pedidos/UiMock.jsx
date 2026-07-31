import React from 'react';

/** Marco ilustrativo de pantalla (no captura de producción). */
export default function UiMock({ titulo, anotaciones = [], children }) {
    return (
        <figure className="rounded-2xl border theme-border overflow-hidden theme-element my-4">
            <div
                className="flex items-center gap-2 px-4 py-2.5 border-b theme-border"
                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 8%, transparent)' }}
            >
                <span className="flex gap-1.5" aria-hidden>
                    <span className="w-2.5 h-2.5 rounded-full bg-black/15 dark:bg-white/20" />
                    <span className="w-2.5 h-2.5 rounded-full bg-black/15 dark:bg-white/20" />
                    <span className="w-2.5 h-2.5 rounded-full bg-black/15 dark:bg-white/20" />
                </span>
                <figcaption className="text-[10px] font-black uppercase tracking-widest theme-text-muted truncate">
                    Ejemplo · {titulo}
                </figcaption>
            </div>
            <div className="p-4 md:p-5 space-y-3 min-h-[7rem]">
                {children}
                {anotaciones.length > 0 && (
                    <ol className="m-0 pl-4 space-y-1.5 text-xs theme-text-muted list-decimal">
                        {anotaciones.map((a) => (
                            <li key={a} className="leading-relaxed">{a}</li>
                        ))}
                    </ol>
                )}
            </div>
        </figure>
    );
}

export function MockRow({ label, accent = false }) {
    return (
        <div
            className={`flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 border theme-border ${
                accent ? 'theme-surface' : 'bg-transparent'
            }`}
        >
            <span className="text-[11px] font-bold theme-text-main truncate">{label}</span>
            <span
                className="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md shrink-0"
                style={{
                    color: 'var(--color-primario)',
                    backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)',
                }}
            >
                Acción
            </span>
        </div>
    );
}
