import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { MoreVertical } from 'lucide-react';
import { THEME_BTN_ICON } from '../../../utils/geliaTheme';

const MENU_PANEL =
    'fixed z-[1000] theme-surface border theme-border shadow-2xl rounded-2xl p-2.5 flex flex-col gap-1.5 backdrop-blur-xl w-60 max-w-[calc(100vw-1rem)]';
const MENU_ITEM =
    'flex min-h-11 items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-colors hover:bg-black/5 dark:hover:bg-white/5 theme-text-main w-full text-left';

/**
 * @param {{ items: Array<{ key: string, label: string, icon?: import('lucide-react').LucideIcon, href?: string, onClick?: () => void, show?: boolean }> }} props
 */
export default function EscalonamientoMenuAcciones({ items = [] }) {
    const [abierto, setAbierto] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });
    const triggerRef = useRef(null);

    const visibles = items.filter((i) => i.show !== false);

    const abrir = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const rect = e.currentTarget.getBoundingClientRect();
        const menuWidth = Math.min(240, window.innerWidth - 16);
        const estimatedHeight = Math.min(320, visibles.length * 48 + 20);
        let left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8);
        left = Math.max(8, left);
        let top = rect.bottom + 8;
        if (top + estimatedHeight > window.innerHeight - 8) {
            top = Math.max(8, rect.top - estimatedHeight - 8);
        }
        setPos({ top, left });
        setAbierto(true);
    };

    const cerrar = () => setAbierto(false);

    useEffect(() => {
        if (!abierto) return undefined;
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                setAbierto(false);
                triggerRef.current?.focus();
            }
        };
        const onViewportChange = () => setAbierto(false);
        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('scroll', onViewportChange, true);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('resize', onViewportChange);
            window.removeEventListener('scroll', onViewportChange, true);
        };
    }, [abierto]);

    if (visibles.length === 0) return null;

    return (
        <>
            <button
                ref={triggerRef}
                type="button"
                onClick={abrir}
                className={`${THEME_BTN_ICON} min-w-11 min-h-11`}
                aria-label="Acciones"
                aria-haspopup="menu"
                aria-expanded={abierto}
            >
                <MoreVertical className="w-5 h-5 theme-text-main" aria-hidden />
            </button>
            {abierto && createPortal(
                <>
                    <div className="fixed inset-0 z-[999]" onClick={cerrar} aria-hidden />
                    <div role="menu" className={MENU_PANEL} style={{ top: pos.top, left: pos.left }}>
                        {visibles.map((item) => {
                            const Icon = item.icon;
                            const contenido = (
                                <>
                                    {Icon ? <Icon className="w-4 h-4 shrink-0" aria-hidden /> : null}
                                    {item.label}
                                </>
                            );
                            if (item.href) {
                                return (
                                    <a
                                        key={item.key}
                                        href={item.href}
                                        role="menuitem"
                                        className={MENU_ITEM}
                                        onClick={cerrar}
                                    >
                                        {contenido}
                                    </a>
                                );
                            }
                            return (
                                <button
                                    key={item.key}
                                    type="button"
                                    role="menuitem"
                                    className={MENU_ITEM}
                                    onClick={() => {
                                        cerrar();
                                        item.onClick?.();
                                    }}
                                >
                                    {contenido}
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
