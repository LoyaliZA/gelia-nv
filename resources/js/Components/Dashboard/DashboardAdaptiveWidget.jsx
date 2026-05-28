import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function DashboardAdaptiveWidget({
    title,
    icon: Icon,
    iconClassName = '',
    badge = null,
    summary = null,
    href,
    ctaLabel = 'Explorar',
    minimalCount = null,
    minimalCountLabel = '',
    variant = 'desktop',
    children,
}) {
    if (variant === 'mobile') {
        return (
            <div className="dashboard-widget-mobile theme-surface theme-border shadow-sm">
                <div className="dashboard-widget-mobile__header">
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        {Icon && <Icon className={`w-5 h-5 shrink-0 ${iconClassName}`} />}
                        <h2 className="dashboard-widget-mobile__title theme-text-main">{title}</h2>
                    </div>
                    {badge}
                </div>

                {summary && <div className="mb-3">{summary}</div>}

                <div className="dashboard-widget-mobile__body custom-scrollbar">{children}</div>

                {href && (
                    <div className="dashboard-widget-mobile__footer theme-border">
                        <Link
                            href={href}
                            className="w-full py-3 rounded-xl font-black uppercase tracking-widest text-[10px] transition-all shadow-sm flex justify-center items-center gap-2 outline-none theme-element border theme-border text-zinc-500 dark:text-zinc-400 text-center px-2"
                        >
                            <span className="truncate">{ctaLabel}</span>
                            <ArrowRight className="w-4 h-4 shrink-0" />
                        </Link>
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="dashboard-adaptive-widget theme-surface theme-border shadow-sm group">
            <div
                className="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity duration-500 pointer-events-none rounded-[inherit]"
                style={{ background: 'linear-gradient(180deg, var(--color-primario) 0%, transparent 100%)' }}
            />

            <div className="dashboard-widget__header relative z-10 min-w-0">
                <div className="flex items-center gap-2 min-w-0 flex-1">
                    {Icon && <Icon className={`w-5 h-5 shrink-0 ${iconClassName}`} />}
                    <h2 className="dashboard-widget__header-title theme-text-main truncate group-hover:text-[var(--color-primario)] transition-colors">
                        {title}
                    </h2>
                </div>
                {badge}
            </div>

            {summary && (
                <div className="dashboard-widget__summary relative z-10">
                    {summary}
                </div>
            )}

            <div className="dashboard-widget__body relative z-10 custom-scrollbar">
                {children}
            </div>

            {href && (
                <>
                    <Link
                        href={href}
                        className="dashboard-widget__minimal-link relative z-10 theme-text-main outline-none"
                        title={ctaLabel}
                    >
                        {Icon && <Icon className={`w-8 h-8 ${iconClassName}`} />}
                        {minimalCount != null && (
                            <span className="text-lg font-black" style={{ color: 'var(--color-primario)' }}>
                                {minimalCount}
                            </span>
                        )}
                        {minimalCountLabel && (
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                {minimalCountLabel}
                            </span>
                        )}
                    </Link>

                    <div className="dashboard-widget__footer relative z-10 theme-border">
                        <Link
                            href={href}
                            className="dashboard-widget__footer-link w-full py-3 sm:py-4 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] transition-all duration-300 shadow-sm flex justify-center items-center gap-2 outline-none theme-element border theme-border text-zinc-500 dark:text-zinc-400 group-hover:bg-[var(--color-primario)] group-hover:text-white group-hover:border-[var(--color-primario)] text-center px-2"
                        >
                            <span className="truncate">{ctaLabel}</span>
                            <ArrowRight className="w-4 h-4 shrink-0" />
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}
