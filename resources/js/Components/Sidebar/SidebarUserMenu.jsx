import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { LogOut, Moon, Sparkles, Sun, User, Settings2 } from 'lucide-react';
import { clearEphemeralThemeKeys } from '../../utils/clearEphemeralThemeKeys';

export { clearEphemeralThemeKeys };

export default function SidebarUserMenu({
    open,
    menuRef,
    onClose,
    isDarkMode,
    toggleTheme,
    className = '',
    style,
}) {
    const { post } = useForm({});

    if (!open) return null;

    const itemClass = 'gelia-pro-sidebar__user-menu-item';

    return (
        <div
            ref={menuRef}
            className={`gelia-pro-sidebar__user-menu ${className}`.trim()}
            style={style}
            role="menu"
            aria-label="Menú de cuenta"
        >
            <Link
                href={typeof route === 'function' ? route('profile.index') : '/perfil'}
                role="menuitem"
                className={itemClass}
                onClick={onClose}
            >
                <User className="w-4 h-4" aria-hidden />
                Mi perfil
            </Link>
            <Link
                href={typeof route === 'function' ? route('profile.preferencias') : '/perfil/preferencias'}
                role="menuitem"
                className={itemClass}
                onClick={onClose}
            >
                <Settings2 className="w-4 h-4" aria-hidden />
                Preferencias
            </Link>
            <Link
                href={typeof route === 'function' ? route('profile.novedades') : '/perfil/novedades'}
                role="menuitem"
                className={itemClass}
                onClick={onClose}
            >
                <Sparkles className="w-4 h-4" aria-hidden />
                Novedades
            </Link>
            <button
                type="button"
                role="menuitem"
                className={itemClass}
                onClick={() => {
                    toggleTheme?.();
                    onClose?.();
                }}
            >
                {isDarkMode
                    ? <Sun className="w-4 h-4" aria-hidden />
                    : <Moon className="w-4 h-4" aria-hidden />}
                {isDarkMode ? 'Tema claro' : 'Tema oscuro'}
            </button>
            <button
                type="button"
                role="menuitem"
                className={`${itemClass} gelia-pro-sidebar__user-menu-item--danger`}
                onClick={() => {
                    clearEphemeralThemeKeys();
                    post(typeof route === 'function' ? route('logout') : '/logout');
                }}
            >
                <LogOut className="w-4 h-4" aria-hidden />
                Cerrar sesión
            </button>
        </div>
    );
}
