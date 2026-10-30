import useIconoHover from '@/hooks/useIconoHover';

export default function EtiquetaConIcono({
    icon,
    className = '',
    iconClassName = 'h-3.5 w-3.5',
    size = 14,
    children,
}) {
    const { nodo, triggerProps } = useIconoHover(icon, { size, className: iconClassName });

    return (
        <span className={className} {...triggerProps}>
            {nodo}
            {children}
        </span>
    );
}
