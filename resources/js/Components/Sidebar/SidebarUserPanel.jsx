import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronUp } from 'lucide-react';
import SidebarUserMenu from './SidebarUserMenu';

function buildUserMenuStyle(rect) {
    const gap = 8;
    const widthPx = 14 * 16;
    const left = Math.max(gap, Math.min(rect.right + gap, window.innerWidth - widthPx - gap));
    // bottom = distancia del borde inferior del menú al borde inferior del viewport
    // (alineado al bottom del avatar; el menú crece hacia arriba).
    const bottom = Math.max(gap, window.innerHeight - rect.bottom);
    return {
        position: 'fixed',
        top: 'auto',
        right: 'auto',
        bottom: `${bottom}px`,
        left: `${left}px`,
        width: '14rem',
        maxHeight: `min(22rem, ${Math.max(120, rect.bottom - gap)}px)`,
        overflowY: 'auto',
        zIndex: 206,
    };
}

export default function SidebarUserPanel({
    user,
    collapsed = false,
    userMenuOpen,
    userButtonRef,
    userMenuRef,
    onToggleMenu,
    onCloseMenu,
    isDarkMode,
    toggleTheme,
}) {
    const roleLabel = user?.roles?.[0] || user?.puesto || user?.departamento || 'Colaborador';
    const displayName = user?.name || 'Usuario';
    const [menuStyle, setMenuStyle] = useState(null);
    const internalBtnRef = useRef(null);

    const setBtnRef = useCallback((el) => {
        internalBtnRef.current = el;
        if (typeof userButtonRef === 'function') userButtonRef(el);
        else if (userButtonRef) userButtonRef.current = el;
    }, [userButtonRef]);

    const handleToggle = useCallback((event) => {
        if (collapsed && !userMenuOpen && event?.currentTarget) {
            setMenuStyle(buildUserMenuStyle(event.currentTarget.getBoundingClientRect()));
        }
        onToggleMenu?.();
    }, [collapsed, userMenuOpen, onToggleMenu]);

    useLayoutEffect(() => {
        if (!userMenuOpen || !collapsed) {
            if (!userMenuOpen) setMenuStyle(null);
            return;
        }
        const btn = internalBtnRef.current;
        const style = menuStyle || (btn ? buildUserMenuStyle(btn.getBoundingClientRect()) : null);
        if (!menuStyle && style) setMenuStyle(style);

        const menuEl = userMenuRef?.current;
        if (!menuEl || !style) return;

        // Forzar posición con !important por si alguna regla de inset compite.
        menuEl.style.setProperty('position', 'fixed', 'important');
        menuEl.style.setProperty('top', 'auto', 'important');
        menuEl.style.setProperty('right', 'auto', 'important');
        menuEl.style.setProperty('bottom', style.bottom, 'important');
        menuEl.style.setProperty('left', style.left, 'important');
        menuEl.style.setProperty('width', style.width, 'important');
        menuEl.style.setProperty('max-height', style.maxHeight, 'important');
        menuEl.style.setProperty('z-index', String(style.zIndex), 'important');
    }, [userMenuOpen, collapsed, menuStyle, userMenuRef]);

    useEffect(() => {
        if (!userMenuOpen || !collapsed) return undefined;
        const onPointer = (event) => {
            if (
                userMenuRef?.current?.contains(event.target)
                || internalBtnRef.current?.contains(event.target)
            ) {
                return;
            }
            onCloseMenu?.();
        };
        const timer = setTimeout(() => document.addEventListener('mousedown', onPointer), 0);
        return () => {
            clearTimeout(timer);
            document.removeEventListener('mousedown', onPointer);
        };
    }, [userMenuOpen, collapsed, userMenuRef, onCloseMenu]);

    const menu = (
        <SidebarUserMenu
            open={userMenuOpen}
            menuRef={userMenuRef}
            onClose={onCloseMenu}
            isDarkMode={isDarkMode}
            toggleTheme={toggleTheme}
            className={collapsed ? 'gelia-pro-sidebar__user-menu--flyout' : ''}
            style={collapsed ? menuStyle || undefined : undefined}
        />
    );

    return (
        <div className="gelia-pro-sidebar__user">
            {!collapsed && menu}
            <button
                ref={setBtnRef}
                type="button"
                className="gelia-pro-sidebar__user-btn gelia-pro-sidebar__tooltip"
                aria-expanded={userMenuOpen}
                aria-haspopup="menu"
                aria-label="Menú de perfil"
                data-tip={collapsed ? displayName : ''}
                onClick={handleToggle}
            >
                <span className="gelia-pro-sidebar__avatar" aria-hidden>
                    {user?.foto_perfil ? (
                        <img src={`/storage/${user.foto_perfil}`} alt="" />
                    ) : (
                        (displayName.charAt(0) || 'U').toUpperCase()
                    )}
                </span>
                <span className="gelia-pro-sidebar__user-meta">
                    <span className="gelia-pro-sidebar__user-name">{displayName}</span>
                    <span className="gelia-pro-sidebar__user-role">{roleLabel}</span>
                </span>
                <ChevronUp
                    className={`w-4 h-4 shrink-0 opacity-60 transition-transform gelia-pro-sidebar__user-chevron ${userMenuOpen ? '' : 'rotate-180'}`}
                    aria-hidden
                />
            </button>
            {collapsed && userMenuOpen && typeof document !== 'undefined' && createPortal(
                <>
                    <div className="gelia-pro-flyout-backdrop" onClick={onCloseMenu} aria-hidden />
                    {menu}
                </>,
                document.body
            )}
        </div>
    );
}
