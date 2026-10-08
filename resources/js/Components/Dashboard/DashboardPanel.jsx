import React from 'react';
import { GELIA_ICON_BOX } from '../../utils/geliaTheme';

/**
 * Contenedor de panel del dashboard.
 * variant="desktop" → grilla adaptativa con container queries
 * variant="mobile"  → layout fijo sin container queries
 */
export default function DashboardPanel({
    title,
    icon: Icon,
    iconStyle,
    iconClassName = '',
    headerActions,
    variant = 'desktop',
    panelClassName = '',
    children,
}) {
    const iconColorStyle = iconStyle || (iconClassName ? undefined : { color: 'var(--color-primario)' });

    const iconNode = Icon ? (
        <span className={`${GELIA_ICON_BOX} !p-2 shrink-0`} aria-hidden>
            <Icon className={`w-4 h-4 ${iconClassName}`} style={iconColorStyle} />
        </span>
    ) : null;

    if (variant === 'mobile') {
        return (
            <div className={`dashboard-panel-mobile theme-surface theme-border shadow-sm ${panelClassName}`.trim()}>
                <div className="dashboard-panel-mobile__header theme-border">
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        {iconNode}
                        <h2 className="dashboard-panel-mobile__title theme-text-main truncate min-w-0">{title}</h2>
                    </div>
                    {headerActions && <div className="flex items-center gap-2 shrink-0">{headerActions}</div>}
                </div>
                <div className="dashboard-panel-mobile__body">{children}</div>
            </div>
        );
    }

    const shellClass = [
        'dashboard-panel-shell',
        'dashboard-panel-shell--card-grid',
        'h-full w-full min-h-0 flex flex-col',
        'theme-surface theme-border shadow-sm',
        panelClassName,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className={shellClass}>
            <div className="dashboard-panel-shell__header flex items-center justify-between border-b theme-border shrink-0 gap-3 min-w-0">
                <div className="flex items-center gap-2 min-w-0 flex-1">
                    {iconNode}
                    <h2 className="dashboard-panel-shell__title theme-text-main min-w-0">{title}</h2>
                </div>
                {headerActions && (
                    <div className="flex items-center gap-2 shrink-0 flex-wrap justify-end">{headerActions}</div>
                )}
            </div>
            <div className="dashboard-panel-shell__body flex-1 min-h-0 overflow-y-auto overflow-x-hidden custom-scrollbar flex flex-col">
                {children}
            </div>
        </div>
    );
}

export function DashboardPanelCards({ children, emptyMessage, variant = 'desktop' }) {
    if (React.Children.count(children) === 0 && emptyMessage) {
        return (
            <div className="dashboard-panel-empty theme-element border theme-border">
                <p className="dashboard-panel-empty__text m-0">{emptyMessage}</p>
            </div>
        );
    }

    if (variant === 'mobile') {
        return <div className="dashboard-panel-mobile__grid">{children}</div>;
    }

    return (
        <div className="dashboard-panel-cards flex-1 min-h-0 min-w-0">
            <div className="dashboard-panel-cards__grid">{children}</div>
        </div>
    );
}

export function DashboardCardSlot({ children, variant = 'desktop' }) {
    if (variant === 'mobile') {
        return <>{children}</>;
    }

    return <div className="dashboard-card-slot">{children}</div>;
}
