import React from 'react';

/** An operational instruction with the same semantic colors as the Gelia theme. */
export default function AvisoOperativoPedido({ label, children, tono = 'neutral', icon: Icon = null, className = '' }) {
    const color = {
        neutral: 'var(--theme-border)',
        info: 'var(--color-info)',
        blue: 'var(--color-info)',
        success: 'var(--color-exito)',
        warning: 'var(--color-aviso)',
        danger: 'var(--color-peligro)',
    }[tono] || 'var(--theme-border)';

    return (
        <div className={`p-3 rounded-xl border ${className}`} style={{
            backgroundColor: `color-mix(in srgb, ${color} 6%, var(--theme-surface-solid))`,
            borderColor: `color-mix(in srgb, ${color} 30%, var(--theme-border))`,
        }}>
            {label && <p className="text-xs font-semibold theme-text-main m-0 mb-1.5">{label}</p>}
            <div className="flex items-start gap-2 theme-text-main">
                {Icon && <Icon className="w-4 h-4 shrink-0 mt-0.5" style={{ color }} aria-hidden="true" />}
                <div className="min-w-0 flex-1 text-sm font-medium leading-relaxed break-words">{children}</div>
            </div>
        </div>
    );
}
