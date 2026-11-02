import useIconoEnControl from '@/hooks/useIconoEnControl';
import { iconoAnimadoDe } from '@/icons/registroIconosAnimados';

export default function useIconoHover(Icon, { size = 16, className = '' } = {}) {
    const Animado = iconoAnimadoDe(Icon);
    const { ref, triggerProps } = useIconoEnControl();

    if (!Icon) {
        return { nodo: null, triggerProps: {} };
    }

    if (!Animado) {
        return {
            nodo: <Icon className={className} aria-hidden="true" />,
            triggerProps: {},
        };
    }

    return {
        nodo: (
            <Animado
                ref={ref}
                size={size}
                duration={1}
                isAnimated={false}
                color="currentColor"
                className={`gelia-icono-animado ${className}`.trim()}
                aria-hidden="true"
            />
        ),
        triggerProps,
    };
}
