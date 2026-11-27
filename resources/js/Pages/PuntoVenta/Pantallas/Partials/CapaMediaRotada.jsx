import React, { useEffect, useRef, useState } from 'react';
import { estiloCapaRotada, normalizarRotacionPublicidad } from '@/utils/rotacionMedioPublicidad';

export default function CapaMediaRotada({
    rotacion = 0,
    className = '',
    children,
}) {
    const marcoRef = useRef(null);
    const [medidas, setMedidas] = useState({ ancho: 0, alto: 0 });
    const grados = normalizarRotacionPublicidad(rotacion);

    useEffect(() => {
        const marco = marcoRef.current;
        if (!marco || typeof ResizeObserver === 'undefined') return undefined;

        const medir = () => {
            const rect = marco.getBoundingClientRect();
            setMedidas({ ancho: rect.width, alto: rect.height });
        };

        medir();
        const observer = new ResizeObserver(medir);
        observer.observe(marco);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={marcoRef}
            className={`relative h-full w-full overflow-hidden ${className}`.trim()}
            data-pdv-medio-rotado={grados}
        >
            <div style={estiloCapaRotada(grados, medidas.ancho, medidas.alto)}>
                {children}
            </div>
        </div>
    );
}
