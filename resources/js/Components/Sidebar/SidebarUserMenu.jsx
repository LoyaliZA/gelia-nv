import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { LogOutIcon } from '@animateicons/react/lucide/log-out-icon';
import { MoonIcon } from '@animateicons/react/lucide/moon-icon';
import { SettingsIcon } from '@animateicons/react/lucide/settings-icon';
import { SparklesIcon } from '@animateicons/react/lucide/sparkles-icon';
import { SunIcon } from '@animateicons/react/lucide/sun-icon';
import { UserIcon } from '@animateicons/react/lucide/user-icon';
import useIconoEnControl from '../../hooks/useIconoEnControl';
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
    const perfil = useIconoEnControl();
    const preferencias = useIconoEnControl();
    const novedades = useIconoEnControl();
    const tema = useIconoEnControl();
    const salir = useIconoEnControl();

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
                {...perfil.triggerProps}
                onClick={onClose}
            >
                <UserIcon ref={perfil.ref} size={16} duration={1} isAnimated={false} className="w-4 h-4" aria-hidden />
                Mi perfil
            </Link>
            <Link
                href={typeof route === 'function' ? route('profile.preferencias') : '/perfil/preferencias'}
                role="menuitem"
                className={itemClass}
                {...preferencias.triggerProps}
                onClick={onClose}
            >
                <SettingsIcon ref={preferencias.ref} size={16} duration={1} isAnimated={false} className="w-4 h-4" aria-hidden />
                Preferencias
            </Link>
            <Link
                href={typeof route === 'function' ? route('profile.novedades') : '/perfil/novedades'}
                role="menuitem"
                className={itemClass}
                {...novedades.triggerProps}
                onClick={onClose}
            >
                <SparklesIcon ref={novedades.ref} size={16} duration={1} isAnimated={false} className="w-4 h-4" aria-hidden />
                Novedades
            </Link>
            <button
                type="button"
                role="menuitem"
                className={itemClass}
                {...tema.triggerProps}
                onClick={() => {
                    toggleTheme?.();
                    onClose?.();
                }}
            >
                {isDarkMode
                    ? <SunIcon ref={tema.ref} size={16} duration={1} isAnimated={false} className="w-4 h-4" aria-hidden />
                    : <MoonIcon ref={tema.ref} size={16} duration={1} isAnimated={false} className="w-4 h-4" aria-hidden />}
                {isDarkMode ? 'Tema claro' : 'Tema oscuro'}
            </button>
            <button
                type="button"
                role="menuitem"
                className={`${itemClass} gelia-pro-sidebar__user-menu-item--danger`}
                {...salir.triggerProps}
                onClick={() => {
                    clearEphemeralThemeKeys();
                    post(typeof route === 'function' ? route('logout') : '/logout');
                }}
            >
                <LogOutIcon ref={salir.ref} size={16} duration={1} isAnimated={false} className="w-4 h-4" aria-hidden />
                Cerrar sesión
            </button>
        </div>
    );
}
