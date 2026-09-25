import React from 'react';
import { Link } from '@inertiajs/react';
import { PanelLeftClose, PanelLeftOpen, X } from 'lucide-react';
import GeliaLogo from '../GeliaLogo';

export default function SidebarBrand({ collapsed = false, onToggleCollapse, showToggle = true, onClose }) {
    return (
        <div className={`gelia-pro-sidebar__brand ${collapsed ? 'gelia-pro-sidebar__brand--collapsed' : ''}`}>
            <div className="gelia-pro-sidebar__brand-main">
                <Link
                    href={typeof route === 'function' ? route('dashboard') : '/dashboard'}
                    className="gelia-pro-sidebar__brand-logo gelia-pro-sidebar__tooltip"
                    data-tip={collapsed ? 'Panel principal' : ''}
                    aria-label="Panel principal"
                >
                    <GeliaLogo variant="sparkle" className="w-7 h-7 sm:w-8 sm:h-8" />
                </Link>
                <div className="gelia-pro-sidebar__brand-text">
                    <p className="gelia-pro-sidebar__brand-title">GELIA</p>
                    <p className="gelia-pro-sidebar__brand-subtitle">GELIA NV</p>
                </div>
            </div>
            {onClose ? (
                <button
                    type="button"
                    className="gelia-pro-sidebar__toggle"
                    onClick={onClose}
                    aria-label="Cerrar menú"
                >
                    <X className="w-4 h-4" aria-hidden />
                </button>
            ) : showToggle && (
                <button
                    type="button"
                    className="gelia-pro-sidebar__toggle gelia-pro-sidebar__tooltip"
                    onClick={onToggleCollapse}
                    aria-label={collapsed ? 'Expandir navegación' : 'Contraer navegación'}
                    aria-pressed={collapsed}
                    data-tip={collapsed ? 'Expandir' : ''}
                >
                    {collapsed
                        ? <PanelLeftOpen className="w-4 h-4" aria-hidden />
                        : <PanelLeftClose className="w-4 h-4" aria-hidden />}
                </button>
            )}
        </div>
    );
}
