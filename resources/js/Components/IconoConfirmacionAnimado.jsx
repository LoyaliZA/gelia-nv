import { useEffect, useRef } from 'react';
import { CircleCheckIcon } from '@animateicons/react/lucide/circle-check-icon';
import { PackageCheckIcon } from '@animateicons/react/lucide/package-check-icon';

const ICONOS = {
    check: CircleCheckIcon,
    paquete: PackageCheckIcon,
};

export default function IconoConfirmacionAnimado({
    variante = 'check',
    size = 48,
    className = '',
}) {
    const ref = useRef(null);
    const Icono = ICONOS[variante] || ICONOS.check;

    useEffect(() => {
        ref.current?.startAnimation();
    }, []);

    return (
        <Icono
            ref={ref}
            size={size}
            duration={1}
            isAnimated={false}
            color="currentColor"
            className={className}
            aria-hidden="true"
        />
    );
}
