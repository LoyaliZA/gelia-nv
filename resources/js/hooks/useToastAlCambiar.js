import { useEffect, useRef } from 'react';
import { emitirToastGelia } from '../utils/geliaToast';

export default function useToastAlCambiar(mensaje, tipo = 'error') {
    const ultimaFirmaRef = useRef(null);

    useEffect(() => {
        if (!mensaje) {
            return;
        }

        const firma = `${tipo}:${mensaje}`;
        if (firma === ultimaFirmaRef.current) {
            return;
        }

        ultimaFirmaRef.current = firma;
        emitirToastGelia(mensaje, tipo);
    }, [mensaje, tipo]);
}
