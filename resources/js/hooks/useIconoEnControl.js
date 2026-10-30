import { useIconHover } from '@animateicons/react';

/**
 * El control (botón, tarjeta o ítem de menú) dispara el icono.
 * useIconHover cubre el puntero; el foco completa el teclado.
 */
export default function useIconoEnControl(trigger = 'hover') {
    const { ref, triggerProps } = useIconHover({ trigger });

    return {
        ref,
        triggerProps: {
            ...triggerProps,
            onFocus: (event) => {
                ref.current?.startAnimation();
                triggerProps.onFocus?.(event);
            },
            onBlur: (event) => {
                ref.current?.stopAnimation();
                triggerProps.onBlur?.(event);
            },
        },
    };
}
