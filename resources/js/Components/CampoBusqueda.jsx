import { useRef } from 'react';
import { SearchIcon } from '@animateicons/react/lucide/search-icon';

/**
 * Lupa que se anima al enfocar el campo y se detiene al salir.
 * El clip no se repite mientras se escribe.
 * `icono` sustituye la lupa (por ejemplo un indicador de carga).
 */
export default function CampoBusqueda({
    wrapperClassName = '',
    iconClassName = 'theme-field-icon',
    icono = null,
    suffix = null,
    ...inputProps
}) {
    const ref = useRef(null);
    const { onFocus, onBlur, type = 'search', ...rest } = inputProps;

    return (
        <div className={`theme-field-with-icon ${wrapperClassName}`.trim()}>
            {icono || (
                <span className={iconClassName} aria-hidden="true">
                    <SearchIcon
                        ref={ref}
                        size={16}
                        isAnimated={false}
                        color="currentColor"
                    />
                </span>
            )}
            <input
                type={type}
                {...rest}
                onFocus={(event) => {
                    ref.current?.startAnimation();
                    onFocus?.(event);
                }}
                onBlur={(event) => {
                    ref.current?.stopAnimation();
                    onBlur?.(event);
                }}
            />
            {suffix}
        </div>
    );
}
