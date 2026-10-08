import { useEffect, useState } from 'react';

/** En viewports menores a md la tabla horizontal no es usable; forzar tarjetas. */
export function useVistaCompactaResguardo() {
    const [compacto, setCompacto] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') return undefined;
        const mq = window.matchMedia('(max-width: 767px)');
        const actualizar = () => setCompacto(mq.matches);
        actualizar();
        mq.addEventListener('change', actualizar);
        return () => mq.removeEventListener('change', actualizar);
    }, []);

    return compacto;
}
