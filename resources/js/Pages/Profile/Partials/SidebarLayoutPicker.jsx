import React from 'react';
import { PanelLeft } from 'lucide-react';
import { SIDEBAR_LAYOUT_OPTIONS, isProfessionalSidebarLayout } from '../../../config/sidebarLayouts';
import SidebarLayoutPreview from './SidebarLayoutPreview';

/**
 * Selector visual de tipo de sidebar (preferencias de usuario).
 */
export default function SidebarLayoutPicker({
    value,
    onChange,
    mobileLayout,
    onMobileChange,
    sidebarMode,
    onSidebarModeChange,
    fixedPosition,
    onFixedPositionChange,
}) {
    const isProfessional = isProfessionalSidebarLayout(value);
    const isFixed = value === 'fixed';
    const showLegacyMode = !isProfessional;
    const showFixedPosition = isFixed;

    return (
        <div className="space-y-5 w-full">
            <div>
                <p className="text-sm font-semibold theme-text-main mb-2 flex items-center gap-2">
                    <PanelLeft className="w-4 h-4" aria-hidden />
                    Tipo de sidebar
                </p>
                <p className="text-xs theme-text-muted mb-3">
                    Elige cómo se presenta la navegación. El estilo profesional es una barra fija a la izquierda.
                </p>
                <div className="gelia-sidebar-layout-picker" role="radiogroup" aria-label="Tipo de sidebar">
                    {SIDEBAR_LAYOUT_OPTIONS.map((option) => {
                        const active = value === option.id;
                        return (
                            <button
                                key={option.id}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                data-active={active ? 'true' : 'false'}
                                className="gelia-sidebar-layout-option"
                                onClick={() => onChange(option.id)}
                            >
                                <div className="gelia-sidebar-layout-option__preview">
                                    <SidebarLayoutPreview variant={option.preview} />
                                </div>
                                <p className="gelia-sidebar-layout-option__title">{option.label}</p>
                                <p className="gelia-sidebar-layout-option__desc">{option.description}</p>
                            </button>
                        );
                    })}
                </div>
            </div>

            {!isProfessional && (
                <div>
                    <p className="text-sm font-semibold theme-text-main mb-2">Sidebar en móvil</p>
                    <div className="gelia-segment w-full sm:w-auto p-1 h-12 shadow-sm" role="group" aria-label="Sidebar en móvil">
                        <button
                            type="button"
                            onClick={() => onMobileChange('mobile_bottom')}
                            className="gelia-segment-btn px-4 sm:px-5"
                            data-active={mobileLayout === 'mobile_bottom'}
                        >
                            Barra inferior
                        </button>
                        <button
                            type="button"
                            onClick={() => onMobileChange('mobile_topbar')}
                            className="gelia-segment-btn px-4 sm:px-5"
                            data-active={mobileLayout === 'mobile_topbar'}
                        >
                            Barra superior
                        </button>
                    </div>
                </div>
            )}

            {isProfessional && (
                <p className="text-xs theme-text-muted m-0">
                    En móvil, el estilo profesional usa barra superior y menú lateral (drawer).
                </p>
            )}

            {showFixedPosition && (
                <div>
                    <p className="text-sm font-semibold theme-text-main mb-2">Posición barra fija</p>
                    <div className="gelia-segment w-full sm:w-auto p-1 h-12 shadow-sm flex-wrap sm:flex-nowrap" role="group" aria-label="Posición barra fija">
                        {['left', 'right', 'top', 'bottom'].map((pos) => (
                            <button
                                key={pos}
                                type="button"
                                onClick={() => onFixedPositionChange(pos)}
                                className="gelia-segment-btn px-4 sm:px-5"
                                data-active={fixedPosition === pos}
                            >
                                {{ left: 'Izquierda', right: 'Derecha', top: 'Arriba', bottom: 'Abajo' }[pos]}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {(showLegacyMode || isProfessional) && (
                <div>
                    <p className="text-sm font-semibold theme-text-main mb-2">Estado del sidebar</p>
                    <p className="text-xs theme-text-muted mb-2">
                        {isProfessional
                            ? 'Contraído o desplegado; solo cambia con el botón (sin hover).'
                            : 'Contraído al pasar el mouse o siempre desplegado.'}
                    </p>
                    <div className="gelia-segment w-full sm:w-auto p-1 h-12 shadow-sm" role="group" aria-label="Estado del sidebar">
                        <button
                            type="button"
                            onClick={() => onSidebarModeChange('collapsed')}
                            className="gelia-segment-btn px-5 sm:px-6"
                            data-active={sidebarMode === 'collapsed'}
                        >
                            Contraída
                        </button>
                        <button
                            type="button"
                            onClick={() => onSidebarModeChange('expanded')}
                            className="gelia-segment-btn px-5 sm:px-6"
                            data-active={sidebarMode === 'expanded'}
                        >
                            Desplegada
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
