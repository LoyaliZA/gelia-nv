import React from 'react';
import { Move, Settings2, Sparkles } from 'lucide-react';

export default function DashboardToolbar({
    editLayoutMode,
    isMobile,
    onOrganize,
    onConfigure,
    onAutoAdjust,
}) {
    return (
        <div className="dashboard-toolbar flex flex-wrap items-center justify-end gap-2 animate-page-reveal">
            {!isMobile && (
                <button
                    type="button"
                    onClick={onAutoAdjust}
                    title="Reorganizar automáticamente los contenedores"
                    className="flex items-center gap-2 px-3 py-2 rounded-xl theme-surface border theme-border hover:border-[var(--color-primario)] transition-colors theme-text-muted hover:theme-text-main text-[9px] font-black uppercase tracking-widest shadow-sm outline-none"
                >
                    <Sparkles className="w-3.5 h-3.5" /> Autoajuste
                </button>
            )}
            {!isMobile && (
                <button
                    type="button"
                    onClick={onOrganize}
                    className={`flex items-center gap-2 px-3 py-2 rounded-xl theme-surface border theme-border transition-colors text-[9px] font-black uppercase tracking-widest shadow-sm outline-none ${editLayoutMode ? 'border-[var(--color-primario)] theme-text-main' : 'theme-text-muted hover:theme-text-main hover:border-[var(--color-primario)]'}`}
                >
                    <Move className="w-3.5 h-3.5" /> Organizar
                </button>
            )}
            <button
                type="button"
                onClick={onConfigure}
                className="flex items-center gap-2 px-3 py-2 rounded-xl theme-surface border theme-border hover:border-[var(--color-primario)] transition-colors theme-text-muted hover:theme-text-main text-[9px] font-black uppercase tracking-widest shadow-sm outline-none"
            >
                <Settings2 className="w-3.5 h-3.5" /> Configurar
            </button>
        </div>
    );
}
