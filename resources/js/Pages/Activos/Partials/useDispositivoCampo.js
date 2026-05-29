import { useEffect, useState } from 'react';

export function esDispositivoCampo() {
    if (typeof window === 'undefined') return false;
    const coarse = window.matchMedia?.('(pointer: coarse)')?.matches;
    const mobileUa = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
    return coarse || mobileUa;
}

export function esViewportMovil(breakpoint = 1024) {
    if (typeof window === 'undefined') return false;
    return window.matchMedia(`(max-width: ${breakpoint - 1}px)`).matches;
}

export default function useDispositivoCampo() {
    const [estado, setEstado] = useState({
        esCampo: false,
        esMovil: false,
    });

    useEffect(() => {
        const actualizar = () => {
            setEstado({
                esCampo: esDispositivoCampo(),
                esMovil: esViewportMovil(),
            });
        };

        actualizar();
        window.addEventListener('resize', actualizar);
        return () => window.removeEventListener('resize', actualizar);
    }, []);

    return estado;
}
