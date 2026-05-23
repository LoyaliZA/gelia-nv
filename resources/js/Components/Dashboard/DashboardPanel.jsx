import React from 'react';

/**
 * Contenedor de panel del dashboard: ocupa el 100% del área asignada y hace scroll interno si hace falta.
 */
export default function DashboardPanel({ title, icon: Icon, iconStyle, iconClassName = '', headerActions, children }) {
    return (
        <div className="h-full w-full min-h-0 flex flex-col overflow-hidden theme-surface border-2 theme-border p-4 sm:p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm">
            <div className="flex items-center justify-between border-b theme-border pb-4 shrink-0 gap-3 min-w-0">
                <div className="flex items-center gap-3 min-w-0 flex-1">
                    {Icon && (
                        <Icon
                            className={`w-5 h-5 shrink-0 ${iconClassName}`}
                            style={iconStyle}
                        />
                    )}
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main truncate min-w-0">
                        {title}
                    </h2>
                </div>
                {headerActions && (
                    <div className="flex items-center gap-2 shrink-0 flex-wrap justify-end">{headerActions}</div>
                )}
            </div>
            <div className="flex-1 min-h-0 mt-4 overflow-y-auto overflow-x-hidden custom-scrollbar">{children}</div>
        </div>
    );
}

/**
 * Grid interno que rellena el ancho del panel con columnas fluidas.
 */
export function DashboardPanelCards({ children, emptyMessage }) {
    if (React.Children.count(children) === 0 && emptyMessage) {
        return (
            <div className="p-8 border-2 border-dashed theme-border rounded-[1.5rem] text-center theme-element">
                <p className="text-xs font-bold theme-text-muted uppercase tracking-widest">{emptyMessage}</p>
            </div>
        );
    }

    return (
        <div className="grid w-full gap-4 sm:gap-6 [grid-template-columns:repeat(auto-fill,minmax(min(100%,10.5rem),1fr))] items-stretch">
            {children}
        </div>
    );
}

export function DashboardCardSlot({ children }) {
    return <div className="h-full min-h-[8.5rem] w-full min-w-0">{children}</div>;
}
