import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { MoreVertical } from 'lucide-react';
import { BTN_KEBAB, MENU_ITEM, MENU_PANEL } from './safStyles';

/**
 * Botón ⋮ + menú flotante (portal), mismo patrón visual que Solicitudes TAG.
 *
 * @param {{ items: Array<{ key: string, label: string, icon: import('lucide-react').LucideIcon, tone?: string, onClick: () => void, show?: boolean }> }} props
 */
export default function SafMenuAcciones({ items = [], align = 'right' }) {
    const [abierto, setAbierto] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });

    const visibles = items.filter((i) => i.show !== false);
    if (visibles.length === 0) return null;

    const abrir = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const rect = e.currentTarget.getBoundingClientRect();
        const menuWidth = 224;
        let left = align === 'right' ? rect.right - menuWidth : rect.left;
        if (left < 8) left = 8;
        if (left + menuWidth > window.innerWidth - 8) {
            left = window.innerWidth - menuWidth - 8;
        }
        let top = rect.bottom + 8;
        if (top + 240 > window.innerHeight) {
            top = Math.max(8, rect.top - 8 - Math.min(240, visibles.length * 52));
        }
        setPos({ top, left });
        setAbierto(true);
    };

    const cerrar = () => setAbierto(false);

    return (
        <>
            <button
                type="button"
                onClick={abrir}
                className={BTN_KEBAB}
                aria-label="Acciones"
                aria-haspopup="menu"
                aria-expanded={abierto}
            >
                <MoreVertical className="w-5 h-5 theme-text-main" />
            </button>
            {abierto && createPortal(
                <>
                    <div className="fixed inset-0 z-[999]" onClick={cerrar} aria-hidden />
                    <div
                        role="menu"
                        className={MENU_PANEL}
                        style={{ top: pos.top, left: pos.left }}
                    >
                        {visibles.map((item) => {
                            const Icon = item.icon;
                            return (
                                <button
                                    key={item.key}
                                    type="button"
                                    role="menuitem"
                                    className={MENU_ITEM(item.tone || 'neutral')}
                                    onClick={() => {
                                        cerrar();
                                        item.onClick?.();
                                    }}
                                >
                                    {Icon ? <Icon className="w-4 h-4 shrink-0" /> : null}
                                    {item.label}
                                </button>
                            );
                        })}
                    </div>
                </>,
                document.body
            )}
        </>
    );
}
