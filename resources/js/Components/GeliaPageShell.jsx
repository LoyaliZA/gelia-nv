import React from 'react';
import { GELIA_PAGE_SHELL } from '../utils/geliaTheme';

/**
 * Contenedor estándar de página bajo AppLayout (max-w 1400px + padding responsivo).
 */
export default function GeliaPageShell({ children, className = '', as: Tag = 'div', ...props }) {
    return (
        <Tag className={`${GELIA_PAGE_SHELL} ${className}`.trim()} {...props}>
            {children}
        </Tag>
    );
}
