import { useCallback, useEffect, useRef, useState } from 'react';
import {
    encolarAlertaPdv,
    expirarColaPdv,
    PDV_COLA_EXPIRACION_MS,
} from '../utils/pdvAlertQueueUtils';

export default function usePdvAlertQueue() {
    const [cola, setCola] = useState([]);
    const idsVistosRef = useRef(new Set());

    const encolar = useCallback((envelope) => {
        let encolado = false;

        setCola((actual) => {
            const resultado = encolarAlertaPdv(actual, idsVistosRef.current, envelope);
            idsVistosRef.current = resultado.idsVistos;
            encolado = resultado.encolado;
            return resultado.cola;
        });

        return encolado;
    }, []);

    const descartar = useCallback((eventId) => {
        setCola((actual) => actual.filter((item) => item.event_id !== eventId));
    }, []);

    const limpiar = useCallback(() => {
        setCola([]);
    }, []);

    const reiniciar = useCallback(() => {
        idsVistosRef.current = new Set();
        setCola([]);
    }, []);

    useEffect(() => {
        const intervalo = setInterval(() => {
            setCola((actual) => {
                const filtrada = expirarColaPdv(actual);
                return filtrada.length === actual.length ? actual : filtrada;
            });
        }, Math.min(PDV_COLA_EXPIRACION_MS, 5_000));

        return () => clearInterval(intervalo);
    }, []);

    return {
        cola,
        encolar,
        descartar,
        limpiar,
        reiniciar,
    };
}
