import React, { useEffect, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { LogOutIcon } from '@animateicons/react/lucide/log-out-icon';
import { MoonIcon } from '@animateicons/react/lucide/moon-icon';
import { SettingsIcon } from '@animateicons/react/lucide/settings-icon';
import { SparklesIcon } from '@animateicons/react/lucide/sparkles-icon';
import { SunIcon } from '@animateicons/react/lucide/sun-icon';
import { UserIcon } from '@animateicons/react/lucide/user-icon';
import useIconoEnControl from '../../hooks/useIconoEnControl';
import { clearEphemeralThemeKeys } from '../../utils/clearEphemeralThemeKeys';
import { abrirAjustes } from '../Ajustes/ajustesEvents';

export { clearEphemeralThemeKeys };

/** Debe coincidir con la duración de geliaProMenuOut en sidebar-professional.css. */
export const USER_MENU_EXIT_MS = 160;

/**
 * Mantiene montado el menú mientras corre su animación de salida.
 * `open` entra de inmediato; al cerrarse sigue presente USER_MENU_EXIT_MS con estado "closing".
 */
export function useMenuPresence(open, exitMs = USER_MENU_EXIT_MS) {
    const [present, setPresent] = useState(open);

    useEffect(() => {
        if (open) {
            setPresent(true);
            return undefined;
        }
        const timer = setTimeout(() => setPresent(false), exitMs);
        return () => clearTimeout(timer);
    }, [open, exitMs]);

    return { present: open || present, state: open ? 'open' : 'closing' };
}

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
    const ajustes = useIconoEnControl();
    const novedades = useIconoEnControl();
    const tema = useIconoEnControl();
    const salir = useIconoEnControl();
    const { present, state } = useMenuPresence(open);

    if (!present) return null;

    const itemClass = 'gelia-pro-sidebar__user-menu-item';
    const closing = state === 'closing';

    return (
        <div
            ref={menuRef}
            className={`gelia-pro-sidebar__user-menu ${className}`.trim()}
            style={style}
            role="menu"
            aria-label="Menú de cuenta"
            data-state={state}
            inert={closing}
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
            <button
                type="button"
                role="menuitem"
                className={itemClass}
                aria-haspopup="dialog"
                {...ajustes.triggerProps}
                onClick={() => {
                    onClose?.();
                    abrirAjustes();
                }}
            >
                <SettingsIcon ref={ajustes.ref} size={16} duration={1} isAnimated={false} className="w-4 h-4" aria-hidden />
                Ajustes
            </button>
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
            <span className="gelia-pro-sidebar__user-menu-sep" role="separator" />
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
