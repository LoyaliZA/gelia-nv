import { useEffect, useRef } from 'react';
import { animate } from 'motion';
import { Check } from 'lucide-react';

const ETAPAS = ['Preparando archivos', 'Aplicando cambios', 'Reiniciando la vista'];

/** Porcentaje, barra y etapas. Un solo `animate` de motion los mueve sin re-renderizar React. */
export default function ProgresoActualizacion({ duracionMs, nota }) {
    const numero = useRef(null);
    const relleno = useRef(null);
    const lista = useRef(null);

    useEffect(() => {
        const control = animate(0, 1, {
            duration: duracionMs / 1000,
            ease: [0.45, 0, 0.2, 1],
            onUpdate: (p) => {
                if (numero.current) {
                    numero.current.textContent = String(Math.round(p * 100));
                }
                if (relleno.current) {
                    relleno.current.style.transform = `scaleX(${p})`;
                }
                if (lista.current) {
                    lista.current.dataset.etapa = String(Math.min(2, Math.floor(p * 3)));
                }
            },
        });

        return () => control.stop();
    }, [duracionMs]);

    return (
        <div className="gelia-progreso">
            <p className="gelia-progreso-cifra" aria-hidden="true">
                <span ref={numero}>0</span>
                <span className="gelia-progreso-simbolo">%</span>
            </p>
            <div className="gelia-actualizacion-barra" aria-hidden="true">
                <span ref={relleno} className="gelia-actualizacion-barra-relleno" />
            </div>
            <ol ref={lista} data-etapa="0" className="gelia-progreso-etapas">
                {ETAPAS.map((etapa, i) => (
                    <li key={etapa} className="gelia-progreso-etapa" style={{ '--i': i }}>
                        <span className="gelia-progreso-marca" aria-hidden="true">
                            <Check className="size-3.5" strokeWidth={3} />
                        </span>
                        {etapa}
                    </li>
                ))}
            </ol>
            {nota ? <p key={nota} className="gelia-progreso-nota">{nota}</p> : null}
        </div>
    );
}
