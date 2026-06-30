import React from 'react';
import { Link } from '@inertiajs/react';

/**
 * Enlace hoja del sidebar (accesos y perfil) — estados activos idénticos.
 * Tipografía definida en resources/css/gelia/features/sidebar.css
 */
export default function SidebarNavLeafLink({
    href,
    active = false,
    onClick,
    icon: Icon,
    label,
    description,
    paddingClass = 'pl-10',
    extraClassName = '',
    role,
}) {
    return (
        <Link
            href={href}
            onClick={onClick}
            role={role}
            aria-current={active ? 'page' : undefined}
            className={`gelia-sidebar-nav-child-link group flex items-center w-full py-2 pr-4 rounded-lg transition-colors outline-none border-l-2 ${paddingClass} ${
                active
                    ? 'gelia-sidebar-nav-child-link--active border-[var(--color-primario)]'
                    : 'border-transparent'
            } ${extraClassName}`.trim()}
        >
            <div className="flex items-center shrink-0 mt-0.5">
                <Icon
                    className={`w-3.5 h-3.5 mr-2.5 ${active ? 'gelia-sidebar-nav-child-icon--active' : 'gelia-sidebar-nav-child-icon'}`}
                    aria-hidden
                />
            </div>
            <div className="flex flex-col min-w-0">
                <span
                    className={`gelia-sidebar-nav-child-text truncate ${
                        active ? 'gelia-sidebar-nav-child-text--active' : 'gelia-sidebar-nav-child-text--muted'
                    }`}
                >
                    {label}
                </span>
                {description && (
                    <span className="text-xs mt-0.5 text-gray-500 dark:text-gray-400 whitespace-normal line-clamp-2 leading-tight">
                        {description}
                    </span>
                )}
            </div>
        </Link>
    );
}
