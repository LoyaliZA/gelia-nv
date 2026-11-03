import React from 'react';
import { MessageCircle, MessageSquare } from 'lucide-react';
import { useMensajeriaContext } from '@/Components/Mensajeria/MensajeriaContext';

export function MensajeriaCountBadge({ count, className = '-top-1.5 -right-1.5' }) {
    if (!count || count <= 0) return null;

    return (
        <span
            className={`absolute z-30 flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 text-[9px] font-black leading-none text-white rounded-full shadow-md border-2 theme-surface pointer-events-none ${className}`}
            style={{ backgroundColor: 'var(--color-primario)' }}
            aria-hidden="true"
        >
            {count > 9 ? '9+' : count}
        </span>
    );
}

export default function MensajeriaWidget({ iconButtonClassName = '' }) {
    const { totalUnread, abrirWidget, enPaginaMensajeria } = useMensajeriaContext();
    const clase = `relative overflow-visible theme-element border theme-border rounded-2xl hover:border-[var(--color-primario)] transition-all group outline-none shrink-0 ${iconButtonClassName || 'p-3'}`;

    const icono = totalUnread > 0 ? (
        <MessageSquare className="w-5 h-5 animate-pulse" style={{ color: 'var(--color-primario)' }} />
    ) : (
        <MessageCircle className="w-5 h-5 theme-text-muted group-hover:text-[var(--color-primario)] transition-colors" />
    );

    if (enPaginaMensajeria) {
        return (
            <span className={clase} aria-current="page" title="Mensajería">
                {icono}
                <MensajeriaCountBadge count={totalUnread} />
            </span>
        );
    }

    return (
        <button
            type="button"
            onClick={abrirWidget}
            className={clase}
            aria-label="Mensajería"
        >
            {icono}
            <MensajeriaCountBadge count={totalUnread} />
        </button>
    );
}
