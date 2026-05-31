import React from 'react';

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
    if (variant === 'mobile') {
        return (
            <div className={`dashboard-panel-mobile theme-surface theme-border shadow-sm ${panelClassName}`.trim()}>
                <div className="dashboard-panel-mobile__header theme-border">
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                        {Icon && (
                            <Icon
                                className={`w-5 h-5 shrink-0 ${iconClassName}`}
                                style={iconStyle}
                            />
                        )}
                        <h2 className="dashboard-panel-mobile__title theme-text-main truncate min-w-0">
                            {title}
                        </h2>
                    </div>
                    {headerActions && (
                        <div className="flex items-center gap-2 shrink-0">{headerActions}</div>
                    )}
                </div>
                <div className="dashboard-panel-mobile__body">{children}</div>
            </div>
        );
    }

    const shellClass = [
        'dashboard-panel-shell',
        'dashboard-panel-shell--card-grid',
        'h-full w-full min-h-0 flex flex-col overflow-hidden',
        'theme-surface border-2 theme-border',
        'p-3 sm:p-4 md:p-6 lg:p-8 rounded-[1.5rem] sm:rounded-[2rem] md:rounded-[2.5rem] shadow-sm',
        panelClassName,
    ].filter(Boolean).join(' ');

    return (
        <div className={shellClass}>
            <div className="dashboard-panel-shell__header flex items-center justify-between border-b theme-border pb-4 shrink-0 gap-3 min-w-0">
                <div className="flex items-center gap-3 min-w-0 flex-1">
                    {Icon && (
                        <Icon
                            className={`w-5 h-5 shrink-0 ${iconClassName}`}
                            style={iconStyle}
                        />
                    )}
                    <h2 className="dashboard-panel-shell__title font-black uppercase tracking-widest theme-text-main truncate min-w-0">
                        {title}
                    </h2>
                </div>
                {headerActions && (
                    <div className="flex items-center gap-2 shrink-0 flex-wrap justify-end">{headerActions}</div>
                )}
            </div>
            <div className="dashboard-panel-shell__body flex-1 min-h-0 mt-4 overflow-y-auto overflow-x-hidden custom-scrollbar flex flex-col">
                {children}
            </div>
        </div>
    );
}

export function DashboardPanelCards({ children, emptyMessage, variant = 'desktop' }) {
    if (React.Children.count(children) === 0 && emptyMessage) {
        return (
            <div className="p-8 border-2 border-dashed theme-border rounded-[1.5rem] text-center theme-element">
                <p className="text-xs font-bold theme-text-muted uppercase tracking-widest">{emptyMessage}</p>
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

    return <div className="dashboard-card-slot w-full min-w-0 h-full min-h-[var(--card-min,3.75rem)]">{children}</div>;
}
