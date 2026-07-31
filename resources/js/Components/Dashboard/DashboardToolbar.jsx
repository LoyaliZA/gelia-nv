import React from 'react';
import { Move, Settings2, Sparkles } from 'lucide-react';
import { DASHBOARD_PRESETS } from './dashboardLayoutUtils';

const PRESET_OPTIONS = [
    { id: DASHBOARD_PRESETS.OPERATIVO, label: 'Operativo' },
    { id: DASHBOARD_PRESETS.COMERCIAL, label: 'Comercial' },
    { id: DASHBOARD_PRESETS.LAUNCHER, label: 'Accesos' },
];

export default function DashboardToolbar({
    editLayoutMode,
    isMobile,
    onOrganize,
    onConfigure,
    onAutoAdjust,
    preset = DASHBOARD_PRESETS.OPERATIVO,
    onPresetChange,
}) {
    return (
        <div className="dashboard-toolbar flex flex-wrap items-center justify-between gap-3 animate-page-reveal">
            {!isMobile && onPresetChange && (
                <div className="flex items-center gap-1 p-1 rounded-xl theme-surface border theme-border shadow-sm">
                    {PRESET_OPTIONS.map(({ id, label }) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => onPresetChange(id)}
                            className={`px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest outline-none transition-colors ${
                                preset === id
                                    ? 'theme-text-main'
                                    : 'theme-text-muted hover:theme-text-main'
                            }`}
                            style={preset === id ? { backgroundColor: 'color-mix(in srgb, var(--color-primario) 18%, transparent)' } : undefined}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-end gap-2 ml-auto">
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
        </div>
    );
}
