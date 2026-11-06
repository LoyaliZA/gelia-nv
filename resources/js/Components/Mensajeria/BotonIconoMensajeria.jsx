import useIconoHover from '@/hooks/useIconoHover';

export default function BotonIconoMensajeria({
    icon,
    label,
    onClick,
    className = '',
    disabled = false,
    type = 'button',
    pressed,
    size = 20,
    plano = false,
}) {
    const { nodo, triggerProps } = useIconoHover(icon, {
        size,
        className: 'gelia-mensajeria-icono w-5 h-5',
    });

    return (
        <button
            type={type}
            className={`gelia-toque inline-flex items-center justify-center ${plano ? 'gelia-mensajeria-accion' : ''} ${className}`}
            disabled={disabled}
            title={label}
            aria-label={label}
            aria-pressed={pressed}
            {...triggerProps}
            onClick={onClick}
        >
            {nodo}
        </button>
    );
}
