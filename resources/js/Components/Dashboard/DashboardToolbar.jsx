import React from 'react';
import { Move, Settings2, Sparkles } from 'lucide-react';
import { DASHBOARD_PRESETS } from './dashboardLayoutUtils';
import {
    GELIA_BTN_OUTLINE,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK_COMPACT,
} from '../../utils/geliaTheme';

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
        <div
            className="dashboard-toolbar flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-between gap-3 animate-page-reveal"
            role="toolbar"
            aria-label="Controles del panel"
        >
            {!isMobile && onPresetChange && (
                <div className={GELIA_SEGMENT_TABS_SCROLL}>
                    <div
                        className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT} p-1 shadow-sm`}
                        role="tablist"
                        aria-label="Vista del panel"
                    >
                        {PRESET_OPTIONS.map(({ id, label }) => (
                            <button
                                key={id}
                                type="button"
                                role="tab"
                                aria-selected={preset === id}
                                data-active={preset === id}
                                onClick={() => onPresetChange(id)}
                                className="gelia-segment-btn whitespace-nowrap min-h-[44px]"
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className="flex flex-wrap items-center justify-end gap-2 sm:ml-auto">
                {!isMobile && (
                    <button
                        type="button"
                        onClick={onAutoAdjust}
                        title="Reorganizar automáticamente los contenedores"
                        className={`${GELIA_BTN_OUTLINE} min-h-[44px]`}
                    >
                        <Sparkles className="w-3.5 h-3.5 shrink-0" aria-hidden />
                        Autoajuste
                    </button>
                )}
                {!isMobile && (
                    <button
                        type="button"
                        onClick={onOrganize}
                        aria-pressed={editLayoutMode}
                        className={`${GELIA_BTN_OUTLINE} min-h-[44px] ${
                            editLayoutMode ? 'border-[var(--color-primario)] theme-text-main' : ''
                        }`}
                    >
                        <Move className="w-3.5 h-3.5 shrink-0" aria-hidden />
                        Organizar
                    </button>
                )}
                <button type="button" onClick={onConfigure} className={`${GELIA_BTN_OUTLINE} min-h-[44px]`}>
                    <Settings2 className="w-3.5 h-3.5 shrink-0" aria-hidden />
                    Configurar
                </button>
            </div>
        </div>
    );
}
